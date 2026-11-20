#!/usr/bin/env sh
#
# Stop hook. Before an agent reports done, run PHPStan, the tests touching
# uncommitted files and tsc. A failure exits 2, which hands the errors back to
# the agent instead of ending the turn. Skipped when nothing changed, and on
# the second stop so a failure it cannot fix never loops.

command -v php >/dev/null 2>&1 || exit 0

active="$(php -r 'echo json_encode(json_decode(stream_get_contents(STDIN), true)["stop_hook_active"] ?? false);')"
[ "$active" = "true" ] && exit 0

cd "$CLAUDE_PROJECT_DIR" || exit 0

changed="$(git status --porcelain --untracked-files=all -- '*.php' '*.ts' '*.tsx' 2>/dev/null)"
[ -n "$changed" ] || exit 0

log="$(mktemp)"
trap 'rm -f "$log"' EXIT
failed=""

# Pest gets a database of its own, as bin/recheck-todo does. Pre-commit and
# your own `pest` use `testing`, and two Pest runs on one database fail with
# duplicate-table errors. `migrate` creates testing_stop when it is missing; if
# it cannot, the run falls back to `testing`.
pest_db=testing_stop
DB_CONNECTION=pgsql DB_DATABASE="$pest_db" php artisan migrate --force >/dev/null 2>&1 || pest_db=testing

XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G --no-progress --error-format=raw >"$log.phpstan" 2>&1 || failed="$failed phpstan"
XDEBUG_MODE=off DB_DATABASE="$pest_db" vendor/bin/pest --dirty --compact >"$log.pest" 2>&1 || failed="$failed pest"
node_modules/.bin/tsc --noEmit >"$log.tsc" 2>&1 || failed="$failed tsc"

if [ -n "$failed" ]; then
    {
        echo "Checks failed before stopping:$failed. Fix them, then finish."
        for check in $failed; do
            echo "--- $check"
            tail -n 40 "$log.$check"
        done
    } >&2
    rm -f "$log".*
    exit 2
fi

rm -f "$log".*
exit 0
