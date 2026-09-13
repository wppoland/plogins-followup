#!/usr/bin/env bash
# Build a clean, installable ${NAME}.zip, honouring .distignore.
# Runtime is self-contained (bundled autoload/lib); vendor/ is dev-only and
# excluded by .distignore, so no composer step is needed here.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# The zip's top folder has to match the Text Domain, which is the wp.org slug.
# The checkout directory does not: 29 repos here are named `recover` while the
# plugin is `plogins-recover`, and a package built from the folder name is a
# TextDomainMismatch the reviewer rejects. Swift was already fixed this way in
# 1.0.8; this is the same fix everywhere else.
NAME="$(grep -m1 -h "Text Domain:" "$ROOT_DIR"/*.php 2>/dev/null | sed 's/.*Text Domain:[[:space:]]*//; s/[[:space:]]*$//')"
NAME="${NAME:-$(basename "$ROOT_DIR")}"
OUT_DIR="${1:-/tmp/${NAME}-build}"
STAGE="${OUT_DIR}/${NAME}"

# Run the plugin's own self-checks before anything is packaged. The release
# script globs tests/*-check.php too, but only for plugins already on wp.org,
# and this one is not there yet: until it is, a build is the only path a
# release takes, so this is where the check has to sit. Without it the install
# floor could be deleted and every automated check would still be green.
for chk in "$ROOT_DIR"/tests/*-check.php; do
    [ -f "$chk" ] || continue
    echo "Running $(basename "$chk")"
    ( cd "$ROOT_DIR" && php "$chk" )
done

rm -rf "${OUT_DIR}"
mkdir -p "${STAGE}"

rsync -a --exclude-from="${ROOT_DIR}/.distignore" \
    --exclude '.git' --exclude 'node_modules' --exclude '.DS_Store' \
    "${ROOT_DIR}/" "${STAGE}/"

find "${STAGE}" -name '.DS_Store' -delete

( cd "${OUT_DIR}" && zip -rqX "/tmp/${NAME}.zip" "${NAME}" -x '*.DS_Store' )
echo "Built /tmp/${NAME}.zip from ${STAGE}"
