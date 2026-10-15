#!/usr/bin/env bash
# Runs every case in cases.json through the Node and the PHP version and diffs the output.
# Usage: tests/parity/run.sh [path to the props-builder package of fylgja/fylgja]
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
upstream="${1:-${PROPS_BUILDER_JS:-$root/../fylgja/props-builder}}"

if [[ ! -f "$upstream/index.js" ]]; then
    echo "Cannot find the Node props-builder at \"$upstream\"." >&2
    echo "Pass its path as the first argument, or set PROPS_BUILDER_JS." >&2
    exit 1
fi

node "$root/tests/parity/run.mjs" "$upstream" > "$root/tests/_parity-node.txt"
php "$root/tests/parity/run.php" > "$root/tests/_parity-php.txt"

if diff -u --label node --label php "$root/tests/_parity-node.txt" "$root/tests/_parity-php.txt"; then
    echo "Parity OK: $(grep -c '^### ' "$root/tests/_parity-php.txt") cases match."
else
    exit 1
fi
