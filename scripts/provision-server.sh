#!/usr/bin/env bash
# One-time setup of a fresh Ubuntu/Debian VPS for cafe.wissam.ly.
#
# Run as root on the new machine, before anything else:
#
#   curl -fsSL https://raw.githubusercontent.com/WissamAlmsalati/SupplyChainSystem/main/scripts/provision-server.sh \
#     | sudo DEPLOY_PUBKEY="ssh-ed25519 AAAA... deploy@github" bash
#
# It installs Docker, locks the firewall down to ssh/http/https, creates the
# `deploy` user the GitHub Actions workflow signs in as, clones the repository
# to /opt/cafe-supply-chain, and writes both env files with fresh secrets.
#
# It does NOT issue the certificate or start the stack; those are
# scripts/init-letsencrypt.sh and the deploy workflow, in that order.
# Running it twice is safe: it never overwrites an env file that exists.
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/WissamAlmsalati/SupplyChainSystem.git}"
APP_DIR="${APP_DIR:-/opt/cafe-supply-chain}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DOMAIN="${DOMAIN:-cafe.wissam.ly}"
LETSENCRYPT_EMAIL="${LETSENCRYPT_EMAIL:-}"

[ "$(id -u)" -eq 0 ] || { echo "Run this as root." >&2; exit 1; }

echo "==> Base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl git ufw openssl

echo "==> Docker"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/"$(. /etc/os-release && echo "$ID")"/gpg \
        -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/$(. /etc/os-release && echo "$ID") \
$(. /etc/os-release && echo "${VERSION_CODENAME}") stable" > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io \
        docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

echo "==> Firewall"
# MySQL (3307) and phpMyAdmin (8080) are published to 127.0.0.1 only by the
# compose file, so they are unreachable from outside whatever ufw says. This
# is the second lock on the same door, not the only one.
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> Deploy user"
if ! id -u "$DEPLOY_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi
# Membership in the docker group is effectively root on this machine; that is
# what lets the deploy run without sudo.
usermod -aG docker "$DEPLOY_USER"

# root's home is /root, not /home/root.
DEPLOY_HOME=$(getent passwd "$DEPLOY_USER" | cut -d: -f6)

# Optional: the workflow currently signs in with the PASSWORD secret. Adding a
# key here is what would let you turn PasswordAuthentication off in sshd, which
# is the single biggest thing standing between a public IP and a brute-force
# break-in.
if [ -n "${DEPLOY_PUBKEY:-}" ]; then
    install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$DEPLOY_HOME/.ssh"
    touch "$DEPLOY_HOME/.ssh/authorized_keys"
    grep -qxF "$DEPLOY_PUBKEY" "$DEPLOY_HOME/.ssh/authorized_keys" \
        || echo "$DEPLOY_PUBKEY" >> "$DEPLOY_HOME/.ssh/authorized_keys"
    chmod 600 "$DEPLOY_HOME/.ssh/authorized_keys"
    chown -R "$DEPLOY_USER:$DEPLOY_USER" "$DEPLOY_HOME/.ssh"
fi

echo "==> Checkout at $APP_DIR"
if [ ! -d "$APP_DIR/.git" ]; then
    git clone "$REPO_URL" "$APP_DIR"
fi
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$APP_DIR"
# The workflow moves the checkout to a detached commit on every deploy, which
# git refuses across owners.
git config --system --add safe.directory "$APP_DIR"

echo "==> Secrets"
rand() { openssl rand -hex 24; }

if [ ! -f "$APP_DIR/.env" ]; then
    cat > "$APP_DIR/.env" <<ENV
# Read by docker compose. Not in git.
DOMAIN=${DOMAIN}
LETSENCRYPT_EMAIL=${LETSENCRYPT_EMAIL}

# Set by scripts/deploy.sh on every release; 'latest' until the first one.
IMAGE_TAG=latest

DB_DATABASE=cafe_supply_chain
DB_USERNAME=cafe_user
DB_PASSWORD=$(rand)
DB_ROOT_PASSWORD=$(rand)
ENV
    chmod 600 "$APP_DIR/.env"
    echo "    wrote $APP_DIR/.env with fresh database passwords"
else
    echo "    $APP_DIR/.env exists, left alone"
fi

if [ ! -f "$APP_DIR/backend/.env" ]; then
    # APP_KEY is what Laravel encrypts with. Generated once and never changed:
    # replacing it makes every already-encrypted column unreadable.
    cp "$APP_DIR/backend/.env.production.example" "$APP_DIR/backend/.env"
    sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" "$APP_DIR/backend/.env"
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "$APP_DIR/backend/.env"
    sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(rand)|" "$APP_DIR/backend/.env"
    chmod 600 "$APP_DIR/backend/.env"
    echo "    wrote $APP_DIR/backend/.env — fill in mail and payment-gateway settings by hand"
else
    echo "    $APP_DIR/backend/.env exists, left alone"
fi

chown "$DEPLOY_USER:$DEPLOY_USER" "$APP_DIR/.env" "$APP_DIR/backend/.env"

echo "==> Nightly database backup"
# backup-db.sh defaults to /var/backups, which the deploy user cannot create.
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" /var/backups/cafe-supply-chain
# UPLOADS_CONTAINER: in production the receipts and product images are in a
# Docker volume, not in the checkout, so the backup reads them from inside the
# app container. Without it the file half of the backup is silently empty.
cron_line="30 2 * * * cd $APP_DIR && UPLOADS_CONTAINER=cafe_supply_chain_app ./scripts/backup-db.sh >> \$HOME/cafe-backup.log 2>&1"
( crontab -u "$DEPLOY_USER" -l 2>/dev/null | grep -vF 'backup-db.sh'; echo "$cron_line" ) \
    | crontab -u "$DEPLOY_USER" -

cat <<NEXT

Done. The machine is ready but serving nothing yet.

Next, in order:
  1. Point DNS at this server:   A  ${DOMAIN}  ->  $(curl -fsS -4 ifconfig.me 2>/dev/null || echo '<this machine>')
     Wait for it:                dig +short ${DOMAIN}
  2. Add the GitHub secrets listed in docs/deployment.md.
  3. Issue the certificate:      sudo -u ${DEPLOY_USER} ${APP_DIR}/scripts/init-letsencrypt.sh
  4. Push to main, or run the Deploy workflow by hand.

Collect the host key for the DEPLOY_KNOWN_HOSTS secret with:
  ssh-keyscan -H ${DOMAIN}
NEXT
