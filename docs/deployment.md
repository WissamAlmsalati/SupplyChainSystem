# Deploying to cafe.wissam.ly

The whole platform is served from one hostname: the admin dashboard at `/`, the
customer app at `/customer/`, the API at `/api/v1`, the API reference at
`/docs`, and the WebSocket at `/app/local`. That is the layout the code already
assumes — the customer build bakes in its sub-path, both clients call the
relative `/api/v1`, `echo.js` derives its socket URL from the page it is on,
and the admin's service worker is scoped to `/`. Splitting the apps across
subdomains later would mean touching all four.

## Where it runs, and who else lives there

A single-node Kubernetes cluster (v1.30, kubeadm, flannel) on a Contabo box
that **already serves eight other sites**: octobits.ly, store.octobits.ly,
fll.com.ly, api.mahata.octobits.ly and more, all behind one ingress-nginx.

That is the fact everything below is shaped by. This platform lives in its own
`cafe-supply-chain` namespace and owns nothing cluster-wide except three
PersistentVolumes named for it. No deploy restarts the ingress controller,
edits another namespace, or changes a cluster-wide setting.

Three things the cluster decided for us:

| what the cluster has | what it means here |
| -------------------- | ------------------ |
| **no StorageClass at all** | a plain PVC would sit `Pending` for ever waiting for a provisioner. Each claim binds by name to a static hostPath PersistentVolume under `/srv/cafe-supply-chain`. |
| **cert-manager, with a working `letsencrypt-prod` issuer** | TLS is one annotation on the Ingress. No certbot, no renewal cron, nothing to forget. |
| **1.6 GB of RAM free beside eight live sites** | every pod asks for little and is capped. That leaves them *Burstable* rather than Guaranteed on purpose: under node pressure the kubelet should evict this newcomer, not octobits.ly. |

## The shape of it

```
GitHub push to main
  └─ ci.yml      Pint, backend tests (in the project's own image), oxlint, both frontend builds
  └─ build       two images → ghcr.io/wissamalmsalati/supplychainsystem/{backend,frontend}
                 tagged sha-<12 chars> and latest
  └─ deploy      ssh → move the checkout to this commit → kubectl apply → wait for every rollout
```

A release **is** an image tag. The checkout in the deploy user's home holds
only `k8s/` and `scripts/` — application code never comes from git there, it
comes from the image. That is why a rollback is one tag and not a `git revert`.

What runs in the namespace:

| deployment | what it is | notes |
| ---------- | ---------- | ----- |
| `db` | MySQL 8 | `Recreate` strategy — two MySQLs on one data directory corrupt it |
| `redis` | cache, sessions **and the queue** | `volatile-lru`, so TTL'd cache may be evicted and queued jobs never are |
| `app` | php-fpm, the web role | one replica, and the only one that migrates |
| `worker` | `queue:work` | |
| `scheduler` | `schedule:work` | not optional: OTP expiry and token pruning |
| `reverb` | the WebSocket server | |
| `backend-nginx` | FastCGI to `app`, WebSocket to `reverb`, uploaded files | |
| `frontend` | the two built SPAs, nothing else | its nginx config is baked into the image |

All four backend roles are the same image, told apart by `CONTAINER_ROLE` —
exactly as `docker-entrypoint.sh` already decides. Keeping the entrypoint
rather than reimplementing it in Kubernetes means the startup path that runs in
production is the one that runs in development: wait for MySQL, wait for Redis,
`config:cache`, and `migrate --force --isolated` from the web role only.

The Ingress splits traffic by path before it reaches any pod — `/api`, `/docs`
and `/storage` to `backend-nginx`, everything else to `frontend` — so the
frontend no longer proxies anything. The WebSocket gets a second Ingress of its
own because it needs an hour-long read timeout and the API must not have one.

Telescope is not routed and `TELESCOPE_ENABLED` is false: its request log holds
bearer tokens and customer data.

## First time

### 1. GitHub secrets

In the repository's **`development-env` environment** (Settings → Environments),
which is why the deploy job names that environment — secrets scoped to an
environment resolve to empty strings in a job that does not join it.

| secret | required | what |
| ------ | -------- | ---- |
| `IP` | yes | the server's address |
| `USER` | yes | the user the deploy signs in as; needs a working `kubectl` |
| `PASSWORD` | yes | that user's password |
| `PORT` | no | ssh port, 22 if unset |
| `KNOWN_HOSTS` | no | output of `ssh-keyscan -H <ip>`; pins the server's identity |

`GITHUB_TOKEN` is provided by Actions — nothing to add. It pushes the images and
is what the cluster pulls them with.

Without `KNOWN_HOSTS` the workflow trusts whatever host key answers first.
Closing that gap is one command: `ssh-keyscan -H <server-ip>`, pasted in.

### 2. DNS

One record at Cloudflare, where `wissam.ly` is hosted:

```
A    cafe    <server-ip>    Proxy: DNS only (grey cloud)
```

**Grey cloud, not orange.** cert-manager answers Let's Encrypt's HTTP-01
challenge on port 80 directly; a proxied record puts Cloudflare in the way of
it. Once the certificate is issued the record can be switched to proxied if you
want Cloudflare's CDN and DDoS protection — but then set SSL mode to *Full
(strict)*, or Cloudflare will talk plain HTTP to an origin that redirects it
straight back.

### 3. Secrets on the cluster, once

```bash
ssh <user>@<server>
cd ~/cafe-supply-chain     # the deploy clones this on its first run
./scripts/k8s-bootstrap.sh
```

It creates the namespace and generates `APP_KEY`, the database passwords and
the Reverb secret. It is idempotent and **refuses to rewrite a secret that
exists** — `APP_KEY` is what every encrypted column and signed URL was written
with, and replacing it makes them unreadable.

Back it up the moment it is created, and keep it with the database dumps:

```bash
kubectl -n cafe-supply-chain get secret cafe-secrets -o yaml > ~/cafe-secrets-backup.yaml
chmod 600 ~/cafe-secrets-backup.yaml
```

The deploy runs this script too, so a first deploy works without the manual
step — but then nobody has backed the key up.

### 4. Mail

`k8s/30-config.yaml` ships `MAIL_MAILER: log`, which writes password-reset and
registration OTPs to a log file instead of sending them. **Until real SMTP is
set there, nobody outside the office can register or reset a password.** The
`COMPANY_*` entries beside it are the letterhead on every invoice, statement
and report.

### 5. Deploy

Push to `main`, or run the **Deploy** workflow from the Actions tab.

## Every day

Push to `main`. CI runs, the images build, every deployment rolls over, and the
workflow calls `https://cafe.wissam.ly/api/v1/categories` over a trusted
certificate before it reports success.

Migrations run in the `app` pod's entrypoint (`--force --isolated`, web role
only, one replica) so nothing can race them over a `CREATE TABLE`. Seeding is
skipped outside local and development.

## Rolling back

Actions → Deploy → Run workflow → put an older tag in **image_tag**, e.g.
`sha-9529ae4a1b2c`. It skips CI and the build and goes straight to the cluster.
Every tag CI ever pushed is still in GHCR; `kubectl -n cafe-supply-chain get
deploy -o wide` names the running one.

A rollback moves the images back but takes the *current* commit's manifests
with it. If the release you are undoing changed `k8s/`, revert that in git too.

**Migrations do not roll back.** A release that added a column is safe to undo;
one that dropped or renamed a column is not, and needs a forward fix.

## Backups

`scripts/backup-db.sh` dumps MySQL and tars the uploaded receipts and product
images. On this cluster it must run with `KUBE_NS` set, because the database is
a pod under containerd — the server's separate Docker daemon cannot see it, and
a `docker exec` backup would produce nothing while reporting success:

```cron
30 2 * * * KUBE_NS=cafe-supply-chain ~/cafe-supply-chain/scripts/backup-db.sh >> ~/cafe-backup.log 2>&1
```

The data lives in hostPath volumes under `/srv/cafe-supply-chain`. That is this
machine's disk, not a backup: it does not survive losing the box, and the box
is at 79% full with eight other sites on it. Set `OFFSITE` in the cron line to
ship the dumps somewhere else, and keep `cafe-secrets` with them — a dump
without `APP_KEY` is not a restore.

## When it goes wrong

Everything below is scoped to the namespace, so none of it can disturb a
neighbour.

```bash
kubectl -n cafe-supply-chain get pods
kubectl -n cafe-supply-chain logs deploy/app --tail=100
kubectl -n cafe-supply-chain get events --sort-by=.lastTimestamp | tail -30
```

**`ImagePullBackOff`.** The packages are private and the pull secret has
expired — it is the deploy run's own `GITHUB_TOKEN`, refreshed on every deploy.
Pods normally never re-pull, because the manifests pin an immutable `sha-` tag
and the kubelet's default is `IfNotPresent`, but a node restart clears the image
cache. Re-run the Deploy workflow, or make both packages public once (the
repository is public anyway) and the problem cannot recur.

**A pod is `Pending` with "no persistent volumes available".** A claim lost its
binding. The volumes are static and named; `kubectl get pv | grep cafe` shows
whether they are `Bound` or `Released`. A `Released` volume keeps the data and
needs its `claimRef` cleared before it will bind again.

**The site serves an "Kubernetes Ingress Controller Fake Certificate".** The
certificate has not been issued. `kubectl -n cafe-supply-chain describe
certificate cafe-wissam-ly-tls` and `kubectl -n cafe-supply-chain get
challenges` say why — almost always DNS not pointing here yet, or the record
switched to Cloudflare's orange cloud so the HTTP-01 challenge never reaches
the cluster.

**A pod is `OOMKilled`.** This stack is capped deliberately tight for a shared
node. Raise the limit in `k8s/` if the workload genuinely needs it, but check
`kubectl top nodes` first: the honest answer may be that the box is full.

**Zones or addresses answer with a sentence instead of data.** `h3-js` is
missing from the image. It is a runtime dependency of the PHP app, installed by
the `npm ci` line in both stages of `backend/Dockerfile`.

## Why the backend suite runs in Docker on CI

`ArabicText::sqlExpression()` wraps every searched column in 35 nested
`REPLACE()` calls — one per fold pair, plus `LOWER()`. How deep a SQLite build
will parse before giving up is a compile-time constant, and it differs:

| SQLite | max nested calls |
| ------ | ---------------- |
| the `php:8.3-fpm` image's bundled 3.46.1 | 60+ |
| Ubuntu 24.04's system libsqlite3 3.45.1 | fails at 30 |

So the Arabic search tests pass in the dev container and fail on a bare GitHub
runner with `SQLSTATE[HY000]: General error: 1 parser stack overflow`. Building
the project's own dev image and running the suite inside it removes the drift:
CI then tests what the developer tests, and what the server runs.

This is worth fixing in the app, not just routed around. Two things are true:

- **Production is not affected.** MySQL parses the expression, and the whole
  suite passes against MySQL 8 (3 failures, all `'2'` vs `2` — see below).
  Anyone running the suite on sqlite outside Docker, which `CLAUDE.md` offers
  as an option, will hit it.
- **The expression can be much shorter.** A fold pair `X → Y` where `Y` is not
  `''` can only ever affect the match if `Y` occurs in the normalized search
  term: the term never contains `X` (it was folded too), so leaving `X` alone
  can produce neither a false positive nor a false negative. Only the thirteen
  deletions — tatweel and the harakat — are needed unconditionally. Searching
  "قهوه امريكيه" would need 23 calls rather than 35, and the query would be
  cheaper on MySQL too, on every product search.

A second thing the MySQL run turned up, unrelated to deployment: MySQL returns
`SUM()` as a string where sqlite returns an integer, so `items_quantity` and
`returned_quantity` come back from production as `"2"` while the tests assert
`2`. A client parsing those as numbers is relying on something the API does not
promise. Both of these want a change of their own.

## What is still open

- SSH signs in with a password. A key on the deploy user, with
  `PasswordAuthentication` off in sshd, is the biggest hardening step left on a
  box with a public IP and eight sites on it.
- No staging environment. `main` goes straight to the shop.
- One replica of everything, on one node. A deploy is a short gap in service,
  not a rolling one, and losing the machine loses the site.
- The RAM is tight. If this stack and the neighbours start fighting, the answer
  is a bigger box, not smaller limits.
