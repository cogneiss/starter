#!/usr/bin/env sh
#
# PreToolUse guard. A session may edit one file under app/ freely. Its second
# distinct app/ file is denied until .work/feature_list.json exists with at
# least one item and every item names a verified_by command: think first
# (deepen), then freeze criteria (contract). Per-session state lives in
# .work/sessions/<session_id>.files and records only allowed edits.
# Covers Edit, Write and MultiEdit. Bash redirects and NotebookEdit are not seen.
# Fails open when php is missing or .work/sessions is not writable.

command -v php >/dev/null 2>&1 || exit 0

cat | CLAUDE_PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(pwd)}" php -r '
$v = json_decode(stream_get_contents(STDIN), true);
if (! is_array($v) || ! in_array($v["tool_name"] ?? "", ["Edit", "Write", "MultiEdit"], true)) {
    exit(0);
}
$path = $v["tool_input"]["file_path"] ?? "";
if (! is_string($path) || $path === "") {
    exit(0);
}
$project = rtrim(getenv("CLAUDE_PROJECT_DIR"), "/");
if (str_starts_with($path, $project."/")) {
    $path = substr($path, strlen($project) + 1);
}
if (str_starts_with($path, "/")) {
    exit(0);
}
$parts = [];
foreach (explode("/", $path) as $segment) {
    if ($segment === "" || $segment === ".") {
        continue;
    }
    if ($segment === "..") {
        array_pop($parts);
        continue;
    }
    $parts[] = $segment;
}
if (count($parts) < 2 || $parts[0] !== "app") {
    exit(0);
}
$file = implode("/", $parts);

$sid = $v["session_id"] ?? "";
$sid = substr(preg_replace("/[^A-Za-z0-9_-]/", "_", is_string($sid) ? $sid : ""), 0, 100);
$dir = $project."/.work/sessions";
if (! is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
if (! is_dir($dir) || ! is_writable($dir)) {
    exit(0);
}
$state = $dir."/".($sid === "" ? "default" : $sid).".files";
$seen = is_file($state) ? array_values(array_filter(array_map("trim", file($state)))) : [];
if (in_array($file, $seen, true)) {
    exit(0);
}

$listed = false;
$list = is_file($project."/.work/feature_list.json") ? json_decode(file_get_contents($project."/.work/feature_list.json"), true) : null;
$items = is_array($list) ? ($list["items"] ?? null) : null;
if (is_array($items) && $items !== []) {
    $listed = true;
    foreach ($items as $item) {
        $by = is_array($item) ? ($item["verified_by"] ?? null) : null;
        if (! is_string($by) || trim($by) === "") {
            $listed = false;
        }
    }
}

if ($seen !== [] && ! $listed) {
    echo json_encode(["hookSpecificOutput" => [
        "hookEventName" => "PreToolUse",
        "permissionDecision" => "deny",
        "permissionDecisionReason" => "This is the second app/ file this session and there is no frozen contract. Stop and think first: follow .ai/skills/deepen/SKILL.md, then .ai/skills/contract/SKILL.md, which writes .work/feature_list.json with a verified_by command on every item. Then retry the edit.",
    ]], JSON_UNESCAPED_SLASHES);
    exit(0);
}
@file_put_contents($state, $file."\n", FILE_APPEND);
'

exit 0
