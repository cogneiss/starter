#!/bin/sh
#
# Prepare a cold agent VM (PHP, composer and bun on PATH; no Herd, no Postgres)
# so the suite can run. Lockfile installs only; safe to rerun.
#
# ponytail: .env keeps the Postgres defaults; SQLite comes from `composer test:sqlite`.

set -eu

cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env

composer install --no-interaction --prefer-dist

grep -q '^APP_KEY=.' .env || php artisan key:generate --no-interaction

bun install --frozen-lockfile
bun run build

if [ -n "${DB_CONNECTION:-}" ]; then
    echo "agent-setup: done. Run the tests with: vendor/bin/pest --compact"
else
    echo "agent-setup: done. Run the tests with: composer test:sqlite"
fi
