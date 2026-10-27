#!/usr/bin/env sh
#
# PostToolUse formatter. Formats the one file an agent just wrote, so style
# never reaches review. Failures are ignored: the pre-commit hook is the gate.

command -v jq >/dev/null 2>&1 || exit 0

path="$(jq -r '.tool_input.file_path // empty')"
[ -f "$path" ] || exit 0

cd "$CLAUDE_PROJECT_DIR" || exit 0

case "$path" in
    *.php)
        vendor/bin/pint --quiet "$path" ;;
    */resources/*.ts|*/resources/*.tsx|*/resources/*.css)
        NODE_OPTIONS='--experimental-strip-types' node_modules/.bin/vp fmt "$path" >/dev/null 2>&1 ;;
esac

exit 0
