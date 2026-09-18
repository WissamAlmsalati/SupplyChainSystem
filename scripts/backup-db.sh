#!/bin/sh
# Nightly database backup, meant for cron on the server:
#
#   15 3 * * * /var/www/SupplyChainSystem/scripts/backup-db.sh >> /var/log/cafe-backup.log 2>&1
#
# Dumps MySQL from inside its container (so no client or password is needed on
# the host), compresses it, checks the result is a real dump and not an error
# page, and deletes copies older than KEEP_DAYS.
#
# A backup on the same disk only protects against mistakes, not against losing
# the server. Set OFFSITE to a command that ships "$1" somewhere else, e.g.
#   OFFSITE='rclone copy "$1" remote:cafe-backups'
#   OFFSITE='scp "$1" backup@other-host:/backups/cafe/'
set -eu

CONTAINER="${CONTAINER:-cafe_supply_chain_db}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/cafe-supply-chain}"
KEEP_DAYS="${KEEP_DAYS:-14}"
OFFSITE="${OFFSITE:-}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

stamp=$(date +%F_%H%M)
file="$BACKUP_DIR/db_$stamp.sql.gz"
tmp="$file.partial"

# --single-transaction: a consistent snapshot without locking the tables the
# app is writing to. The password comes from the container's own environment.
docker exec "$CONTAINER" sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --no-tablespaces "$MYSQL_DATABASE"' 2>/dev/null | gzip -9 > "$tmp"

# A failed dump still leaves a small gzip behind; a real one ends with this line.
if ! gzip -dc "$tmp" | tail -n 1 | grep -q "Dump completed"; then
  rm -f "$tmp"
  echo "$(date '+%F %T') BACKUP FAILED: dump did not complete" >&2
  exit 1
fi

mv "$tmp" "$file"
chmod 600 "$file"
echo "$(date '+%F %T') ok $file ($(du -h "$file" | cut -f1))"

if [ -n "$OFFSITE" ]; then
  sh -c "$OFFSITE" backup "$file" && echo "$(date '+%F %T') shipped offsite" || echo "$(date '+%F %T') OFFSITE COPY FAILED" >&2
fi

find "$BACKUP_DIR" -name 'db_*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name '*.partial' -mmin +120 -delete

# Restore:
#   gunzip -c db_2026-09-19_0315.sql.gz | docker exec -i cafe_supply_chain_db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
