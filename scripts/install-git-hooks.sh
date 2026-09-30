#!/bin/sh
root="$(cd "$(dirname "$0")/.." && pwd)"
cp "$root/.githooks/commit-msg" "$root/.git/hooks/commit-msg"
chmod +x "$root/.git/hooks/commit-msg"
echo "Installed commit-msg hook -> $root/.git/hooks/commit-msg"
