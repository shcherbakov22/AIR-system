<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DeviceEnrollmentToken;
use App\Models\Student;
use App\Models\StudentDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CompanionEnrollmentController extends Controller
{
    public function show(Request $request): Response
    {
        $student = $request->user()?->student;
        abort_unless($student !== null, 404);

        $browserExtensionSetup = $this->browserExtensionSetupPayload($student, $request->ip());

        return Inertia::render('Student/Companion/Enroll', [
            'installer_download_url' => route('companion.installer.download'),
            'browser_extension_download_url' => route('companion.browser-extension.download'),
            'bootstrap_script_url' => route('student.companion.enroll.bootstrap'),
            'root_ca_url' => $this->rootCertificateUrl(),
            'base_url' => url('/'),
            'browser_extension_setup' => $browserExtensionSetup,
            'browser_extension_configure_url' => route('student.companion.enroll.browser-extension-token'),
        ]);
    }

    public function browserExtensionToken(Request $request): JsonResponse
    {
        $student = $request->user()?->student;
        abort_unless($student !== null, 404);

        return response()->json([
            'platform_url' => url('/'),
            'device_token' => $this->rotateBrowserExtensionSetupToken($student, $request->ip()),
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
        $rootCaUrl = $this->rootCertificateUrl();
        $script = <<<'POWERSHELL'
param(
    [switch]$Elevated
)

$ErrorActionPreference = 'Stop'
$logDirectory = Join-Path $env:ProgramData 'AIRCompanion\Logs'
$logPath = Join-Path $logDirectory 'enroll.log'
$bootstrapDirectory = Join-Path ([System.IO.Path]::GetTempPath()) 'AIRCompanion'
$resultPath = Join-Path $bootstrapDirectory 'enroll-result.txt'
$elevatedWrapperPath = Join-Path $bootstrapDirectory 'enroll-elevated.ps1'
New-Item -ItemType Directory -Force -Path $bootstrapDirectory | Out-Null

function Show-FailureAndPause {
    param(
        [string]$Message,
        [string]$LogPath
    )

    Write-Host ''
    Write-Host 'AIR Companion enrollment failed.' -ForegroundColor Red
    Write-Host $Message -ForegroundColor Red
    if ($LogPath) {
        Write-Host "Log: $LogPath" -ForegroundColor Yellow
    }
    Write-Host ''
    Read-Host 'Press Enter to close'
}

$currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($currentIdentity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator) -and -not $Elevated) {
    if (Test-Path $resultPath) {
        Remove-Item -Force $resultPath
    }

    @'
param(
    [string]$ScriptPath,
    [string]$ResultPath
)

$ErrorActionPreference = 'Stop'

try {
    & "$ScriptPath" -Elevated
    $exitCode = if ($LASTEXITCODE -ne $null) { $LASTEXITCODE } else { 0 }
    if ($exitCode -ne 0 -and -not (Test-Path $ResultPath)) {
        Set-Content -Path $ResultPath -Value "Elevated enrollment exited with code $exitCode."
    }
    exit $exitCode
} catch {
    Set-Content -Path $ResultPath -Value $_.Exception.Message
    exit 1
}
'@ | Set-Content -Path $elevatedWrapperPath

    $argumentList = ('-NoProfile -ExecutionPolicy Bypass -File "{0}" -ScriptPath "{1}" -ResultPath "{2}"' -f `
        $elevatedWrapperPath, $PSCommandPath, $resultPath)
    $process = Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList $argumentList -PassThru -Wait
    if ($process.ExitCode -ne 0) {
        $childMessage = if (Test-Path $resultPath) {
            Get-Content $resultPath -Raw
        } else {
            "Elevated enrollment exited with code $($process.ExitCode)."
        }

        Show-FailureAndPause -Message $childMessage.Trim() -LogPath $logPath
    }
    exit $process.ExitCode
}

try {
    Start-Transcript -Path $logPath -Append | Out-Null
    $installDirectory = Join-Path $env:ProgramFiles 'AIR Companion'
    $utility = Join-Path $installDirectory 'air_companion_tray.exe'
    $serviceBinary = Join-Path $installDirectory 'air_companion_service.exe'
    $serviceName = 'AIRCompanion'
    $debugLog = Join-Path $env:windir 'System32\config\systemprofile\AppData\Roaming\AIRCompanion\debug.log'
    $requestPath = Join-Path $env:ProgramData 'AIRCompanion\Internal\enrollment-request.json'

    if (-not (Test-Path $utility)) {
        throw "AIR Companion is not installed at $installDirectory."
    }

    if (-not (Get-Service -Name $serviceName -ErrorAction SilentlyContinue)) {
        throw "AIR Companion service is not installed."
    }

    if (Test-Path $requestPath -PathType Container) {
        Remove-Item -Recurse -Force $requestPath
    }

    $utilityOutput = & $utility --write-enrollment --base-url "__BASE_URL__" --enrollment-token "__ENROLLMENT_TOKEN__" --root-ca-url "__ROOT_CA_URL__" 2>&1
    if ($LASTEXITCODE -ne 0) {
        $utilityMessage = ($utilityOutput | ForEach-Object { "$_" }) -join [Environment]::NewLine
        if ([string]::IsNullOrWhiteSpace($utilityMessage)) {
            throw 'Failed to write AIR Companion enrollment request.'
        }

        throw "Failed to write AIR Companion enrollment request.`n$utilityMessage"
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
            Set-Content -Path $resultPath -Value 'AIR Companion enrollment request written and service is running.'
            Write-Host 'AIR Companion enrollment request written and service is running.'
            Write-Host "Enrollment log: $logPath"
            Stop-Transcript | Out-Null
            exit 0
        }

        Start-Sleep -Seconds 1
    }

    if (Test-Path $debugLog) {
        Get-Content $debugLog -Tail 50
    }

    throw "AIR Companion service failed to reach Running state. Check $debugLog"
} catch {
    try {
        Set-Content -Path $resultPath -Value $_.Exception.Message
    } catch {
    }
    try {
        Stop-Transcript | Out-Null
    } catch {
    }
    Show-FailureAndPause -Message $_.Exception.Message -LogPath $logPath
    exit 1
}
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

    private function rootCertificateUrl(): string
    {
        $configuredPath = config('services.local_tls.root_ca_path');

        if (! is_string($configuredPath) || $configuredPath === '') {
            return '';
        }

        $path = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $configuredPath) === 1
            ? $configuredPath
            : base_path($configuredPath);
        $realPath = realpath($path);

        return $realPath !== false && is_file($realPath) && is_readable($realPath)
            ? route('companion.root-ca')
            : '';
    }

    /**
     * @return array{platform_url: string, device_token: string}
     */
    private function browserExtensionSetupPayload(Student $student, ?string $ipAddress): array
    {
        $device = StudentDevice::query()->updateOrCreate(
            [
                'device_key' => 'browser-extension:student:'.$student->id,
            ],
            [
                'student_id' => $student->id,
                'label' => 'Chrome browser extension',
                'hostname' => null,
                'platform' => 'chrome_extension',
                'app_version' => '0.1.0',
                'last_seen_ip' => $ipAddress,
            ],
        );

        return [
            'platform_url' => url('/'),
            'device_token' => is_array($device->meta) && is_string($device->meta['setup_token'] ?? null)
                ? $device->meta['setup_token']
                : '',
        ];
    }

    private function rotateBrowserExtensionSetupToken(Student $student, ?string $ipAddress): string
    {
        $device = StudentDevice::query()->updateOrCreate(
            [
                'device_key' => 'browser-extension:student:'.$student->id,
            ],
            [
                'student_id' => $student->id,
                'label' => 'Chrome browser extension',
                'hostname' => null,
                'platform' => 'chrome_extension',
                'app_version' => '0.1.0',
                'last_seen_ip' => $ipAddress,
            ],
        );

        return $this->issueBrowserExtensionSetupToken($device);
    }

    private function issueBrowserExtensionSetupToken(StudentDevice $device): string
    {
        $meta = is_array($device->meta) ? $device->meta : [];
        $token = Str::random(64);
        $meta['setup_token'] = $token;

        $device->forceFill([
            'token_hash' => hash('sha256', $token),
            'meta' => $meta,
        ])->save();

        return $token;
    }
}
