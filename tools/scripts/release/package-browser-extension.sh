#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
extension_dir="$repo_root/apps/extension"
output_path="${1:-$repo_root/apps/platform/storage/app/companion-updates/air-look-extension.zip}"
staging_dir="$(mktemp -d)"

cleanup() {
  rm -rf "$staging_dir"
}
trap cleanup EXIT

mkdir -p "$(dirname "$output_path")"
cp "$extension_dir/manifest.json" "$staging_dir/manifest.json"
mkdir -p "$staging_dir/src"
cp "$extension_dir"/src/* "$staging_dir/src/"

if command -v bsdtar >/dev/null 2>&1; then
  (cd "$staging_dir" && bsdtar -a -cf "$output_path" manifest.json src)
elif command -v zip >/dev/null 2>&1; then
  (cd "$staging_dir" && zip -qr "$output_path" manifest.json src)
else
  php -r '
    $output = $argv[1];
    $root = $argv[2];
    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Unable to create ".$output.PHP_EOL);
        exit(1);
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
        $path = $file->getPathname();
        $relative = substr($path, strlen($root) + 1);
        $zip->addFile($path, str_replace(DIRECTORY_SEPARATOR, "/", $relative));
    }
    $zip->close();
  ' "$output_path" "$staging_dir"
fi

echo "$output_path"
