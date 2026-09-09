#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

for cmd in msgfmt msgunfmt msgattrib msgcmp zip rsync php unzip; do
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

CONSTANT_VERSION="$(php -r '$data=file_get_contents("bfcamel-crm.php"); if (preg_match("/define\\(\\s*[\"\x27]BFCAMEL_CRM_VERSION[\"\x27]\\s*,\\s*[\"\x27]([^\"\x27]+)[\"\x27]/", $data, $m)) { echo $m[1]; }')"
STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -n1)"
if [[ "$VERSION" != "$CONSTANT_VERSION" || "$VERSION" != "$STABLE_TAG" ]]; then
  echo "Plugin header, BFCAMEL_CRM_VERSION and Stable tag must match." >&2
  exit 1
fi

if ! grep -Fq 'Plugin Name: BfCamel CRM' bfcamel-crm.php || ! grep -Fq 'Text Domain: bfcamel-crm' bfcamel-crm.php || ! grep -Fq 'Domain Path: /languages' bfcamel-crm.php || ! grep -Fq 'Update URI: https://github.com/bfcamel/bfcamel-crm' bfcamel-crm.php; then
  echo "The established WordPress plugin identity changed." >&2
  exit 1
fi

msgcmp --use-fuzzy languages/bfcamel-crm-ru_RU.po languages/bfcamel-crm.pot
if msgattrib --untranslated --no-obsolete languages/bfcamel-crm-ru_RU.po | grep -q '^#:'; then
  echo "Russian catalog contains untranslated strings." >&2
  exit 1
fi
if msgattrib --only-fuzzy --no-obsolete languages/bfcamel-crm-ru_RU.po | grep -q '^#:'; then
  echo "Russian catalog contains fuzzy translations." >&2
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
  --exclude='tests/' \
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
# 0.1.3. WordPress loads this freshly compiled catalog through its standard
# text-domain mechanism.
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
