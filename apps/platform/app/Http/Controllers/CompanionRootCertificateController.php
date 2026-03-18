<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanionRootCertificateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $configuredPath = config('services.local_tls.root_ca_path');

        abort_unless(is_string($configuredPath) && $configuredPath !== '', 404);

        $resolvedPath = $this->resolvePath($configuredPath);
        $realPath = realpath($resolvedPath);

        abort_unless($realPath !== false && is_file($realPath) && is_readable($realPath), 404);

        $response = response()->download($realPath, 'air-root-ca.crt', [
            'Content-Type' => 'application/x-x509-ca-cert',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    private function resolvePath(string $path): string
    {
        return $this->isAbsolutePath($path)
            ? $path
            : base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
    }
}
