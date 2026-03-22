<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DeviceEnrollmentToken;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CompanionEnrollmentController extends Controller
{
    public function show(Request $request): Response
    {
        $student = $request->user()?->student;
        abort_unless($student !== null, 404);

        return Inertia::render('Student/Companion/Enroll', [
            'installer_download_url' => route('companion.installer.download'),
            'bootstrap_script_url' => route('student.companion.enroll.bootstrap'),
            'root_ca_url' => route('companion.root-ca'),
            'base_url' => url('/'),
        ]);
    }

    public function bootstrapScript(Request $request): HttpResponse
    {
        $student = $request->user()?->student;
        abort_unless($student !== null, 404);

        [, $plainTextToken] = DeviceEnrollmentToken::issue(
            $student->id,
            $request->user()->id,
            gethostname() ?: null,
            30,
        );

        $baseUrl = url('/');
        $rootCaUrl = route('companion.root-ca');
        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$installDirectory = Join-Path $env:ProgramFiles 'AIR Companion'
$utility = Join-Path $installDirectory 'air_companion_tray.exe'
$serviceName = 'AIRCompanion'

if (-not (Test-Path $utility)) {
    throw "AIR Companion is not installed at $installDirectory."
}

& $utility --write-enrollment --base-url "__BASE_URL__" --enrollment-token "__ENROLLMENT_TOKEN__" --root-ca-url "__ROOT_CA_URL__"
if ($LASTEXITCODE -ne 0) {
    throw 'Failed to write AIR Companion enrollment request.'
}

try {
    Start-Service -Name $serviceName -ErrorAction Stop | Out-Null
} catch {
    sc.exe start $serviceName | Out-Null
}

Write-Host 'AIR Companion enrollment request written and service start requested.'
POWERSHELL;

        $script = str_replace(
            ['__BASE_URL__', '__ENROLLMENT_TOKEN__', '__ROOT_CA_URL__'],
            [$baseUrl, $plainTextToken, $rootCaUrl],
            $script
        );

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="air-companion-enroll.ps1"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
