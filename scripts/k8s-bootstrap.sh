#!/usr/bin/env bash
# One-time setup of the cafe platform's namespace and secrets on the cluster.
#
# Run on the server, once, before the first deploy:
#   ./scripts/k8s-bootstrap.sh
#
# It is idempotent and deliberately refuses to rewrite a secret that exists:
# APP_KEY is what every encrypted column and signed URL was written with, and
# replacing it makes them unreadable. Keep a copy of the secret with the
# database backups — a dump without the key is not a restore.
#
# This cluster also runs octobits.ly, luxresale and six more sites. Everything
# here is inside the cafe-supply-chain namespace; nothing touches theirs.
set -euo pipefail

NS="${NS:-cafe-supply-chain}"
KUBECTL="${KUBECTL:-kubectl}"

if ! $KUBECTL get nodes >/dev/null 2>&1; then
    echo "kubectl cannot reach the cluster. Try: KUBECTL='sudo kubectl' $0" >&2
    exit 1
fi

echo "==> Namespace"
$KUBECTL create namespace "$NS" --dry-run=client -o yaml | $KUBECTL apply -f -

if $KUBECTL -n "$NS" get secret cafe-secrets >/dev/null 2>&1; then
    echo "==> cafe-secrets already exists — left exactly as it is."
    echo "    To see what is in it (careful, this prints them):"
    echo "      kubectl -n $NS get secret cafe-secrets -o go-template='{{range \$k,\$v := .data}}{{\$k}}={{\$v|base64decode}}{{\"\\n\"}}{{end}}'"
    exit 0
fi

echo "==> Generating secrets"
rand() { openssl rand -hex 24; }

$KUBECTL -n "$NS" create secret generic cafe-secrets \
    --from-literal=APP_KEY="base64:$(openssl rand -base64 32)" \
    --from-literal=DB_PASSWORD="$(rand)" \
    --from-literal=DB_ROOT_PASSWORD="$(rand)" \
    --from-literal=REVERB_APP_SECRET="$(rand)" \
    --from-literal=MAIL_PASSWORD=""

cat <<NEXT

Done. cafe-secrets is created and will not be touched again by any deploy.

Back it up now, before anything is written with it:

  kubectl -n $NS get secret cafe-secrets -o yaml > ~/cafe-secrets-backup.yaml
  chmod 600 ~/cafe-secrets-backup.yaml

Still to do by hand, in the ConfigMap rather than here:
  MAIL_* — it ships as MAIL_MAILER=log, which writes password-reset and
  registration OTPs to a log file instead of sending them. Until real SMTP is
  set, nobody outside the office can register or reset a password.
NEXT
