<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'published_at' => date(DATE_ATOM, filemtime($realPath) ?: time()),
        ]);
    }

    public function download(): BinaryFileResponse
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $packagePath = $this->packagePath();
        $realPath = realpath($packagePath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        return response()->download($realPath, 'air-companion-windows.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function installerBundle(): BinaryFileResponse
    {
        abort_unless((bool) config('services.companion_updates.enabled', true), 404);

        $packagePath = $this->installerBundlePath();
        $realPath = realpath($packagePath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        return response()->download($realPath, 'air-companion-windows-installer.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
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

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
    }
}
