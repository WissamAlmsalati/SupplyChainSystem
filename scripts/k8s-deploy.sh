#!/usr/bin/env bash
# Roll the cafe platform over to a release on the Kubernetes cluster.
#
# Run on the server by .github/workflows/deploy.yml, which has already moved
# the checkout to the release's commit:
#
#   IMAGE_TAG=sha-1a2b3c4d5e6f ./scripts/k8s-deploy.sh
#
# To roll back by hand, run it again with an older tag; every tag CI pushed is
# still in GHCR. `kubectl -n cafe-supply-chain get deploy -o wide` names the
# running one.
#
# The cluster also runs octobits.ly, luxresale and six more. Everything below
# is scoped to the cafe-supply-chain namespace, apart from three
# PersistentVolumes named for this project.
set -euo pipefail

cd "$(dirname "$0")/.."

: "${IMAGE_TAG:?IMAGE_TAG is required, e.g. IMAGE_TAG=sha-1a2b3c4d5e6f}"
NS="${NS:-cafe-supply-chain}"

KUBECTL="${KUBECTL:-}"
if [ -z "$KUBECTL" ]; then
    for k in "kubectl" "sudo kubectl" "sudo k3s kubectl"; do
        if $k get nodes >/dev/null 2>&1; then KUBECTL="$k"; break; fi
    done
fi
[ -n "$KUBECTL" ] || { echo "No working kubectl on this machine." >&2; exit 1; }

if ! $KUBECTL -n "$NS" get secret cafe-secrets >/dev/null 2>&1; then
    echo "cafe-secrets is missing. Run ./scripts/k8s-bootstrap.sh once first:" >&2
    echo "  it generates APP_KEY and the database passwords, and never rewrites them." >&2
    exit 1
fi

# The packages are private until somebody makes them public, so the cluster
# needs credentials to pull. This is the workflow's own GITHUB_TOKEN, refreshed
# on every deploy and dead when the job ends — which is enough, because the
# manifests pin an immutable sha- tag, so the kubelet's default IfNotPresent
# policy never re-pulls an image it already has.
if [ -n "${REGISTRY_TOKEN:-}" ]; then
    echo "==> Registry credentials"
    $KUBECTL -n "$NS" create secret docker-registry ghcr \
        --docker-server=ghcr.io \
        --docker-username="${REGISTRY_USER:?REGISTRY_USER is required with REGISTRY_TOKEN}" \
        --docker-password="$REGISTRY_TOKEN" \
        --dry-run=client -o yaml | $KUBECTL apply -f -
    # On the namespace's default service account, so every pod picks it up
    # without each manifest having to say so.
    $KUBECTL -n "$NS" patch serviceaccount default \
        -p '{"imagePullSecrets":[{"name":"ghcr"}]}' >/dev/null
fi

echo "==> Applying manifests at ${IMAGE_TAG}"
# The tag is substituted rather than set afterwards with `kubectl set image`,
# so init containers are pinned too and the cluster never holds a spec that
# says :latest.
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
for f in k8s/*.yaml; do
    sed "s|__IMAGE_TAG__|${IMAGE_TAG}|g" "$f" > "$tmp/$(basename "$f")"
done
$KUBECTL apply -f "$tmp/"

report() {
    echo "--- pods ---";   $KUBECTL -n "$NS" get pods -o wide || true
    echo "--- events ---"; $KUBECTL -n "$NS" get events --sort-by=.lastTimestamp | tail -25 || true
}

# MySQL first: on a first boot it initialises its data directory, and the app's
# entrypoint only waits sixty seconds for it before giving up and crash-looping.
echo "==> Waiting for the database"
if ! $KUBECTL -n "$NS" rollout status deploy/db --timeout=300s; then
    report; exit 1
fi

echo "==> Waiting for the rest"
for d in redis app worker scheduler reverb backend-nginx frontend; do
    if ! $KUBECTL -n "$NS" rollout status "deploy/$d" --timeout=300s; then
        echo "!! $d did not come up"
        report
        echo "--- $d logs ---"
        $KUBECTL -n "$NS" logs "deploy/$d" --tail=60 --all-containers || true
        exit 1
    fi
done

# cert-manager answers the HTTP-01 challenge through the ingress it just got,
# which takes a minute or so the first time. A failure here leaves the site
# serving ingress-nginx's self-signed default, so it is worth saying plainly.
echo "==> Certificate"
if $KUBECTL -n "$NS" wait --for=condition=Ready certificate/cafe-wissam-ly-tls --timeout=180s 2>/dev/null; then
    echo "TLS is ready."
else
    echo "The certificate is not Ready yet. The site will answer with the"
    echo "ingress controller's own certificate until it is. Check with:"
    echo "  kubectl -n $NS describe certificate cafe-wissam-ly-tls"
    echo "  kubectl -n $NS get challenges"
    $KUBECTL -n "$NS" get certificate,order,challenge 2>/dev/null || true
fi

echo "==> Running ${IMAGE_TAG}"
$KUBECTL -n "$NS" get deploy -o wide
