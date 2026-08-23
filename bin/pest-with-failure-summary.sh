#!/usr/bin/env bash
# Runs pest, and on failure reprints just the failing test names so they are not
# buried under the diff output. Pest's exit code is passed through untouched —
# without that every gate calling this script would silently always pass.
set -uo pipefail

log="$(mktemp -t pest-run)"
trap 'rm -f "$log"' EXIT

vendor/bin/pest "$@" 2>&1 | tee "$log"
status=${PIPESTATUS[0]}

if [ "$status" -ne 0 ]; then
    echo
    echo "Failed tests:"
    # Pest prints a human table by default and JSON under the agent formatter,
    # so pull failing names out of whichever one we got.
    failures=$( { grep -oE '"test":"[^"]*"' "$log" | sed 's/"test":"/  /;s/"$//'
                  grep -E '^[[:space:]]+(FAIL|⨯)' "$log"; } | sort -u )
    echo "${failures:-  (no per-test failure lines; see output above)}"
fi

exit "$status"
