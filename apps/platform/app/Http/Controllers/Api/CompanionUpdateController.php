<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CompanionUpdateController extends Controller
{
    public function manifest(): JsonResponse
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $packagePath = $this->packagePath();
        $realPath = realpath($packagePath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        return response()->json([
            'available' => true,
            'version' => (string) config('services.companion_updates.version', '0.1.0'),
            'channel' => (string) config('services.companion_updates.channel', 'stable'),
            'mandatory' => false,
            'download_url' => route('api.companion.update.download'),
            'sha256' => hash_file('sha256', $realPath),
            'size_bytes' => filesize($realPath) ?: 0,
            'filename' => 'air-companion-windows.zip',
            'published_at' => date(DATE_ATOM, filemtime($realPath) ?: time()),
        ]);
    }

    public function download(): BinaryFileResponse
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $packagePath = $this->packagePath();
        $realPath = realpath($packagePath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        return $this->downloadResponse($realPath, 'air-companion-windows.zip');
    }

    public function installerBundle(): BinaryFileResponse
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $packagePath = $this->installerBundlePath();
        $realPath = realpath($packagePath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        return $this->downloadResponse($realPath, 'air-companion-windows-installer.zip');
    }

    public function browserExtensionBundle(): BinaryFileResponse
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $packagePath = $this->browserExtensionBundlePath();
        $realPath = realpath($packagePath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        return $this->downloadResponse($realPath, 'air-look-extension.zip');
    }

    public function browserExtensionCrx(): BinaryFileResponse
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $packagePath = $this->browserExtensionCrxPath();
        $realPath = realpath($packagePath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        return $this->fileResponse($realPath, 'application/x-chrome-extension');
    }

    public function browserExtensionUpdateManifest(): Response
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $manifestPath = $this->browserExtensionUpdateManifestPath();
        $realPath = realpath($manifestPath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        $contents = file_get_contents($realPath);
        abort_unless(is_string($contents), 404);

        return response($contents, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ])->setLastModified(new \DateTimeImmutable('@'.(filemtime($realPath) ?: time())));
    }

    private function packagePath(): string
    {
        $configuredPath = (string) config('services.companion_updates.windows_package_path', '');

        return $this->isAbsolutePath($configuredPath)
            ? $configuredPath
            : base_path($configuredPath);
    }

    private function installerBundlePath(): string
    {
        $configuredPath = (string) config('services.companion_updates.windows_installer_bundle_path', '');

        return $this->isAbsolutePath($configuredPath)
            ? $configuredPath
            : base_path($configuredPath);
    }

    private function browserExtensionBundlePath(): string
    {
        $configuredPath = (string) config('services.companion_updates.browser_extension_bundle_path', '');

        return $this->isAbsolutePath($configuredPath)
            ? $configuredPath
            : base_path($configuredPath);
    }

    private function browserExtensionCrxPath(): string
    {
        $configuredPath = (string) config('services.companion_updates.browser_extension_crx_path', '');

        return $this->isAbsolutePath($configuredPath)
            ? $configuredPath
            : base_path($configuredPath);
    }

    private function browserExtensionUpdateManifestPath(): string
    {
        $configuredPath = (string) config('services.companion_updates.browser_extension_update_manifest_path', '');

        return $this->isAbsolutePath($configuredPath)
            ? $configuredPath
            : base_path($configuredPath);
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
    }

    private function downloadResponse(
        string $realPath,
        string $filename,
        string $contentType = 'application/zip',
    ): BinaryFileResponse
    {
        $sha256 = hash_file('sha256', $realPath);

        return response()->download($realPath, $filename, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'X-AIR-Companion-SHA256' => $sha256,
            'X-AIR-Companion-Version' => (string) config('services.companion_updates.version', '0.1.0'),
            'ETag' => '"'.$sha256.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ])->setLastModified(new \DateTimeImmutable('@'.(filemtime($realPath) ?: time())));
    }

    private function fileResponse(string $realPath, string $contentType): BinaryFileResponse
    {
        $sha256 = hash_file('sha256', $realPath);

        return response()->file($realPath, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'X-AIR-Companion-SHA256' => $sha256,
            'ETag' => '"'.$sha256.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ])->setLastModified(new \DateTimeImmutable('@'.(filemtime($realPath) ?: time())));
    }
}
