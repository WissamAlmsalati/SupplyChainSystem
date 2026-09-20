#!/usr/bin/env bash
# Roll the production stack over to a release.
#
# Run on the server, from the checkout root, by .github/workflows/deploy.yml —
# which has already moved the checkout to the release's commit, so this file
# and docker-compose.prod.yml are the ones the release ships with.
#
#   IMAGE_TAG=sha-1a2b3c4d5e6f ./scripts/deploy.sh
#
# To roll back by hand, run it again with an older tag; every tag CI ever
# pushed is still in GHCR. `docker compose ps` names the running one.
set -euo pipefail

cd "$(dirname "$0")/.."

: "${IMAGE_TAG:?IMAGE_TAG is required, e.g. IMAGE_TAG=sha-1a2b3c4d5e6f}"

COMPOSE=(docker compose -f docker-compose.prod.yml)

if [ ! -f .env ]; then
    echo "No .env here. Copy .env.example and fill it in (scripts/provision-server.sh does this)." >&2
    exit 1
fi

if [ ! -f backend/.env ]; then
    echo "No backend/.env here. Laravel needs APP_KEY; see docs/deployment.md." >&2
    exit 1
fi

# Pin the tag in .env so a reboot, or a bare `docker compose up -d`, brings
# back this release rather than whatever `latest` has since become.
if grep -q '^IMAGE_TAG=' .env; then
    sed -i "s|^IMAGE_TAG=.*|IMAGE_TAG=${IMAGE_TAG}|" .env
else
    printf 'IMAGE_TAG=%s\n' "$IMAGE_TAG" >> .env
fi

echo "==> Pulling ${IMAGE_TAG}"
"${COMPOSE[@]}" pull --quiet

echo "==> Starting"
"${COMPOSE[@]}" up -d --remove-orphans

# The app container migrates on start (--force --isolated); worker, scheduler
# and reverb deliberately leave migrations alone, so two of them cannot race
# over a CREATE TABLE. Wait for it rather than reporting success while the
# schema is still moving.
echo "==> Waiting for migrations"
for _ in $(seq 1 60); do
    if "${COMPOSE[@]}" exec -T app php artisan migrate:status >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

if ! "${COMPOSE[@]}" exec -T app php artisan migrate:status | tail -n 20; then
    echo "The app container never became ready. Logs:" >&2
    "${COMPOSE[@]}" logs --tail 50 app >&2
    exit 1
fi

# Keep the last few releases pullable from disk, drop the rest. `--filter
# until` rather than a bare prune, so an image pulled minutes ago for a
# rollback is not thrown away.
echo "==> Tidying old images"
docker image prune -af --filter "until=168h" >/dev/null || true

echo "==> Running ${IMAGE_TAG}"
"${COMPOSE[@]}" ps
