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

if ! grep -Fq 'Plugin Name: BfCamel CRM' bfcamel-crm.php || ! grep -Fq 'Text Domain: bfcamel-crm' bfcamel-crm.php || ! grep -Fq 'Domain Path: /languages' bfcamel-crm.php; then
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

# Keep the reviewed Russian catalog healthy in the source repository while
# letting WordPress.org distribute approved translations as language packs.
TRANSLATION_CHECK_DIR="$(mktemp -d)"
trap 'rm -rf "$TRANSLATION_CHECK_DIR"' EXIT
TRANSLATION_CHECK_MO="$TRANSLATION_CHECK_DIR/bfcamel-crm-ru_RU.mo"
TRANSLATION_CHECK_PO="$TRANSLATION_CHECK_DIR/bfcamel-crm-ru_RU.po"
msgfmt --check --check-format languages/bfcamel-crm-ru_RU.po -o "$TRANSLATION_CHECK_MO"
msgunfmt --no-wrap "$TRANSLATION_CHECK_MO" >"$TRANSLATION_CHECK_PO"
if ! grep -F 'msgid "Forms"' -A1 "$TRANSLATION_CHECK_PO" | grep -Fq 'msgstr "Формы"'; then
  echo "Russian localization integrity check failed: Forms -> Формы not found." >&2
  exit 1
fi

BUILD_DIR="$ROOT_DIR/build"
PACKAGE_DIR="$BUILD_DIR/bfcamel-crm"
ZIP_PATH="$BUILD_DIR/bfcamel-crm-$VERSION.zip"
LATEST_ZIP_PATH="$BUILD_DIR/bfcamel-crm.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$PACKAGE_DIR"

rsync -a ./ "$PACKAGE_DIR/" \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='.github/' \
  --exclude='scripts/' \
  --exclude='tests/' \
  --exclude='build/' \
  --exclude='languages/*.po' \
  --exclude='languages/*.mo' \
  --exclude='*.zip' \
  --exclude='.DS_Store' \
  --exclude='.idea/' \
  --exclude='.vscode/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='README.md' \
  --exclude='SECURITY.md' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpcs.xml.dist'

(
  cd "$BUILD_DIR"
  zip -qr "$(basename "$ZIP_PATH")" bfcamel-crm
)

if ! unzip -Z1 "$ZIP_PATH" | grep -qx 'bfcamel-crm/bfcamel-crm.php'; then
  echo "Release ZIP does not contain bfcamel-crm/bfcamel-crm.php" >&2
  exit 1
fi

if unzip -Z1 "$ZIP_PATH" | grep -Eq '\.(po|mo)$'; then
  echo "Release ZIP contains bundled translation files instead of using WordPress.org language packs." >&2
  exit 1
fi

if ! unzip -Z1 "$ZIP_PATH" | grep -qx 'bfcamel-crm/languages/bfcamel-crm.pot'; then
  echo "Release ZIP does not contain the translation template." >&2
  exit 1
fi

if unzip -Z1 "$ZIP_PATH" | grep -Eq '^bfcamel-crm/\.git($|/)'; then
  echo "Release ZIP contains Git metadata." >&2
  exit 1
fi

TOP_LEVEL="$(unzip -Z1 "$ZIP_PATH" | sed 's#/.*##' | sort -u)"
if [[ "$TOP_LEVEL" != "bfcamel-crm" ]]; then
  echo "Release ZIP has an unexpected top-level directory: $TOP_LEVEL" >&2
  exit 1
fi

# Keep a stable release-asset URL while retaining the versioned package.
cp "$ZIP_PATH" "$LATEST_ZIP_PATH"

echo "$ZIP_PATH"
