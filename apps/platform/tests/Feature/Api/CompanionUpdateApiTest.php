<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CompanionUpdateApiTest extends TestCase
{
    public function test_companion_update_manifest_returns_latest_windows_package(): void
    {
        $directory = storage_path('framework/testing/companion-updates');
        @mkdir($directory, 0777, true);
        $packagePath = $directory.'/air-companion-windows.zip';
        file_put_contents($packagePath, 'fake-zip-bytes');

        config()->set('services.companion_updates.enabled', true);
        config()->set('services.companion_updates.version', '0.1.7');
        config()->set('services.companion_updates.channel', 'stable');
        config()->set('services.companion_updates.windows_package_path', $packagePath);

        $response = $this->getJson(route('api.companion.update.manifest'));

        $response
            ->assertOk()
            ->assertJson([
                'available' => true,
                'version' => '0.1.7',
                'channel' => 'stable',
                'mandatory' => false,
                'download_url' => route('api.companion.update.download'),
                'sha256' => hash_file('sha256', $packagePath),
                'size_bytes' => filesize($packagePath),
                'filename' => 'air-companion-windows.zip',
            ]);
    }

    public function test_companion_update_download_serves_windows_package(): void
    {
        $directory = storage_path('framework/testing/companion-updates-download');
        @mkdir($directory, 0777, true);
        $packagePath = $directory.'/air-companion-windows.zip';
        file_put_contents($packagePath, 'fake-zip-bytes');

        config()->set('services.companion_updates.enabled', true);
        config()->set('services.companion_updates.windows_package_path', $packagePath);

        $this->get(route('api.companion.update.download'))
            ->assertOk()
            ->assertHeader('X-AIR-Companion-SHA256', hash_file('sha256', $packagePath))
            ->assertHeader('X-AIR-Companion-Version', '0.1.13')
            ->assertDownload('air-companion-windows.zip');
    }

    public function test_companion_update_download_exposes_retry_safe_metadata_headers(): void
    {
        $directory = storage_path('framework/testing/companion-updates-download-metadata');
        @mkdir($directory, 0777, true);
        $packagePath = $directory.'/air-companion-windows.zip';
        file_put_contents($packagePath, 'fake-zip-bytes');

        config()->set('services.companion_updates.enabled', true);
        config()->set('services.companion_updates.version', '0.2.4');
        config()->set('services.companion_updates.windows_package_path', $packagePath);

        $response = $this->get(route('api.companion.update.download'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-AIR-Companion-SHA256', hash_file('sha256', $packagePath))
            ->assertHeader('X-AIR-Companion-Version', '0.2.4');

        $this->assertStringContainsString(
            hash_file('sha256', $packagePath),
            (string) $response->headers->get('ETag')
        );
    }

    public function test_companion_installer_bundle_download_serves_windows_installer_bundle(): void
    {
        $directory = storage_path('framework/testing/companion-installer-download');
        @mkdir($directory, 0777, true);
        $bundlePath = $directory.'/air-companion-windows-installer.zip';
        file_put_contents($bundlePath, 'fake-installer-zip-bytes');

        config()->set('services.companion_updates.enabled', true);
        config()->set('services.companion_updates.windows_installer_bundle_path', $bundlePath);

        $this->get(route('companion.installer.download'))
            ->assertOk()
            ->assertDownload('air-companion-windows-installer.zip');
    }

    public function test_browser_extension_bundle_download_serves_blocklist_extension(): void
    {
        $directory = storage_path('framework/testing/browser-extension-download');
        @mkdir($directory, 0777, true);
        $bundlePath = $directory.'/air-look-extension.zip';
        file_put_contents($bundlePath, 'fake-extension-zip-bytes');

        config()->set('services.companion_updates.enabled', true);
        config()->set('services.companion_updates.browser_extension_bundle_path', $bundlePath);

        $this->get(route('companion.browser-extension.download'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('X-AIR-Companion-SHA256', hash_file('sha256', $bundlePath))
            ->assertDownload('air-look-extension.zip');
    }

    public function test_companion_update_manifest_returns_not_found_when_package_is_missing(): void
    {
        config()->set('services.companion_updates.enabled', true);
        config()->set('services.companion_updates.windows_package_path', storage_path('framework/testing/missing-update-package.zip'));

        $this->getJson(route('api.companion.update.manifest'))
            ->assertNotFound();
    }
}
