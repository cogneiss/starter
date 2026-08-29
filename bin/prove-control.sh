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
# Usage: bin/prove-control.sh <patch-name> <pest-filter> [case-pattern]
#   e.g. bin/prove-control.sh phase5-export-scope CsvExportScope
#        bin/prove-control.sh phase7-peek-scope DetailDrawer '404|another organization'
#
# A filter can cover several cases. Without a case pattern this script proves
# only that *something* under the filter went red, which is a weaker claim than
# most criteria make. Pass the pattern the failing case must match and the red
# run has to name that case, not merely fail somewhere nearby.
#
# Exits 0 only when the control is proven load-bearing and the tree is restored.

set -uo pipefail

name="${1:?usage: prove-control.sh <patch-name> <pest-filter>}"
filter="${2:?usage: prove-control.sh <patch-name> <pest-filter>}"
case_pattern="${3:-}"
patch="tests/Mutations/${name}.patch"

fail() {
    echo "prove-control: $1" >&2
    exit 1
}

# The names of the cases that went red, and only those. Pest marks a failure
# with `⨯` and repeats it as `FAILED  Suite > case name`; a pass is `✓`. Read
# on stdin so the same matcher can be exercised against a recorded transcript.
match_failing_case() {
    local pattern="$1"

    # Whole lines, not `grep -Eo`: BSD grep truncates an `-Eo` match on a line
    # holding a multibyte character, so a real case name loses its tail — and
    # with it the very word the pattern looks for.
    grep -E '⨯|✗|FAILED[[:space:]]|"result":"failed"' |
        grep -qEi "$pattern"
}

# `bin/prove-control.sh --match-cases <pattern>` runs that matcher alone over a
# transcript on stdin. It exists so the matcher itself can be tested.
if [ "${1:-}" = "--match-cases" ]; then
    match_failing_case "${2:?usage: prove-control.sh --match-cases <pattern>}"
    exit $?
fi

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

# 4b. And it must have failed on the case the caller names. The pattern is
#     matched against the names of the cases that actually went red, not
#     against the run's output: a passing case's name, or a diff that happens
#     to print the number in the pattern, is not evidence that the named case
#     failed.
if [ -n "$case_pattern" ]; then
    if ! printf '%s\n' "$red_output" | match_failing_case "$case_pattern"; then
        printf '%s\n' "$red_output"
        fail "$filter went red, but no *failing* case is named /$case_pattern/ — $patch reddens something other than the control this proves"
    fi
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
