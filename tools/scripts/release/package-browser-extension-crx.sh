#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
extension_dir="$repo_root/apps/extension"
updates_dir="$repo_root/apps/platform/storage/app/companion-updates"
key_path="${BROWSER_EXTENSION_KEY_PATH:-$updates_dir/air-look-extension.pem}"
crx_path="${1:-$updates_dir/air-look-extension.crx}"
xml_path="${2:-$updates_dir/air-look-extension-updates.xml}"
crx_url="${BROWSER_EXTENSION_CRX_URL:-https://192.168.11.228/companion/downloads/chrome/extension.crx}"
update_url="${BROWSER_EXTENSION_UPDATE_URL:-https://192.168.11.228/companion/downloads/chrome/extension-updates.xml}"
chromium_bin="${CHROMIUM_BIN:-}"
staging_dir="$(mktemp -d)"

cleanup() {
  rm -rf "$staging_dir"
}
trap cleanup EXIT

if [[ -z "$chromium_bin" ]]; then
  for candidate in chromium chromium-browser google-chrome google-chrome-stable; do
    if command -v "$candidate" >/dev/null 2>&1; then
      chromium_bin="$(command -v "$candidate")"
      break
    fi
  done
fi

if [[ -z "$chromium_bin" ]]; then
  echo "Unable to find chromium/google-chrome for CRX packaging." >&2
  exit 1
fi

mkdir -p "$updates_dir" "$(dirname "$crx_path")" "$(dirname "$xml_path")"
cp "$extension_dir/manifest.json" "$staging_dir/manifest.json"
mkdir -p "$staging_dir/src"
cp -R "$extension_dir"/src/. "$staging_dir/src/"

php -r '
    $manifestPath = $argv[1];
    $updateUrl = $argv[2];
    $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest["update_url"] = $updateUrl;
    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
    );
' "$staging_dir/manifest.json" "$update_url"

pack_output="$staging_dir.crx"
generated_key="$staging_dir.pem"

if [[ -f "$key_path" ]]; then
  "$chromium_bin" --no-sandbox --pack-extension="$staging_dir" --pack-extension-key="$key_path" >/dev/null
else
  "$chromium_bin" --no-sandbox --pack-extension="$staging_dir" >/dev/null
  if [[ ! -f "$generated_key" ]]; then
    echo "Chromium did not generate an extension key at $generated_key." >&2
    exit 1
  fi
  mv "$generated_key" "$key_path"
  chmod 0600 "$key_path"
fi

if [[ ! -f "$pack_output" ]]; then
  echo "Chromium did not create CRX output at $pack_output." >&2
  exit 1
fi

mv "$pack_output" "$crx_path"

pub_hex="$(
  openssl pkey -in "$key_path" -pubout -outform DER 2>/dev/null \
    | sha256sum \
    | awk '{print substr($1, 1, 32)}'
)"
extension_id="$(php -r '
    $hex = $argv[1];
    $id = "";
    for ($i = 0; $i < strlen($hex); $i++) {
        $id .= chr(ord("a") + hexdec($hex[$i]));
    }
    echo $id;
' "$pub_hex")"
version="$(php -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo $manifest["version"];
' "$staging_dir/manifest.json")"

cat > "$xml_path" <<XML
<?xml version="1.0" encoding="UTF-8"?>
<gupdate xmlns="http://www.google.com/update2/response" protocol="2.0">
  <app appid="$extension_id">
    <updatecheck codebase="$crx_url" version="$version" />
  </app>
</gupdate>
XML

echo "CRX: $crx_path"
echo "Update XML: $xml_path"
echo "Extension ID: $extension_id"
echo "Version: $version"
echo "Update URL: $update_url"
