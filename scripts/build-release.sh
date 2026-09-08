#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v msgfmt >/dev/null 2>&1; then
  echo "msgfmt is required (install GNU gettext)." >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "zip is required." >&2
  exit 1
fi

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

# Always compile the MO file from the editable PO source used for this release.
msgfmt "$PACKAGE_DIR/languages/bfcamel-crm-ru_RU.po" \
  -o "$PACKAGE_DIR/languages/bfcamel-crm-ru_RU.mo"

(
  cd "$BUILD_DIR"
  zip -qr "$(basename "$ZIP_PATH")" bfcamel-crm
)

if ! unzip -Z1 "$ZIP_PATH" | grep -qx 'bfcamel-crm/bfcamel-crm.php'; then
  echo "Release ZIP does not contain bfcamel-crm/bfcamel-crm.php" >&2
  exit 1
fi

TOP_LEVEL="$(unzip -Z1 "$ZIP_PATH" | sed 's#/.*##' | sort -u)"
if [[ "$TOP_LEVEL" != "bfcamel-crm" ]]; then
  echo "Release ZIP has an unexpected top-level directory: $TOP_LEVEL" >&2
  exit 1
fi

echo "$ZIP_PATH"
