#!/bin/bash
# NISconnect SessionStart hook — prepares the backend so tests/linters run in
# Claude Code on the web. Idempotent and non-interactive.
set -euo pipefail

# Only run in the remote (web) environment.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(pwd)}"
PGDATA="/home/user/pgdata"
PGBIN="/usr/lib/postgresql/16/bin"

# --- PostgreSQL -------------------------------------------------------------
if [ -d "$PGBIN" ]; then
  mkdir -p "$PGDATA"
  chown -R postgres:postgres "$PGDATA" 2>/dev/null || true

  if [ ! -f "$PGDATA/PG_VERSION" ]; then
    su postgres -c "$PGBIN/initdb -D $PGDATA -U postgres --auth=trust" >/tmp/nis-initdb.log 2>&1 || true
  fi

  if ! su postgres -c "$PGBIN/pg_ctl -D $PGDATA status" >/dev/null 2>&1; then
    su postgres -c "$PGBIN/pg_ctl -D $PGDATA -l /tmp/nis-pg.log -o '-p 5432' start" >/tmp/nis-pgstart.log 2>&1 || true
  fi

  # Wait for readiness (max ~15s).
  for _ in $(seq 1 15); do
    if psql -h 127.0.0.1 -U postgres -c 'SELECT 1;' >/dev/null 2>&1; then break; fi
    sleep 1
  done

  psql -h 127.0.0.1 -U postgres -tc "SELECT 1 FROM pg_database WHERE datname='nisconnect'" \
    | grep -q 1 || psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE nisconnect;" >/dev/null 2>&1 || true
  psql -h 127.0.0.1 -U postgres -tc "SELECT 1 FROM pg_database WHERE datname='nisconnect_testing'" \
    | grep -q 1 || psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE nisconnect_testing;" >/dev/null 2>&1 || true
fi

# --- Laravel backend --------------------------------------------------------
if [ -d "$PROJECT_DIR/backend" ]; then
  cd "$PROJECT_DIR/backend"

  if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist --no-progress || true
  fi

  if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force || true
  fi

  # Migrate + seed the dev database (safe to re-run).
  php artisan migrate --force --seed >/tmp/nis-migrate.log 2>&1 || true
fi

echo "NISconnect session-start hook complete."
