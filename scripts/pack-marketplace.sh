#!/usr/bin/env bash
# Pack plateam.partner for Bitrix Marketplace upload.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MOD="$ROOT/modules/plateam.partner"
VERSION="$(php -r 'include "'"$MOD"'/install/version.php"; echo $arModuleVersion["VERSION"];')"
OUT_DIR="$ROOT/dist"
NAME="plateam.partner-${VERSION}"
STAGE="$OUT_DIR/.pack-$$"

mkdir -p "$OUT_DIR"
rm -rf "$STAGE"
mkdir -p "$STAGE"
cp -a "$MOD" "$STAGE/plateam.partner"

# Safety: never ship devtools leftovers inside module
rm -rf "$STAGE/plateam.partner/tools/debug_"* \
  "$STAGE/plateam.partner/tools/test_"* \
  "$STAGE/plateam.partner/tools/setup_demo_"* \
  "$STAGE/plateam.partner/tools/web_install.php" \
  "$STAGE/plateam.partner/tools/install_paysystem.php" 2>/dev/null || true

ZIP="$OUT_DIR/${NAME}.zip"
rm -f "$ZIP"
(cd "$STAGE" && zip -r "$ZIP" plateam.partner -x '*.DS_Store' -x '*/.git/*')
rm -rf "$STAGE"
echo "Wrote $ZIP"
ls -la "$ZIP"
