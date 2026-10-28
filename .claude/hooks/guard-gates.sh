#!/usr/bin/env sh
#
# PreToolUse guard. An agent may not loosen the quality gates on its own:
# edits to gate config need a human to approve, and skipping git hooks is refused.
# JSON is read with PHP, which every contributor already has, so the guard
# never silently switches off on a machine without jq.

command -v php >/dev/null 2>&1 || exit 0

input="$(cat)"

field() {
    printf '%s' "$input" | php -r '$v = json_decode(stream_get_contents(STDIN), true); foreach (array_slice($argv, 1) as $k) { $v = $v[$k] ?? ""; } echo is_string($v) ? $v : "";' -- "$@"
}

decide() {
    php -r 'echo json_encode(["hookSpecificOutput" => ["hookEventName" => "PreToolUse", "permissionDecision" => $argv[1], "permissionDecisionReason" => $argv[2]]]);' -- "$1" "$2"
    exit 0
}

tool="$(field tool_name)"

if [ "$tool" = "Bash" ]; then
    command="$(field tool_input command)"
    case "$command" in
        *--get*core.hooksPath*) ;;
        *--no-verify*|*core.hooksPath*)
            decide deny "Git hooks are the local quality gate. Fix what they report instead of skipping them." ;;
    esac
    exit 0
fi

path="$(field tool_input file_path)"
path="${path#"$CLAUDE_PROJECT_DIR"/}"

case "$path" in
    phpstan*.neon|rector.php|pint.json|knip.json|.sloppy-baseline.json|vite.config.ts|tests/Unit/ArchTest.php|.github/workflows/*|.githooks/*|.claude/settings.json|.claude/hooks/*)
        decide ask "$path is part of the quality gate. A human approves every change to it." ;;
esac

exit 0
