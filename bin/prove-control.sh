#!/usr/bin/env bash
#
# Prove that a security control is load-bearing.
#
# A test that is green proves nothing on its own — the same author writes the
# test and the criterion it satisfies. This script proves the second half: with
# the control disabled by a committed, minimal patch, the guarded test must go
# red, and it must go red because an assertion failed rather than because the
# file stopped parsing.
#
# Usage: bin/prove-control.sh <patch-name> <pest-filter>
#   e.g. bin/prove-control.sh phase5-export-scope CsvExportScope
#
# Exits 0 only when the control is proven load-bearing and the tree is restored.

set -uo pipefail

name="${1:?usage: prove-control.sh <patch-name> <pest-filter>}"
filter="${2:?usage: prove-control.sh <patch-name> <pest-filter>}"
patch="tests/Mutations/${name}.patch"

fail() {
    echo "prove-control: $1" >&2
    exit 1
}

run_guarded_test() {
    herd php artisan test --compact --filter="$filter" 2>&1
}

[ -f "$patch" ] || fail "no such patch: $patch"

# 1. The patch is minimal and reviewable: one file, at most three changed lines.
#    A patch that rewrites a file to force a failure is rejected here, not left
#    for a reader to notice.
stat="$(git apply --numstat "$patch")" || fail "$patch does not apply to this tree"
[ "$(printf '%s\n' "$stat" | wc -l)" -eq 1 ] || fail "$patch touches more than one file"
[ "$(printf '%s\n' "$stat" | awk '{print $1 + $2}')" -le 3 ] || fail "$patch changes more than 3 lines"

# 2. The tree is clean before we mutate it, and it is restored on any exit —
#    including a failure, a signal or an interrupt.
git diff --quiet || fail "working tree is dirty; refusing to mutate it"

restore() {
    git apply -R "$patch" 2>/dev/null || true
}
trap restore EXIT INT TERM

git apply "$patch" || fail "failed to apply $patch"

# 3. With the control disabled the guarded test must fail.
red_output="$(run_guarded_test)"
red_status=$?

if [ "$red_status" -eq 0 ]; then
    echo "CONTROL NOT DISABLED"
    fail "$filter still passes with $patch applied — the control it claims to guard is not load-bearing"
fi

# 4. It must have failed for the right reason. A patch that reddens a test by
#    breaking PHP proves nothing about the control.
if printf '%s\n' "$red_output" | grep -qE 'Fatal error|ParseError|Uncaught|Syntax error'; then
    printf '%s\n' "$red_output"
    fail "$filter failed on a PHP error, not an assertion — $patch breaks the code instead of disabling the control"
fi

# `--compact` reports as JSON, so a red run is either the human "FAIL" banner or
# a "result":"failed" field. Either one is a real test failure.
if ! printf '%s\n' "$red_output" | grep -qE 'FAIL|"result":"failed"'; then
    printf '%s\n' "$red_output"
    fail "$filter produced no recognisable test failure"
fi

# 5. Show the red run. This output is the evidence.
echo "--- red run: $filter with $patch applied ---"
printf '%s\n' "$red_output"
echo "--- end red run ---"

# 6. Restore (the trap does it, so the tree is clean even if step 7 fails).
restore
trap - EXIT INT TERM

# 7. The test is green again and the tree is exactly as we found it. A prove
#    script that leaves the tree mutated fails.
if ! run_guarded_test > /dev/null; then
    fail "$filter is not green again after restoring $patch"
fi

git diff --quiet || fail "working tree still mutated after restoring $patch"

echo "prove-control: $name proven load-bearing for $filter"
