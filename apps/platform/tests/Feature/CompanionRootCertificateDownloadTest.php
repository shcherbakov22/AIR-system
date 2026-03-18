<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CompanionRootCertificateDownloadTest extends TestCase
{
    private ?string $temporaryCertificateDirectory = null;

    protected function tearDown(): void
    {
        if ($this->temporaryCertificateDirectory !== null) {
            File::deleteDirectory($this->temporaryCertificateDirectory);
        }

        parent::tearDown();
    }

    public function test_companion_root_certificate_can_be_downloaded_from_a_relative_configured_path(): void
    {
        $certificatePath = $this->makeCertificateFile();
        $relativePath = ltrim(str_replace(base_path(), '', $certificatePath), '\\/');

        config()->set('services.local_tls.root_ca_path', $relativePath);

        $response = $this->get(route('companion.root-ca', [], false));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/x-x509-ca-cert')
            ->assertDownload('air-root-ca.crt')
            ->assertHeader('pragma', 'no-cache')
            ->assertHeader('x-content-type-options', 'nosniff');

        $cacheControl = (string) $response->headers->get('cache-control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame((string) filesize($certificatePath), $response->headers->get('content-length'));
    }

    public function test_companion_root_certificate_returns_not_found_when_the_path_is_missing(): void
    {
        config()->set('services.local_tls.root_ca_path', null);

        $this->get(route('companion.root-ca', [], false))
            ->assertNotFound();
    }

    public function test_companion_root_certificate_returns_not_found_when_the_configured_file_does_not_exist(): void
    {
        config()->set('services.local_tls.root_ca_path', 'storage/framework/testing/missing-root-ca/root.crt');

        $this->get(route('companion.root-ca', [], false))
            ->assertNotFound();
    }

    private function makeCertificateFile(): string
    {
        $directory = storage_path('framework/testing/companion-root-ca-'.uniqid('', true));

        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.'root.crt', "-----BEGIN CERTIFICATE-----\nTESTROOT\n-----END CERTIFICATE-----\n");

        $this->temporaryCertificateDirectory = $directory;

        return $directory.DIRECTORY_SEPARATOR.'root.crt';
    }
}
