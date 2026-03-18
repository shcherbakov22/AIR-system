<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class CompanionRootCertificateController extends Controller
{
    public function __invoke(): Response
    {
        $path = (string) config('services.local_tls.root_ca_path');

        abort_unless($path !== '' && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/x-x509-ca-cert',
            'Content-Disposition' => 'inline; filename="air-root-ca.crt"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
