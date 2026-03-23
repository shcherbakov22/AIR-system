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
$currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($currentIdentity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    $argumentList = @(
        '-ExecutionPolicy', 'Bypass',
        '-File', ('"{0}"' -f $PSCommandPath)
    )
    Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList $argumentList | Out-Null
    exit 0
}

$installDirectory = Join-Path $env:ProgramFiles 'AIR Companion'
$utility = Join-Path $installDirectory 'air_companion_tray.exe'
$serviceBinary = Join-Path $installDirectory 'air_companion_service.exe'
$serviceName = 'AIRCompanion'
$debugLog = Join-Path $env:windir 'System32\config\systemprofile\AppData\Roaming\AIRCompanion\debug.log'

if (-not (Test-Path $utility)) {
    throw "AIR Companion is not installed at $installDirectory."
}

if (-not (Get-Service -Name $serviceName -ErrorAction SilentlyContinue)) {
    throw "AIR Companion service is not installed."
}

& $utility --write-enrollment --base-url "__BASE_URL__" --enrollment-token "__ENROLLMENT_TOKEN__" --root-ca-url "__ROOT_CA_URL__"
if ($LASTEXITCODE -ne 0) {
    throw 'Failed to write AIR Companion enrollment request.'
}

if (-not (Test-Path $serviceBinary)) {
    throw "AIR Companion service binary is missing at $serviceBinary."
}

$service = Get-Service -Name $serviceName -ErrorAction Stop
if ($service.Status -ne 'Running') {
    Start-Service -Name $serviceName -ErrorAction SilentlyContinue
    if ((Get-Service -Name $serviceName).Status -ne 'Running') {
        sc.exe start $serviceName | Out-Null
    }
}

for ($attempt = 0; $attempt -lt 15; $attempt++) {
    $service = Get-Service -Name $serviceName -ErrorAction Stop
    if ($service.Status -eq 'Running') {
        Write-Host 'AIR Companion enrollment request written and service is running.'
        exit 0
    }

    Start-Sleep -Seconds 1
}

if (Test-Path $debugLog) {
    Get-Content $debugLog -Tail 50
}

throw "AIR Companion service failed to reach Running state. Check $debugLog"
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
