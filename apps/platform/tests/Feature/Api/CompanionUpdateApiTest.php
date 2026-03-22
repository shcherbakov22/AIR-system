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
            ->assertDownload('air-companion-windows.zip');
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

    public function test_companion_update_manifest_returns_not_found_when_package_is_missing(): void
    {
        config()->set('services.companion_updates.enabled', true);
        config()->set('services.companion_updates.windows_package_path', storage_path('framework/testing/missing-update-package.zip'));

        $this->getJson(route('api.companion.update.manifest'))
            ->assertNotFound();
    }
}
