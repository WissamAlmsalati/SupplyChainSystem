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
# Where the app keeps uploaded files, relative to this script's repository.
UPLOADS_DIR="${UPLOADS_DIR:-$(cd "$(dirname "$0")/.." && pwd)/backend/storage/app/public}"
# In production there is no such directory: the release is an image and uploads
# live in a Docker volume. Naming a container that mounts it tars them from the
# inside, which needs no root on the host and no knowledge of where Docker
# keeps its volumes.
UPLOADS_CONTAINER="${UPLOADS_CONTAINER:-}"

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

# Uploaded files: bank-transfer receipts and product images. They are the
# evidence behind wallet top-ups, and a database dump alone cannot bring them back.
files=""
if [ -n "$UPLOADS_CONTAINER" ] || [ -d "$UPLOADS_DIR" ]; then
  files="$BACKUP_DIR/files_$stamp.tar.gz"
  if [ -n "$UPLOADS_CONTAINER" ]; then
    docker exec "$UPLOADS_CONTAINER" tar -czf - -C /var/www/storage/app/public . > "$files.partial" 2>/dev/null
  else
    tar -czf "$files.partial" -C "$UPLOADS_DIR" . 2>/dev/null
  fi
  # An empty archive is 45 bytes or so; anything smaller means the tar never ran.
  if [ -s "$files.partial" ] && tar -tzf "$files.partial" >/dev/null 2>&1; then
    mv "$files.partial" "$files"; chmod 600 "$files"
    echo "$(date '+%F %T') ok $files ($(du -h "$files" | cut -f1))"
  else
    rm -f "$files.partial"; files=""
    echo "$(date '+%F %T') UPLOADS BACKUP FAILED" >&2
  fi
else
  echo "$(date '+%F %T') UPLOADS BACKUP SKIPPED: no $UPLOADS_DIR and no UPLOADS_CONTAINER" >&2
fi

if [ -n "$OFFSITE" ]; then
  for f in "$file" $files; do
    sh -c "$OFFSITE" backup "$f" && echo "$(date '+%F %T') shipped offsite: $f" || echo "$(date '+%F %T') OFFSITE COPY FAILED: $f" >&2
  done
fi

find "$BACKUP_DIR" -name 'db_*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name 'files_*.tar.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name '*.partial' -mmin +120 -delete

# Restore the files, in development:
#   tar -xzf files_2026-09-19_0315.tar.gz -C backend/storage/app/public
# and in production, back into the volume through the container:
#   gunzip -c files_2026-09-19_0315.tar.gz | docker exec -i cafe_supply_chain_app tar -xf - -C /var/www/storage/app/public
# Restore the database:
#   gunzip -c db_2026-09-19_0315.sql.gz | docker exec -i cafe_supply_chain_db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
