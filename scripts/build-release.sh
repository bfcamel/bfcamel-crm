#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

for cmd in msgfmt msgunfmt zip rsync php unzip; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "$cmd is required." >&2
    exit 1
  fi
done

VERSION="$(php -r '$data=file_get_contents("bfcamel-crm.php"); if (preg_match("/^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+)/mi", $data, $m)) { echo $m[1]; }')"
if [[ -z "$VERSION" ]]; then
  echo "Could not read plugin version from bfcamel-crm.php" >&2
  exit 1
fi

BUILD_DIR="$ROOT_DIR/build"
PACKAGE_DIR="$BUILD_DIR/bfcamel-crm"
ZIP_PATH="$BUILD_DIR/bfcamel-crm-$VERSION.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$PACKAGE_DIR"

rsync -a ./ "$PACKAGE_DIR/" \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='scripts/' \
  --exclude='build/' \
  --exclude='*.zip' \
  --exclude='.DS_Store' \
  --exclude='.idea/' \
  --exclude='.vscode/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='README.md'

PO_FILE="$PACKAGE_DIR/languages/bfcamel-crm-ru_RU.po"
MO_FILE="$PACKAGE_DIR/languages/bfcamel-crm-ru_RU.mo"

# Build the binary catalog from the UTF-8 PO source on every release. The MO is
# intentionally not committed to Git because a stale binary caused mojibake in
# 0.1.3. Runtime Russian localization also has a PO fallback for safety.
msgfmt --check --check-format "$PO_FILE" -o "$MO_FILE"

# Fail the release if the generated catalog cannot be decoded or if a known
# Cyrillic translation is missing/corrupted.
msgunfmt --no-wrap "$MO_FILE" >/tmp/bfcamel-crm-ru_RU.po
if ! grep -F 'msgid "Forms"' -A1 /tmp/bfcamel-crm-ru_RU.po | grep -Fq 'msgstr "Формы"'; then
  echo "Russian localization integrity check failed: Forms -> Формы not found." >&2
  exit 1
fi
rm -f /tmp/bfcamel-crm-ru_RU.po

(
  cd "$BUILD_DIR"
  zip -qr "$(basename "$ZIP_PATH")" bfcamel-crm
)

if ! unzip -Z1 "$ZIP_PATH" | grep -qx 'bfcamel-crm/bfcamel-crm.php'; then
  echo "Release ZIP does not contain bfcamel-crm/bfcamel-crm.php" >&2
  exit 1
fi

if ! unzip -Z1 "$ZIP_PATH" | grep -qx 'bfcamel-crm/languages/bfcamel-crm-ru_RU.mo'; then
  echo "Release ZIP does not contain the compiled Russian MO catalog" >&2
  exit 1
fi

TOP_LEVEL="$(unzip -Z1 "$ZIP_PATH" | sed 's#/.*##' | sort -u)"
if [[ "$TOP_LEVEL" != "bfcamel-crm" ]]; then
  echo "Release ZIP has an unexpected top-level directory: $TOP_LEVEL" >&2
  exit 1
fi

echo "$ZIP_PATH"
