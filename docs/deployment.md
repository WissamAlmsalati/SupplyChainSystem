# Deploying to cafe.wissam.ly

The whole platform is served from one hostname: the admin dashboard at `/`, the
customer app at `/customer/`, the API at `/api/v1`, the API reference at
`/docs`, and the WebSocket at `/app/local`. That is the layout the code already
assumes — `frontend-customer` is built with `VITE_BASE_PATH=/customer/`, both
clients call the relative path `/api/v1`, `echo.js` derives its WebSocket URL
from the page it is on, and the admin's service worker is scoped to `/`.
Splitting the apps across subdomains later would mean touching all four.

## The shape of it

```
GitHub push to main
  └─ ci.yml          Pint, backend tests, oxlint, both frontend builds
  └─ build           two images → ghcr.io/wissamalmsalati/supplychainsystem/{backend,frontend}
                     tagged sha-<12 chars> and latest
  └─ deploy          ssh to the server, move the checkout to this commit,
                     pull the images, docker compose up -d, check the API answers
```

A release **is** an image tag. The checkout at `/opt/cafe-supply-chain` on the
server holds only configuration — the compose file, the nginx configs and
`scripts/` — never application code. That is why a rollback is one tag and not
a `git revert`.

Two nginx containers, on purpose:

| container  | job |
| ---------- | --- |
| `frontend` | public edge. TLS, both SPAs, ACME challenge. Ports 80 and 443. |
| `nginx`    | backend proxy. FastCGI to `app`, WebSocket to `reverb`, uploaded files. Publishes no port. |

`db` (3307) and `phpmyadmin` (8080) are published to `127.0.0.1` only. From
anywhere else, tunnel:

```bash
ssh -L 3307:127.0.0.1:3307 -L 8080:127.0.0.1:8080 user@cafe.wissam.ly
```

Telescope is not proxied by the edge at all, and `TELESCOPE_ENABLED` is false in
production: its request log holds bearer tokens and customer data.

## First time

### 1. GitHub secrets

They live in the repository's **`development-env` environment**
(Settings → Environments → development-env → Environment secrets), which is why
the deploy job names that environment — secrets scoped to an environment resolve
to empty strings in a job that does not join it.

| secret | required | what |
| ------ | -------- | ---- |
| `IP` | yes | the server's address |
| `USER` | yes | the user the deploy signs in as |
| `PASSWORD` | yes | that user's password |
| `PORT` | no | ssh port, 22 if unset |
| `KNOWN_HOSTS` | no | output of `ssh-keyscan -H <ip>`; pins the server's identity |

`GITHUB_TOKEN` is provided by Actions — nothing to add. It is what pushes the
images and what the server uses to pull them.

Without `KNOWN_HOSTS` the workflow trusts whatever host key answers on the first
connection. Adding it takes one command and closes that gap:

```bash
ssh-keyscan -H <server-ip>     # paste the whole output into the secret
```

### 2. DNS

One record at the registrar for `wissam.ly`:

```
A    cafe    <server-ip>    TTL 300
```

Wait for it to be live before asking Let's Encrypt for anything — a certificate
request against a name that does not resolve yet burns one of the five attempts
per hostname per hour that Let's Encrypt allows.

```bash
dig +short cafe.wissam.ly
```

### 3. The server

On a fresh Ubuntu or Debian machine, as root:

```bash
curl -fsSL https://raw.githubusercontent.com/WissamAlmsalati/SupplyChainSystem/main/scripts/provision-server.sh \
  | sudo DEPLOY_USER=<the USER secret> LETSENCRYPT_EMAIL=you@example.com bash
```

It installs Docker, allows only 22/80/443 through ufw, clones the repository to
`/opt/cafe-supply-chain`, and writes both env files with fresh secrets. It never
overwrites an env file that already exists, so it is safe to run again.

Then finish `/opt/cafe-supply-chain/backend/.env` by hand. Two entries matter
before customers arrive:

- **`MAIL_*`** — it ships as `MAIL_MAILER=log`, which writes password-reset and
  registration OTPs to a log file instead of sending them. Until real SMTP is
  in, nobody outside the office can register or reset a password.
- **`COMPANY_*`** — the letterhead on every invoice, statement and report.

### 4. The certificate

The edge nginx will not start without a certificate to load, and certbot cannot
answer the challenge without nginx running. `init-letsencrypt.sh` breaks that
circle with a one-day self-signed certificate, then replaces it:

```bash
cd /opt/cafe-supply-chain
./scripts/init-letsencrypt.sh
```

Rehearse first with `STAGING=1 ./scripts/init-letsencrypt.sh` if anything about
the DNS or the firewall is uncertain; staging has no rate limit and issues an
untrusted certificate you then delete.

Renewal is the `certbot` container's job from here on: it wakes twice a day and
does nothing until the certificate is inside 30 days of expiry. The `frontend`
container reloads nginx every six hours so a renewed certificate is picked up
without a restart.

### 5. First deploy

Push to `main`, or run the **Deploy** workflow from the Actions tab.

## Every day

Push to `main`. CI runs, the images build, the server rolls over, and the
workflow calls `https://cafe.wissam.ly/api/v1/categories` to confirm the site is
answering before it reports success.

Nothing else is automatic on purpose: migrations run in the `app` container's
entrypoint (`--force --isolated`, and only in that container, so `worker`,
`scheduler` and `reverb` cannot race it over a `CREATE TABLE`), and seeding is
skipped outside local and development.

## Rolling back

Actions → Deploy → Run workflow → put an older tag in **image_tag**, e.g.
`sha-9529ae4a1b2c`. It skips CI and the build and goes straight to the server.
Every tag CI ever pushed is still in GHCR; `docker compose -f
docker-compose.prod.yml ps` on the server names the running one, and
`grep IMAGE_TAG /opt/cafe-supply-chain/.env` is the pinned one.

A rollback moves the images back but takes the *current* commit's compose file
and nginx config with it. If the release you are undoing changed either of
those, revert them in git as well.

**Migrations do not roll back.** A release that added a column is safe to
undo; one that dropped or renamed one is not, and needs a forward fix.

## Backups

`scripts/backup-db.sh` runs nightly at 02:30 from the deploy user's crontab,
installed by the provisioning script. It dumps MySQL from inside the container
and tars the uploaded receipts and product images out of the `storage_public`
volume — hence `UPLOADS_CONTAINER=cafe_supply_chain_app` in the cron line;
without it the file half of the backup is silently empty, because in production
there is no `backend/storage/app/public` directory on the host at all.

A backup on the same disk protects against mistakes, not against losing the
server. Set `OFFSITE` in the cron line to ship them somewhere else.

Keep a copy of `backend/.env` with them: `APP_KEY` is what every encrypted
column was written with, and a dump without it is not a restore.

## When it goes wrong

**The deploy step says a secret is empty.** The job's `environment:` name and the
environment the secrets live in have to match exactly — it is `development-env`
in both `.github/workflows/deploy.yml` and the repository settings.

**`docker pull` is denied on the server.** The packages are private until
somebody makes them public. Either leave them private (the workflow logs the
server in with the run's own `GITHUB_TOKEN`, which is what it does now) or, since
this repository is public anyway, set both packages to Public once under
the account's Packages settings and the login becomes unnecessary.

**The health check fails but the site loads in a browser.** The check calls
`/api/v1/categories`, which needs the database. `docker compose -f
docker-compose.prod.yml logs app` usually says why; the most common cause is a
migration that could not run.

**502 from the edge.** The backend container was rebuilt and nginx is holding a
dead IP — both configs re-resolve per request through a variable to prevent
exactly this, so a 502 that survives is the `app` container being down, not DNS.

**Zones or addresses answer with a sentence instead of data.** `h3-js` is
missing from the image. It is a runtime dependency of the PHP app, installed by
the `npm ci` line in the production stage of `backend/Dockerfile`.

**The certificate did not renew.** `docker compose -f docker-compose.prod.yml
logs certbot`. The usual cause is the ACME challenge location having been moved
below the HTTPS redirect in the edge config, which turns the challenge into a
301.

## What is still open

- SSH signs in with a password. Adding a key to the deploy user and turning
  `PasswordAuthentication` off in sshd is the single biggest hardening step left
  on a machine with a public IP; `scripts/provision-server.sh` takes a
  `DEPLOY_PUBKEY` for exactly that, and the workflow would need its ssh call
  swapped from `sshpass -e` to `-i`.
- No staging environment. `main` goes straight to the shop.
