#!/usr/bin/env bash
#
# Prove .claude/hooks/require-contract.sh in both directions by piping hook JSON
# into it. Every case runs against a throwaway project directory
# (CLAUDE_PROJECT_DIR), so the real .work/ is never touched. Prints one
# "PASS <id>" or "FAIL <id>" line per case and exits 0 only if all pass.
#
# Usage: bin/prove-contract-hook.sh

set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
hook="$root/.claude/hooks/require-contract.sh"
before_work="$(find "$root/.work" -type f 2>/dev/null | sort | xargs shasum 2>/dev/null)"
failures=0
container=""
out=""
rc=0

pass() { echo "PASS $1"; }
fail() { echo "FAIL $1 ${2:-}"; failures=$((failures + 1)); }
check() { if [ "$2" = ok ]; then pass "$1"; else fail "$1" "${3:-}"; fi; }

# A fresh project directory inside its own container, so writes outside the
# project are visible too.
fresh() {
    container="$(mktemp -d)"
    proj="$container/proj"
    mkdir -p "$proj/.work"
}

# call <session_id> <tool> <path>
call() {
    local json
    json="$(printf '{"session_id":"%s","tool_name":"%s","tool_input":{"file_path":"%s"}}' "$1" "$2" "$3")"
    out="$(printf '%s' "$json" | CLAUDE_PROJECT_DIR="$proj" "$hook" 2>&1)"
    rc=$?
}

is_allow() { [ "$rc" -eq 0 ] && [ -z "$out" ]; }

is_deny() {
    [ "$rc" -eq 0 ] && printf '%s' "$out" | php -r '
        $v = json_decode(stream_get_contents(STDIN), true);
        $o = $v["hookSpecificOutput"] ?? [];
        exit(($o["hookEventName"] ?? "") === "PreToolUse" && ($o["permissionDecision"] ?? "") === "deny" && ($o["permissionDecisionReason"] ?? "") !== "" ? 0 : 1);'
}

list_files() { (cd "$1" && find . | sort); }

valid_list='{"items":[{"id":"c1","verified_by":"vendor/bin/pest tests/Feature/X.php"}]}'

# h-first, h-second-denied
fresh
call s1 Edit app/A.php
is_allow && check h-first ok || check h-first bad "rc=$rc out=$out"
call s1 Edit app/B.php
if is_deny && printf '%s' "$out" | grep -q '\.ai/skills/deepen/SKILL.md' && printf '%s' "$out" | grep -q '\.ai/skills/contract/SKILL.md'; then
    check h-second-denied-names-skills ok
else
    check h-second-denied-names-skills bad "rc=$rc out=$out"
fi

# h-same (relative, absolute-in-project, ./ forms)
fresh
call s1 Edit app/A.php
call s1 Edit app/A.php; r1=$?; a1=0; is_allow && a1=1
call s1 Edit "$proj/app/A.php"; a2=0; is_allow && a2=1
call s1 Edit ./app/A.php; a3=0; is_allow && a3=1
[ "$a1$a2$a3" = 111 ] && check h-same ok || check h-same bad "$a1$a2$a3"

# h-write, h-multiedit
for tool in Write MultiEdit; do
    fresh
    call s1 "$tool" app/A.php
    call s1 "$tool" app/B.php
    id="h-$(printf '%s' "$tool" | tr 'A-Z' 'a-z')"
    is_deny && check "$id" ok || check "$id" bad "rc=$rc out=$out"
done

# h-*-uncounted: an edit under the path never uses up the free app file
for dir in tests wiki resources; do
    fresh
    call s1 Edit "$dir/x.php"; first_ok=0; is_allow && first_ok=1
    call s1 Edit app/A.php; second_ok=0; is_allow && second_ok=1
    call s1 Edit app/B.php; third_denied=0; is_deny && third_denied=1
    [ "$first_ok$second_ok$third_denied" = 111 ] && check "h-$dir-uncounted" ok || check "h-$dir-uncounted" bad "$first_ok$second_ok$third_denied"
done

# h-traversal
fresh
call s1 Edit app/A.php
call s1 Edit app/../tests/x.php; t1=0; is_allow && t1=1
call s1 Edit app/B.php; t2=0; is_deny && t2=1
[ "$t1$t2" = 11 ] && check h-traversal ok || check h-traversal bad "$t1$t2"

# h-bash
fresh
call s1 Bash ""
json='{"session_id":"s1","tool_name":"Bash","tool_input":{"command":"echo > app/x.php"}}'
out="$(printf '%s' "$json" | CLAUDE_PROJECT_DIR="$proj" "$hook" 2>&1)"; rc=$?
b1=0; is_allow && b1=1
call s1 Edit app/A.php; b2=0; is_allow && b2=1
call s1 Edit app/B.php; b3=0; is_deny && b3=1
[ "$b1$b2$b3" = 111 ] && check h-bash ok || check h-bash bad "$b1$b2$b3"

# h-valid-list, h-denied-not-recorded
fresh
call s1 Edit app/A.php
call s1 Edit app/B.php; d1=0; is_deny && d1=1
printf '%s' "$valid_list" > "$proj/.work/feature_list.json"
call s1 Edit app/B.php; d2=0; is_allow && d2=1
call s1 Edit app/C.php; d3=0; is_allow && d3=1
[ "$d1$d2$d3" = 111 ] && check h-valid-list ok || check h-valid-list bad "$d1$d2$d3"
[ "$d1$d2" = 11 ] && check h-denied-not-recorded ok || check h-denied-not-recorded bad "$d1$d2"

# h-bad-list-*: each of these must still deny the second file
bad_case() { # id json ("MISSING" = no file)
    fresh
    if [ "$2" != MISSING ]; then printf '%s' "$2" > "$proj/.work/feature_list.json"; fi
    call s1 Edit app/A.php
    call s1 Edit app/B.php
    is_deny && check "h-bad-list-$1" ok || check "h-bad-list-$1" bad "rc=$rc out=$out"
}
bad_case items-empty '{"items":[]}'
bad_case items-missing '{"contract":"x"}'
bad_case no-verified-by '{"items":[{"id":"c1"}]}'
bad_case verified-empty '{"items":[{"verified_by":""}]}'
bad_case verified-blank '{"items":[{"verified_by":"  "}]}'
bad_case verified-null '{"items":[{"verified_by":null}]}'
bad_case verified-array '{"items":[{"verified_by":[]}]}'
bad_case verified-number '{"items":[{"verified_by":5}]}'
bad_case verified-bool '{"items":[{"verified_by":true}]}'
bad_case second-item-bad '{"items":[{"verified_by":"ok"},{"id":"c2"}]}'
bad_case invalid-json '{"items":['
bad_case top-level-array '[{"verified_by":"x"}]'
bad_case file-missing MISSING

# h-sessions
fresh
call s1 Edit app/A.php
call s2 Edit app/B.php; is_allow && check h-sessions ok || check h-sessions bad "rc=$rc out=$out"

# h-session-sanitised, h-state-confined
fresh
before="$(list_files "$container" | grep -v '^\./proj/\.work/sessions' )"
call s1 Edit app/A.php
call "../../x" Edit app/A.php
call "a/b" Edit app/A.php
after="$(list_files "$container" | grep -v '^\./proj/\.work/sessions')"
[ "$before" = "$after" ] && check h-session-sanitised ok || check h-session-sanitised bad "$(printf '%s\n%s' "$before" "$after" | sort | uniq -u | head -3)"
if [ "$(list_files "$container" | grep -v '^\./proj/\.work/sessions' | grep -c .)" = "$(printf '%s\n' "$before" | grep -c .)" ]; then
    check h-state-confined ok
else
    check h-state-confined bad
fi

# h-bad-input
fresh
rc_all=0
for input in '' 'not json' '{"tool_name":"Edit","tool_input":{"file_path":"app/A.php"}}'; do
    out="$(printf '%s' "$input" | CLAUDE_PROJECT_DIR="$proj" "$hook" 2>&1)"; rc=$?
    { [ "$rc" -eq 0 ] && ! printf '%s' "$out" | grep -qiE 'Warning|Fatal|Stack trace|Parse error'; } || rc_all=1
done
[ "$rc_all" -eq 0 ] && check h-bad-input ok || check h-bad-input bad "out=$out"

# h-cwd
fresh
json='{"session_id":"s1","tool_name":"Edit","tool_input":{"file_path":"app/A.php"}}'
(cd / && printf '%s' "$json" | CLAUDE_PROJECT_DIR="$proj" "$hook" >/dev/null 2>&1)
(cd / && printf '%s' '{"session_id":"s1","tool_name":"Edit","tool_input":{"file_path":"app/B.php"}}' | CLAUDE_PROJECT_DIR="$proj" "$hook") | grep -q deny \
    && check h-cwd ok || check h-cwd bad

# h-no-php, h-unwritable
fresh
if PATH=/usr/bin:/bin command -v php >/dev/null 2>&1; then
    fail h-no-php "php is on the minimal PATH; cannot construct the case"
else
    out="$(printf '%s' "$json" | PATH=/usr/bin:/bin CLAUDE_PROJECT_DIR="$proj" /bin/sh "$hook" 2>&1)"; rc=$?
    [ "$rc" -eq 0 ] && [ -z "$out" ] && check h-no-php ok || check h-no-php bad "rc=$rc out=$out"
fi
fresh
mkdir -p "$proj/.work/sessions"; chmod 555 "$proj/.work/sessions"
call s1 Edit app/A.php; u1=0; is_allow && u1=1
call s1 Edit app/B.php; u2=0; is_allow && u2=1
chmod 755 "$proj/.work/sessions"
[ "$u1$u2" = 11 ] && check h-unwritable ok || check h-unwritable bad "$u1$u2 out=$out"

# h-bash-guard: with both hooks run in order, a hooksPath change is still denied
fresh
json='{"session_id":"s1","tool_name":"Bash","tool_input":{"command":"git config core.hooksPath /tmp/x"}}'
g1="$(printf '%s' "$json" | CLAUDE_PROJECT_DIR="$proj" "$root/.claude/hooks/guard-gates.sh")"
g2="$(printf '%s' "$json" | CLAUDE_PROJECT_DIR="$proj" "$hook")"
printf '%s' "$g1" | grep -q '"permissionDecision":"deny"' && [ -z "$g2" ] && check h-bash-guard ok || check h-bash-guard bad "g1=$g1 g2=$g2"

# h-guards-intact: the two existing guards answer exactly as they do at HEAD
tmpguards="$(mktemp -d)"
git -C "$root" show HEAD:.claude/hooks/guard-gates.sh > "$tmpguards/guard-gates.sh"
git -C "$root" show HEAD:.claude/hooks/guard-generated.sh > "$tmpguards/guard-generated.sh"
chmod +x "$tmpguards"/*.sh
intact=1
inputs=(
    'guard-gates|{"tool_name":"Bash","tool_input":{"command":"git config core.hooksPath x"}}'
    'guard-gates|{"tool_name":"Edit","tool_input":{"file_path":"phpstan.neon"}}'
    'guard-generated|{"tool_name":"Edit","tool_input":{"file_path":"AGENTS.md"}}'
)
for entry in "${inputs[@]}"; do
    name="${entry%%|*}"; body="${entry#*|}"
    now="$(printf '%s' "$body" | CLAUDE_PROJECT_DIR="$root" "$root/.claude/hooks/$name.sh" 2>&1)"
    was="$(printf '%s' "$body" | CLAUDE_PROJECT_DIR="$root" "$tmpguards/$name.sh" 2>&1)"
    [ "$now" = "$was" ] && [ -n "$now" ] || intact=0
done
rm -rf "$tmpguards"
[ "$intact" -eq 1 ] && check h-guards-intact ok || check h-guards-intact bad

# h-fast: median of 5 under 300 ms
fresh
times=()
for _ in 1 2 3 4 5; do
    t="$(P="$proj" H="$hook" perl -MTime::HiRes=time -e '
        my $s = time;
        system(q{printf %s "{\"session_id\":\"f\",\"tool_name\":\"Edit\",\"tool_input\":{\"file_path\":\"app/A.php\"}}" | CLAUDE_PROJECT_DIR="$P" "$H" >/dev/null 2>&1});
        printf "%d\n", (time - $s) * 1000;')"
    times+=("$t")
done
median="$(printf '%s\n' "${times[@]}" | sort -n | sed -n 3p)"
[ -n "$median" ] && [ "$median" -lt 300 ] && check h-fast ok || check h-fast bad "median=${median}ms"

# h-wired
php -r '
    $s = json_decode(file_get_contents($argv[1]), true);
    $want = "\"\$CLAUDE_PROJECT_DIR\"/.claude/hooks/require-contract.sh";
    $hits = 0; $group = null;
    foreach ($s["hooks"]["PreToolUse"] as $g) {
        foreach ($g["hooks"] as $h) {
            if (($h["command"] ?? "") === $want) { $hits++; $group = $g; }
        }
    }
    $cmds = array_column($group["hooks"] ?? [], "command");
    $ok = $hits === 1 && ($group["matcher"] ?? "") === "Edit|Write|MultiEdit|Bash"
        && in_array("\"\$CLAUDE_PROJECT_DIR\"/.claude/hooks/guard-gates.sh", $cmds, true)
        && in_array("\"\$CLAUDE_PROJECT_DIR\"/.claude/hooks/guard-generated.sh", $cmds, true);
    exit($ok ? 0 : 1);' -- "$root/.claude/settings.json" && [ -x "$hook" ] && check h-wired ok || check h-wired bad

after_work="$(find "$root/.work" -type f 2>/dev/null | sort | xargs shasum 2>/dev/null)"
[ "$before_work" = "$after_work" ] && check h-real-work-untouched ok || check h-real-work-untouched bad
exit "$((failures > 0 ? 1 : 0))"
