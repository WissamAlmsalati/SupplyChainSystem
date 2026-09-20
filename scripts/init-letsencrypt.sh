#!/usr/bin/env bash
# Issue the first Let's Encrypt certificate for the site. One time, on the
# server, after DNS for $DOMAIN points at this machine and before the first
# deploy — the edge nginx will not start without a certificate to load, and
# the deploy workflow's health check calls the site over https.
#
#   cd /opt/cafe-supply-chain && ./scripts/init-letsencrypt.sh
#   STAGING=1 ./scripts/init-letsencrypt.sh    # rehearsal, untrusted cert
#
# Let's Encrypt allows five failed attempts per hostname per hour, so rehearse
# against staging first if anything about the DNS or the firewall is uncertain.
# Renewal afterwards is the certbot container's job; this script is not a cron.
set -euo pipefail

cd "$(dirname "$0")/.."

[ -f .env ] || { echo "No .env here; see docs/deployment.md." >&2; exit 1; }
# shellcheck disable=SC1091
set -a; . ./.env; set +a

: "${DOMAIN:?set DOMAIN in .env}"
: "${LETSENCRYPT_EMAIL:?set LETSENCRYPT_EMAIL in .env — it is where expiry warnings go}"

COMPOSE=(docker compose -f docker-compose.prod.yml)
LIVE="/etc/letsencrypt/live/${DOMAIN}"

certbot_run() {
    "${COMPOSE[@]}" run --rm --entrypoint "$1" certbot
}

if "${COMPOSE[@]}" run --rm --entrypoint "sh -c 'test -s ${LIVE}/fullchain.pem'" certbot 2>/dev/null; then
    echo "A certificate for ${DOMAIN} already exists. Renewal is the certbot container's job."
    echo "To replace it anyway, delete it first:"
    echo "  docker compose -f docker-compose.prod.yml run --rm --entrypoint \"sh -c 'rm -rf ${LIVE}'\" certbot"
    exit 0
fi

# nginx refuses to start when ssl_certificate names a file that is not there,
# and certbot's webroot challenge needs nginx already answering on port 80.
# A throwaway self-signed certificate breaks that circle.
echo "==> Placing a temporary self-signed certificate"
certbot_run "sh -c 'mkdir -p ${LIVE} && openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout ${LIVE}/privkey.pem -out ${LIVE}/fullchain.pem -subj /CN=${DOMAIN}'"

echo "==> Starting the edge so port 80 answers"
# --no-deps: only the edge is needed to serve the challenge, and the rest of
# the stack may not have a release to run yet.
"${COMPOSE[@]}" up -d --no-deps frontend
sleep 3

echo "==> Discarding the temporary certificate"
certbot_run "sh -c 'rm -rf ${LIVE} /etc/letsencrypt/archive/${DOMAIN} /etc/letsencrypt/renewal/${DOMAIN}.conf'"

echo "==> Asking Let's Encrypt for ${DOMAIN}"
certbot_run "certbot certonly --webroot -w /var/www/certbot \
    --email ${LETSENCRYPT_EMAIL} --agree-tos --no-eff-email --non-interactive \
    ${STAGING:+--staging} \
    -d ${DOMAIN}"

echo "==> Reloading nginx onto the real certificate"
"${COMPOSE[@]}" exec frontend nginx -s reload

echo
echo "Done. Check it from another machine:"
echo "  curl -sI https://${DOMAIN}/ | head -1"
