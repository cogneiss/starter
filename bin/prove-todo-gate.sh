#!/usr/bin/env bash
#
# Prove the todo gates in both directions: .githooks/commit-msg (a feat needs a
# todo item), .githooks/pre-push (a status-only change keeps the stamp; the
# recheck starts detached) and bin/recheck-todo (flips and commits only status
# files, one run at a time, skips when unsafe). Each group runs in a throwaway
# clone with the working copies of the hooks overlaid, so the real index, history
# and database are never touched. Prints "PASS <id>" or "FAIL <id>" per case and
# exits 0 only if all pass.
#
# Usage: bin/prove-todo-gate.sh [commit-msg|pre-push|recheck ...]

set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
failures=0
pass() { echo "PASS $1 ${2:-}"; }
fail() { echo "FAIL $1 ${2:-}"; failures=$((failures + 1)); }
check() { if [ "$2" = ok ]; then pass "$1" "${3:-}"; else fail "$1" "${3:-}"; fi; }
now() { perl -MTime::HiRes=time -e 'printf "%.3f", time'; }
elapsed() { perl -e 'printf "%.2f", $ARGV[1] - $ARGV[0]' "$1" "$(now)"; }

base="$(mktemp -d)"
trap 'for p in "$base"/*/.git/recheck-status.lock/pid; do kill "$(cat "$p" 2>/dev/null)" 2>/dev/null; done; rm -rf "$base"' EXIT

# A clone whose hooks are this checkout's current ones, with the real hooks
# installed in .git/hooks and a single fixture plan in todo/.
clone() {
    dir="$base/$1"
    git clone -q --no-hardlinks "$root" "$dir" || exit 1
    cd "$dir" || exit 1
    git config user.name t
    git config user.email t@t
    cp "$root"/.githooks/commit-msg "$root"/.githooks/pre-push .githooks/
    cp "$root"/bin/recheck-todo "$root"/bin/recheck-status bin/
    git rm -q -r todo
    mkdir todo
    cat > todo/fix.status.json <<'JSON'
{
  "plan": "fixture",
  "phases": [
    {
      "id": 1,
      "name": "wrongly failing",
      "status": "failing",
      "command": "true"
    },
    {
      "id": 2,
      "name": "rightly passing",
      "status": "passing",
      "command": "true"
    }
  ]
}
JSON
    git add -A
    git commit -q -m "overlay"
    mkdir -p app
}
install_hook() { cp ".githooks/$1" ".git/hooks/$1"; chmod +x ".git/hooks/$1"; }
reset() { git reset -q --hard "${1:-HEAD}"; git clean -fdq; rm -rf .git/recheck-status.* .git/index.lock .git/rebase-merge; }

group_commit_msg() {
    clone cm
    install_hook commit-msg
    base_sha="$(git rev-parse HEAD)"
    try() { # message... ; leaves rc and err
        git commit -q "$@" >/dev/null 2>"$base/err"
        rc=$?
        reset "$base_sha"
    }
    stage() { mkdir -p "$(dirname "$1")"; echo "<?php // $RANDOM" > "$1"; git add "$1"; }

    stage app/Proof.php; try -m "feat: add proof"
    [ $rc -ne 0 ] && grep -q 'todo/<area>.status.json' "$base/err" && grep -q 'Todo: none' "$base/err" \
        && check m-refuse ok || check m-refuse bad "rc=$rc"

    stage resources/js/proof.tsx; try -m "feat(ui)!: add proof"
    [ $rc -ne 0 ] && check m-refuse-scope ok || check m-refuse-scope bad "rc=$rc"

    stage app/Proof.php; stage todo/fix.status.json; try -m "feat: add proof"
    [ $rc -eq 0 ] && check m-allow-todo ok || check m-allow-todo bad "rc=$rc"

    stage app/Proof.php; try -m "feat: add proof" -m "Todo: none"
    [ $rc -eq 0 ] && check m-allow-trailer ok || check m-allow-trailer bad "rc=$rc"

    ok=ok
    for subject in "fix: x" "chore: x" "docs: x" "refactor(core): x"; do
        stage app/Proof.php; try -m "$subject"; [ $rc -eq 0 ] || ok="bad $subject"
    done
    stage tests/ProofTest.php; try -m "feat: tests only"; [ $rc -eq 0 ] || ok="bad tests-only"
    stage docs/proof.md; try -m "feat: docs only"; [ $rc -eq 0 ] || ok="bad docs-only"
    check m-untouched "${ok%% *}" "$ok"

    stage app/Proof.php; git commit -q -m "feat: add proof" -m "Todo: none" >/dev/null 2>&1
    git commit -q --amend -m "feat: reworded proof" >/dev/null 2>&1; rc=$?
    [ $rc -eq 0 ] && check m-amend ok || check m-amend bad "rc=$rc"
    stage app/More.php
    git commit -q --amend -m "feat: reworded proof" >/dev/null 2>&1; rc=$?
    [ $rc -ne 0 ] && check m-amend-new-code ok || check m-amend-new-code bad "rc=$rc"
    reset "$base_sha"

    stage app/Proof.php
    printf 'feat: add proof\n' > "$base/msg"
    took="$(for _ in 1 2 3 4 5; do start="$(now)"; .githooks/commit-msg "$base/msg" >/dev/null 2>&1; elapsed "$start"; echo; done | sort -n | sed -n 3p)"
    perl -e 'exit($ARGV[0] < 0.3 ? 0 : 1)' "$took" && check m-fast ok "${took}s" || check m-fast bad "${took}s"
    reset "$base_sha"

    cd "$root" || exit 1
    git ls-files -s .githooks/commit-msg | grep -q '^100755' && check m-active ok || check m-active bad
}

group_pre_push() {
    clone pp
    mkdir -p "$base/bin" vendor/bin
    log="$base/pp.log"
    printf '#!/bin/sh\necho "composer $*" >> "%s"\n[ "${FAKE_VERIFY:-green}" = red ] && exit 1\nexit 0\n' "$log" > "$base/bin/composer"
    printf '#!/bin/sh\nexit 0\n' > vendor/bin/sloppy
    chmod +x "$base/bin/composer" vendor/bin/sloppy
    export PATH="$base/bin:$PATH" RECHECK_ISOLATE_DB=0
    zero=0000000000000000000000000000000000000000
    stamp="$(git rev-parse --absolute-git-dir)/verified-tree"
    push() { : > "$log"; echo "refs/heads/main $(git rev-parse HEAD) refs/heads/main ${1:-$zero}" | .githooks/pre-push >/dev/null 2>&1; rc=$?; }
    verifies() { grep -c '^composer verify' "$log"; }

    git rev-parse 'HEAD^{tree}' > "$stamp"
    sed -i.bak 's/wrongly failing/renamed/' todo/fix.status.json && rm -f todo/fix.status.json.bak
    sed -i.bak 's/"failing"/"passing"/' todo/fix.status.json && rm -f todo/fix.status.json.bak
    git commit -q -am "chore(todo): recheck statuses"
    push
    [ $rc -eq 0 ] && [ "$(verifies)" -eq 0 ] && ! [ -e .git/recheck-status.log ] && check p-skip-todo ok || check p-skip-todo bad "rc=$rc verifies=$(verifies)"
    check p-no-launch "$([ ! -e .git/recheck-status.lock ] && echo ok || echo bad)" "skip push"

    echo "<?php // code" > app/Changed.php
    git add -A; git commit -q -m "fix: code"
    SKIP_TODO_RECHECK=1 push
    [ $rc -eq 0 ] && [ "$(verifies)" -eq 1 ] && check p-full-code ok || check p-full-code bad "rc=$rc verifies=$(verifies)"
    # A full green push that touches no status file starts no recheck.
    prev="$(git rev-parse HEAD)"
    echo "<?php // more" >> app/Changed.php
    git commit -q -am "fix: more code"
    push "$prev"; sleep 1
    [ $rc -eq 0 ] && [ ! -e .git/recheck-status.lock ] && [ ! -e .git/recheck-status.log ] && check p-launch-scoped ok || check p-launch-scoped bad "rc=$rc"

    git rev-parse 'HEAD^{tree}' > "$stamp"
    FAKE_VERIFY=red SKIP_TODO_RECHECK= push
    git commit -q --allow-empty -m "fix: empty"; rm -f "$stamp"
    FAKE_VERIFY=red push
    [ $rc -ne 0 ] && [ ! -e .git/recheck-status.lock ] && [ ! -e .git/recheck-status.log ] && check p-no-launch-red ok || check p-no-launch-red bad "rc=$rc"

    # A green full gate starts a slow recheck and returns before it ends.
    cat > todo/fix.status.json <<'JSON'
{
  "plan": "fixture",
  "phases": [
    {
      "id": 1,
      "name": "slow",
      "status": "passing",
      "command": "sleep 20"
    }
  ]
}
JSON
    git commit -q -am "chore(todo): slow fixture"; rm -f "$stamp"
    start="$(now)"; push; took="$(elapsed "$start")"
    sleep 1
    pid="$(cat .git/recheck-status.lock/pid 2>/dev/null)"
    [ $rc -eq 0 ] && [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null && perl -e 'exit($ARGV[0] < 3 ? 0 : 1)' "$took" \
        && check p-launch-nonblocking ok "${took}s" || check p-launch-nonblocking bad "rc=$rc took=${took}s pid=$pid"
    kill "$pid" 2>/dev/null
    cd "$root" || exit 1
}

group_recheck() {
    clone rc
    base_sha="$(git rev-parse HEAD)"
    export RECHECK_ISOLATE_DB=0
    commits() { git rev-list --count HEAD; }

    # r-flip: the wrong status flips; only that file lands; allowed-path work stays staged.
    cat > todo/fix.status.json <<'JSON'
{
  "plan": "fixture",
  "phases": [
    {
      "id": 1,
      "name": "wrongly failing",
      "status": "failing",
      "command": "mkdir -p wiki/_meta && echo x > wiki/_meta/staged.txt && git add -f wiki/_meta/staged.txt && true"
    }
  ]
}
JSON
    git commit -q -am "fixture" ; before="$(commits)"
    bin/recheck-todo >/dev/null 2>&1
    names="$(git show --name-only --format= HEAD)"
    [ "$(commits)" -eq $((before + 1))  ] && [ "$(git log -1 --format=%s)" = "chore(todo): recheck statuses" ] \
        && [ "$names" = "todo/fix.status.json" ] && grep -q '"passing"' todo/fix.status.json \
        && [ "$(git diff --cached --name-only)" = "wiki/_meta/staged.txt" ] \
        && check r-flip ok || check r-flip bad "names=$names commits=$(commits)/$before"

    # r-lock: a live run holds the lock, the second is a no-op; then a stale lock is reclaimed.
    reset "$base_sha"
    cat > todo/fix.status.json <<'JSON'
{
  "plan": "fixture",
  "phases": [
    {
      "id": 1,
      "name": "slow and wrong",
      "status": "failing",
      "command": "sleep 4"
    }
  ]
}
JSON
    git commit -q -am "fixture"; before="$(commits)"
    bin/recheck-todo >/dev/null 2>&1 &
    first=$!
    sleep 1.5
    bin/recheck-todo > "$base/second.out" 2>&1; second=$?
    [ "$(commits)" -eq "$before" ] && [ $second -eq 0 ] && grep -q 'another recheck is running' "$base/second.out" \
        && check r-lock-noop ok || check r-lock-noop bad "second=$second"
    wait "$first"
    [ "$(commits)" -eq $((before + 1)) ] && check r-lock ok || check r-lock bad "commits=$(commits)/$before"

    reset "$base_sha"
    sleep 0 & dead=$!; wait "$dead"
    mkdir .git/recheck-status.lock; echo "$dead" > .git/recheck-status.lock/pid
    git commit -q --allow-empty -m "fixture"; before="$(commits)"
    bin/recheck-todo >/dev/null 2>&1
    [ "$(commits)" -eq $((before + 1)) ] && check r-stale ok || check r-stale bad

    # r-skip: dirty tree, index.lock, rebase in progress, merge in progress.
    reset "$base_sha"; before="$(commits)"; ok=ok
    echo x > untracked.txt;               bin/recheck-todo >/dev/null 2>&1; [ "$(commits)" -eq "$before" ] || ok="bad dirty"; rm untracked.txt
    touch .git/index.lock;                bin/recheck-todo >/dev/null 2>&1; [ "$(commits)" -eq "$before" ] || ok="bad index.lock"; rm .git/index.lock
    mkdir .git/rebase-merge;              bin/recheck-todo >/dev/null 2>&1; [ "$(commits)" -eq "$before" ] || ok="bad rebase"; rmdir .git/rebase-merge
    git rev-parse HEAD > .git/MERGE_HEAD; bin/recheck-todo >/dev/null 2>&1; [ "$(commits)" -eq "$before" ] || ok="bad merge"; rm .git/MERGE_HEAD
    git diff --quiet && [ ! -e .git/recheck-status.lock ] || ok="bad leftovers"
    check r-skip "${ok%% *}" "$ok"

    # r-discard: HEAD moves during the run, or a status file takes a non-status edit.
    reset "$base_sha"
    sed -i.bak 's#"command": "true"#"command": "git commit -q --allow-empty -m moved"#' todo/fix.status.json; rm -f todo/fix.status.json.bak
    git commit -q -am "fixture"; head_before="$(git rev-parse HEAD)"
    bin/recheck-todo >/dev/null 2>&1
    [ "$(git log -1 --format=%s)" = "moved" ] && git diff --quiet && [ "$(git rev-list --count "$head_before"..HEAD)" -eq 1 ] \
        && check r-discard-head ok || check r-discard-head bad "$(git log -1 --format=%s)"

    reset "$base_sha"
    cat > todo/fix.status.json <<'JSON'
{
  "plan": "fixture",
  "phases": [
    {
      "id": 1,
      "name": "edits its neighbour",
      "status": "passing",
      "command": "sed -i.bak 's/neighbour-name/edited/' todo/neighbour.status.json && rm -f todo/neighbour.status.json.bak"
    }
  ]
}
JSON
    cat > todo/neighbour.status.json <<'JSON'
{
  "plan": "neighbour-name",
  "phases": [
    {
      "id": 1,
      "name": "wrongly failing",
      "status": "failing",
      "command": "true"
    }
  ]
}
JSON
    git add -A; git commit -q -m "fixture"; before="$(commits)"
    bin/recheck-todo >/dev/null 2>&1
    [ "$(commits)" -eq "$before" ] && grep -q '"edited"' todo/neighbour.status.json \
        && check r-discard-edit ok || check r-discard-edit bad
    cd "$root" || exit 1
}

groups=("$@")
[ ${#groups[@]} -gt 0 ] || groups=(commit-msg pre-push recheck)
for group in "${groups[@]}"; do
    case "$group" in
        commit-msg) group_commit_msg ;;
        pre-push) group_pre_push ;;
        recheck) group_recheck ;;
        *) echo "unknown group $group"; exit 2 ;;
    esac
done

[ "$failures" -eq 0 ]
