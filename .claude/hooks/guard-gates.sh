#!/usr/bin/env sh
#
# PreToolUse guard. An agent may not loosen the quality gates on its own:
# edits to gate config need a human to approve, and skipping git hooks is refused.

command -v jq >/dev/null 2>&1 || exit 0

input="$(cat)"
tool="$(printf '%s' "$input" | jq -r '.tool_name // empty')"

decide() {
    jq -n --arg decision "$1" --arg reason "$2" \
        '{hookSpecificOutput: {hookEventName: "PreToolUse", permissionDecision: $decision, permissionDecisionReason: $reason}}'
    exit 0
}

if [ "$tool" = "Bash" ]; then
    command="$(printf '%s' "$input" | jq -r '.tool_input.command // empty')"
    case "$command" in
        *--get*core.hooksPath*) ;;
        *--no-verify*|*core.hooksPath*)
            decide deny "Git hooks are the local quality gate. Fix what they report instead of skipping them." ;;
    esac
    exit 0
fi

path="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
path="${path#"$CLAUDE_PROJECT_DIR"/}"

case "$path" in
    phpstan*.neon|rector.php|pint.json|knip.json|.sloppy-baseline.json|vite.config.ts|tests/Unit/ArchTest.php|.github/workflows/*|.githooks/*|.claude/settings.json|.claude/hooks/*)
        decide ask "$path is part of the quality gate. A human approves every change to it." ;;
esac

exit 0
