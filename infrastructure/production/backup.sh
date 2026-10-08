#!/usr/bin/env bash
# NISconnect backups for the production stack (docs/GO_LIVE.md, "Backups").
#
#   sudo ./backup.sh                    back up now: database + uploaded media
#   sudo ./backup.sh --install-cron     back up automatically every night at 02:30
#   sudo ./backup.sh --list             show existing backups
#   sudo ./backup.sh --restore FILE.dump [MEDIA.tar.gz]
#                                       restore a database dump (and optionally
#                                       the media archive taken at the same time)
#
# Writes to BACKUP_DIR (default /var/backups/nisconnect) and deletes backups
# older than BACKUP_RETENTION_DAYS (default 14). Both can be set in .env.
# Copy the backup folder OFF this server regularly (see GO_LIVE.md): a backup
# that lives only on the server is lost with the server.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"
SCRIPT="$(pwd)/backup.sh"

# Read a single KEY=value from .env without executing the file.
env_value() {
    [ -f .env ] || return 0
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//'
}

BACKUP_DIR="${BACKUP_DIR:-$(env_value BACKUP_DIR)}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/nisconnect}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-$(env_value BACKUP_RETENTION_DAYS)}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

compose() { docker compose "$@"; }

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

backup() {
    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"
    local stamp db media
    stamp="$(date +%Y%m%d-%H%M%S)"
    db="$BACKUP_DIR/nisconnect-db-$stamp.dump"
    media="$BACKUP_DIR/nisconnect-media-$stamp.tar.gz"

    log "Dumping database to $db"
    # Custom format (-Fc): compressed, restorable with pg_restore.
    compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$db.partial"
    mv "$db.partial" "$db"

    log "Archiving uploaded media to $media"
    compose exec -T app tar -czf - -C /var/www/html/storage app > "$media.partial"
    mv "$media.partial" "$media"

    chmod 600 "$db" "$media"

    log "Deleting backups older than $RETENTION_DAYS days"
    find "$BACKUP_DIR" -maxdepth 1 -type f -name 'nisconnect-*' -mtime "+$RETENTION_DAYS" -print -delete

    log "Backup finished: $(du -h "$db" | cut -f1) database, $(du -h "$media" | cut -f1) media"
}

install_cron() {
    local cron=/etc/cron.d/nisconnect-backup
    cat > "$cron" <<EOF
# NISconnect nightly backup (installed by $SCRIPT --install-cron)
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
30 2 * * * root $SCRIPT >> /var/log/nisconnect-backup.log 2>&1
EOF
    chmod 644 "$cron"
    log "Installed $cron (runs every night at 02:30; log: /var/log/nisconnect-backup.log)"
}

restore() {
    local db="${1:-}" media="${2:-}"
    [ -f "$db" ] || { echo "Usage: $0 --restore FILE.dump [MEDIA.tar.gz]" >&2; exit 1; }
    [ -z "$media" ] || [ -f "$media" ] || { echo "Media archive not found: $media" >&2; exit 1; }

    echo "This REPLACES the current database with $db."
    [ -z "$media" ] || echo "It also REPLACES uploaded media with $media."
    read -r -p "Type RESTORE to continue: " answer
    [ "$answer" = "RESTORE" ] || { echo "Cancelled."; exit 1; }

    log "Stopping the app, worker, scheduler and websocket server"
    compose stop app worker scheduler reverb

    log "Restoring database"
    compose exec -T postgres sh -c \
        'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --exit-on-error' < "$db"

    if [ -n "$media" ]; then
        log "Restoring media"
        compose run --rm --no-deps -T --user root app \
            sh -c 'rm -rf /var/www/html/storage/app && tar -xzf - -C /var/www/html/storage && chown -R www-data:www-data /var/www/html/storage/app' \
            < "$media"
    fi

    log "Starting everything again"
    compose up -d
    log "Restore finished"
}

case "${1:-}" in
    "") backup ;;
    --install-cron) install_cron ;;
    --list) ls -lh "$BACKUP_DIR" ;;
    --restore) shift; restore "$@" ;;
    -h|--help) sed -n '2,15p' "$SCRIPT" ;;
    *) echo "Unknown option: $1 (try --help)" >&2; exit 1 ;;
esac
