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
            'repair_script_url' => route('student.companion.enroll.repair-permissions'),
            'browser_extension_download_url' => route('companion.browser-extension.download'),
            'browser_extension_enterprise_script_url' => route('student.companion.enroll.browser-extension-enterprise-install'),
            'bootstrap_script_url' => route('student.companion.enroll.bootstrap'),
            'root_ca_url' => $this->rootCertificateUrl(),
            'base_url' => url('/'),
            'browser_extension_setup' => $browserExtensionSetup,
            'browser_extension_configure_url' => route('student.companion.enroll.browser-extension-token'),
        ]);
    }

    public function browserExtensionEnterpriseInstallScript(Request $request): HttpResponse
    {
        $student = $request->user()?->student;
        abort_unless($student !== null, 404);

        $extensionId = 'cccijfadcaffnndbpdfdhbncehedgkhb';
        $updateUrl = route('companion.browser-extension.update-manifest');
        $platformUrl = url('/');
        $deviceToken = $this->ensureBrowserExtensionSetupToken($student, $request->ip());
        $chromeEnterpriseEnrollmentToken = (string) config('services.companion_updates.chrome_enterprise_enrollment_token', '');
        $rootCaUrl = $this->rootCertificateUrl();
        $script = <<<'POWERSHELL'
param(
    [switch]$Elevated,
    [switch]$Native64
)

$ErrorActionPreference = 'Stop'
$extensionId = '__EXTENSION_ID__'
$updateUrl = '__UPDATE_URL__'
$platformUrl = '__PLATFORM_URL__'
$deviceToken = '__DEVICE_TOKEN__'
$chromeEnterpriseEnrollmentToken = '__CHROME_ENTERPRISE_ENROLLMENT_TOKEN__'
$rootCaUrl = '__ROOT_CA_URL__'
$forceInstallValue = "$extensionId;$updateUrl"
$bootstrapDirectory = Join-Path $env:ProgramData 'AIRCompanion\EnterpriseExtension'
$resultPath = Join-Path $bootstrapDirectory 'enterprise-extension-install-result.txt'
$elevatedWrapperPath = Join-Path $bootstrapDirectory 'enterprise-extension-install-elevated.ps1'

if (-not $Native64 -and $env:PROCESSOR_ARCHITEW6432) {
    $nativePowerShell = Join-Path $env:WINDIR 'Sysnative\WindowsPowerShell\v1.0\powershell.exe'
    if (Test-Path $nativePowerShell) {
        $argumentList = ('-NoProfile -ExecutionPolicy Bypass -File "{0}" -Native64' -f $PSCommandPath)
        if ($Elevated) {
            $argumentList += ' -Elevated'
        }

        $process = Start-Process -FilePath $nativePowerShell -ArgumentList $argumentList -PassThru -Wait
        exit $process.ExitCode
    }
}

function Show-FailureAndPause {
    param([string]$Message)

    Write-Host ''
    Write-Host 'AIR browser extension enterprise install failed.' -ForegroundColor Red
    Write-Host $Message -ForegroundColor Red
    Write-Host ''
    Read-Host 'Press Enter to close'
}

function Ensure-Elevated {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if ($principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        return
    }

    New-Item -ItemType Directory -Force -Path $bootstrapDirectory | Out-Null
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
        Set-Content -Path $ResultPath -Value "Elevated extension install exited with code $exitCode."
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
            "Elevated extension install exited with code $($process.ExitCode)."
        }

        Show-FailureAndPause -Message $childMessage.Trim()
    }

    exit $process.ExitCode
}

function Set-PolicyValue {
    param(
        [string]$Path,
        [string]$Name,
        [string]$Value
    )

    New-Item -Path $Path -Force | Out-Null
    New-ItemProperty -Path $Path -Name $Name -Value $Value -PropertyType String -Force | Out-Null
}

function Install-RootCertificate {
    if ([string]::IsNullOrWhiteSpace($rootCaUrl)) {
        return
    }

    $certificatePath = Join-Path $bootstrapDirectory 'air-local-root-ca.crt'
    try {
        Invoke-WebRequest -Uri $rootCaUrl -OutFile $certificatePath -UseBasicParsing
        certutil.exe -addstore -f Root $certificatePath | Out-Null
    } catch {
        throw "Failed to install local root certificate from $rootCaUrl. $($_.Exception.Message)"
    }
}

function Set-ExtensionManagedConfig {
    param([string]$BrowserPolicyRoot)

    $policyPath = Join-Path $BrowserPolicyRoot "3rdparty\extensions\$extensionId\policy"
    Set-PolicyValue -Path $policyPath -Name 'platformUrl' -Value $platformUrl
    Set-PolicyValue -Path $policyPath -Name 'deviceToken' -Value $deviceToken
}

function Set-ExtensionForceInstall {
    param([string]$BrowserPolicyRoot)

    Set-PolicyValue `
        -Path (Join-Path $BrowserPolicyRoot 'ExtensionInstallForcelist') `
        -Name '1' `
        -Value $forceInstallValue
}

function Set-ExtensionSettings {
    param([string]$BrowserPolicyRoot)

    $extensionSettings = @{
        $extensionId = @{
            override_update_url = $true
            file_url_navigation_allowed = $true
            blocked_permissions = @()
        }
    } | ConvertTo-Json -Compress -Depth 5

    Set-PolicyValue `
        -Path $BrowserPolicyRoot `
        -Name 'ExtensionSettings' `
        -Value $extensionSettings
}

function Clear-ExtensionForceInstall {
    param([string]$BrowserPolicyRoot)

    $forceListPath = Join-Path $BrowserPolicyRoot 'ExtensionInstallForcelist'
    for ($index = 1; $index -le 20; $index++) {
        $valueName = [string]$index
        $currentValue = $null
        try {
            $currentValue = (Get-ItemProperty -Path $forceListPath -Name $valueName -ErrorAction Stop).$valueName
        } catch {
            continue
        }

        if ($currentValue -eq $forceInstallValue) {
            Remove-ItemProperty -Path $forceListPath -Name $valueName -Force -ErrorAction SilentlyContinue
        }
    }

    Remove-Item -Path $forceListPath -Recurse -Force -ErrorAction SilentlyContinue

    try {
        $extensionSettings = (Get-ItemProperty -Path $BrowserPolicyRoot -Name 'ExtensionSettings' -ErrorAction Stop).ExtensionSettings
        if ($extensionSettings -like "*$extensionId*") {
            Remove-ItemProperty -Path $BrowserPolicyRoot -Name 'ExtensionSettings' -Force -ErrorAction SilentlyContinue
        }
    } catch {
    }
}

function Set-ChromeEnterpriseEnrollment {
    if ([string]::IsNullOrWhiteSpace($chromeEnterpriseEnrollmentToken)) {
        return
    }

    Set-PolicyValue `
        -Path 'HKLM:\Software\Policies\Google\Chrome' `
        -Name 'CloudManagementEnrollmentToken' `
        -Value $chromeEnterpriseEnrollmentToken
}

try {
    Ensure-Elevated
    New-Item -ItemType Directory -Force -Path $bootstrapDirectory | Out-Null

    Install-RootCertificate
    Set-ChromeEnterpriseEnrollment

    if ([string]::IsNullOrWhiteSpace($chromeEnterpriseEnrollmentToken)) {
        Set-ExtensionForceInstall -BrowserPolicyRoot 'HKLM:\Software\Policies\Google\Chrome'
        Set-ExtensionForceInstall -BrowserPolicyRoot 'HKLM:\Software\Policies\Microsoft\Edge'
    } else {
        Clear-ExtensionForceInstall -BrowserPolicyRoot 'HKLM:\Software\Policies\Google\Chrome'
        Clear-ExtensionForceInstall -BrowserPolicyRoot 'HKLM:\Software\Policies\Microsoft\Edge'
    }

    Set-ExtensionSettings -BrowserPolicyRoot 'HKLM:\Software\Policies\Google\Chrome'
    Set-ExtensionSettings -BrowserPolicyRoot 'HKLM:\Software\Policies\Microsoft\Edge'
    Set-ExtensionManagedConfig -BrowserPolicyRoot 'HKLM:\Software\Policies\Google\Chrome'
    Set-ExtensionManagedConfig -BrowserPolicyRoot 'HKLM:\Software\Policies\Microsoft\Edge'

    Set-Content -Path $resultPath -Value "OK: $forceInstallValue configured for $platformUrl"

    Write-Host ''
    Write-Host 'AIR browser extension enterprise install policy applied.' -ForegroundColor Green
    Write-Host "Extension ID: $extensionId"
    Write-Host "Update URL: $updateUrl"
    Write-Host "Platform URL: $platformUrl"
    if ([string]::IsNullOrWhiteSpace($chromeEnterpriseEnrollmentToken)) {
        Write-Host 'Chrome and Edge local force-install policies written.'
        Write-Host 'Chrome and Edge ExtensionSettings policies written.'
    } else {
        Write-Host 'Chrome Enterprise Core enrollment token written.'
        Write-Host 'Local Chrome and Edge force-install policies for AIR were cleared.'
        Write-Host 'Chrome and Edge ExtensionSettings policies written.'
    }
    Write-Host 'Managed extension token written to Chrome and Edge policy.'
    Write-Host ''
    Write-Host 'Restart Chrome/Edge or open chrome://policy and edge://policy, then click Reload policies.'
    Write-Host ''
    Read-Host 'Press Enter to close'
    exit 0
} catch {
    try {
        Set-Content -Path $resultPath -Value $_.Exception.Message
    } catch {
    }

    Show-FailureAndPause -Message $_.Exception.Message
    exit 1
}
POWERSHELL;

        $script = str_replace(
            ['__EXTENSION_ID__', '__UPDATE_URL__', '__PLATFORM_URL__', '__DEVICE_TOKEN__', '__CHROME_ENTERPRISE_ENROLLMENT_TOKEN__', '__ROOT_CA_URL__'],
            [$extensionId, $updateUrl, $platformUrl, $deviceToken, $chromeEnterpriseEnrollmentToken, $rootCaUrl],
            $script
        );

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="install-browser-extension-enterprise-policy.ps1"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function repairPermissionsScript(Request $request): HttpResponse
    {
        abort_unless($request->user()?->student !== null, 404);

        $script = <<<'POWERSHELL'
param(
    [string]$InstallDirectory = "$env:ProgramFiles\AIR Companion",
    [string]$UserDataDirectory = "$env:APPDATA\AIRCompanion",
    [switch]$SystemRepair
)

$ErrorActionPreference = 'Stop'
$serviceName = 'AIRCompanion'
$programDataRoot = Join-Path $env:ProgramData 'AIRCompanion'
$publicRoot = Join-Path $env:PUBLIC 'AIRCompanion'
$systemProfileDataRoot = Join-Path $env:windir 'System32\config\systemprofile\AppData\Roaming\AIRCompanion'
$logDirectory = Join-Path $programDataRoot 'Logs'
$logPath = Join-Path $logDirectory 'permission-repair.log'
$bootstrapDirectory = Join-Path $env:windir 'Temp\AIRCompanion'
$resultPath = Join-Path $bootstrapDirectory 'permission-repair-result.txt'
$elevatedWrapperPath = Join-Path $bootstrapDirectory 'permission-repair-elevated.ps1'
$systemScriptPath = Join-Path $bootstrapDirectory 'permission-repair-system.ps1'

function Ensure-Elevated {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    if ($identity.User.Value -eq 'S-1-5-18') {
        return
    }

    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if ($principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        return
    }

    New-Item -ItemType Directory -Force -Path $bootstrapDirectory | Out-Null
    @'
param(
    [string]$ScriptPath,
    [string]$ResultPath,
    [string]$InstallDirectory,
    [string]$UserDataDirectory
)

$ErrorActionPreference = 'Stop'

try {
    & "$ScriptPath" -InstallDirectory $InstallDirectory -UserDataDirectory $UserDataDirectory
    $exitCode = if ($LASTEXITCODE -ne $null) { $LASTEXITCODE } else { 0 }
    if ($exitCode -ne 0 -and -not (Test-Path $ResultPath)) {
        Set-Content -Path $ResultPath -Value "Elevated repair exited with code $exitCode."
    }
    exit $exitCode
} catch {
    Set-Content -Path $ResultPath -Value $_.Exception.Message
    exit 1
}
'@ | Set-Content -Path $elevatedWrapperPath

    $argumentList = ('-NoProfile -ExecutionPolicy Bypass -File "{0}" -ScriptPath "{1}" -ResultPath "{2}" -InstallDirectory "{3}" -UserDataDirectory "{4}"' -f `
        $elevatedWrapperPath, $PSCommandPath, $resultPath, $InstallDirectory, $UserDataDirectory)
    $process = Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList $argumentList -PassThru -Wait

    if ($process.ExitCode -ne 0) {
        if (Test-Path $resultPath) {
            throw (Get-Content $resultPath -Raw).Trim()
        }

        throw "Elevated repair exited with code $($process.ExitCode)."
    }

    exit 0
}

function Invoke-SystemRepair {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    if ($SystemRepair -or $identity.User.Value -eq 'S-1-5-18') {
        return
    }

    New-Item -ItemType Directory -Force -Path $bootstrapDirectory | Out-Null
    Copy-Item -Path $PSCommandPath -Destination $systemScriptPath -Force
    if (Test-Path $resultPath) {
        Remove-Item -Force $resultPath
    }

    $taskName = 'AIR Companion Permission Repair'
    $taskArgument = '-NoProfile -ExecutionPolicy Bypass -File "{0}" -InstallDirectory "{1}" -UserDataDirectory "{2}" -SystemRepair' -f `
        $systemScriptPath, $InstallDirectory, $UserDataDirectory

    try {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
        $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $taskArgument
        $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1)
        $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
        $settings = New-CompanionScheduledTaskSettings -ExecutionMinutes 5
        Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
    } catch {
        throw "Failed to create SYSTEM permission repair task. $($_.Exception.Message)"
    }

    try {
        Start-ScheduledTask -TaskName $taskName -ErrorAction Stop
    } catch {
        throw "Failed to start SYSTEM permission repair task. $($_.Exception.Message)"
    }

    for ($attempt = 0; $attempt -lt 180; $attempt++) {
        if (Test-Path $resultPath) {
            $result = Get-Content $resultPath -Raw
            Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

            if ($result -match '^OK') {
                Write-Host $result.Trim()
                exit 0
            }

            throw $result.Trim()
        }

        Start-Sleep -Seconds 1
    }

    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    throw 'SYSTEM permission repair task timed out.'
}

function New-CompanionScheduledTaskSettings {
    param([int]$ExecutionMinutes = 0)

    try {
        if ($ExecutionMinutes -gt 0) {
            return New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes $ExecutionMinutes)
        }

        return New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries
    } catch {
        return New-ScheduledTaskSettingsSet
    }
}

function New-CompanionRepeatingMinuteTrigger {
    try {
        return New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
    } catch {
        $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1)
        try {
            $trigger.Repetition.Interval = 'PT1M'
            $trigger.Repetition.Duration = 'P1D'
        } catch {
        }

        return $trigger
    }
}

function Invoke-NativeCommand {
    param(
        [string]$FilePath,
        [string[]]$Arguments,
        [string]$FailureMessage,
        [switch]$IgnoreFailure
    )

    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        $output = & $FilePath @Arguments 2>&1
        $exitCode = if ($LASTEXITCODE -ne $null) { $LASTEXITCODE } else { 0 }
    } catch {
        if ($IgnoreFailure) {
            return
        }

        throw "$FailureMessage`n$($_.Exception.Message)"
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($exitCode -ne 0 -and -not $IgnoreFailure) {
        $details = ($output | ForEach-Object { "$_" }) -join [Environment]::NewLine
        if ([string]::IsNullOrWhiteSpace($details)) {
            throw $FailureMessage
        }

        throw "$FailureMessage`n$details"
    }
}

function Ensure-Directory {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
    }
}

function Take-PathOwnership {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return
    }

    Invoke-NativeCommand -FilePath 'takeown.exe' -Arguments @('/F', $Path, '/A', '/R', '/D', 'Y') -FailureMessage "Failed to take ownership of $Path." -IgnoreFailure
    Invoke-NativeCommand -FilePath 'icacls.exe' -Arguments @($Path, '/setowner', '*S-1-5-32-544', '/T', '/C') -FailureMessage "Failed to set owner for $Path." -IgnoreFailure
}

function Set-CompanionAcl {
    param(
        [string]$Path,
        [ValidateSet('None', 'ReadExecute', 'Modify')]
        [string]$UsersAccess = 'None',
        [switch]$AllowPartial
    )

    Ensure-Directory $Path
    Take-PathOwnership $Path

    $grants = @(
        '*S-1-5-18:(OI)(CI)F',
        '*S-1-5-32-544:(OI)(CI)F'
    )

    if ($UsersAccess -eq 'ReadExecute') {
        $grants += '*S-1-5-32-545:(OI)(CI)RX'
    } elseif ($UsersAccess -eq 'Modify') {
        $grants += '*S-1-5-32-545:(OI)(CI)M'
    }

    Invoke-NativeCommand -FilePath 'icacls.exe' -Arguments (@($Path, '/inheritance:e', '/grant:r') + $grants) -FailureMessage "Failed to repair root ACLs for $Path."
    Invoke-NativeCommand -FilePath 'icacls.exe' -Arguments (@($Path, '/inheritance:e', '/grant:r') + $grants + @('/T', '/C')) -FailureMessage "Failed to repair ACLs for $Path." -IgnoreFailure:$AllowPartial
}

function Assert-PathWritable {
    param(
        [string]$Path,
        [string]$Name
    )

    Ensure-Directory $Path
    $testPath = Join-Path $Path ('permission-repair-test-{0}.tmp' -f ([Guid]::NewGuid().ToString('N')))

    try {
        Set-Content -Path $testPath -Value 'ok' -ErrorAction Stop
        Remove-Item -Force $testPath -ErrorAction SilentlyContinue
    } catch {
        throw "$Name is not writable after repair: $Path. $($_.Exception.Message)"
    }
}

function Assert-PathReadable {
    param(
        [string]$Path,
        [string]$Name
    )

    Ensure-Directory $Path

    try {
        Get-ChildItem -LiteralPath $Path -Force -ErrorAction Stop | Select-Object -First 1 | Out-Null
    } catch {
        throw "$Name is not readable after repair: $Path. $($_.Exception.Message)"
    }
}

function Repair-CompanionBinaryAcls {
    param([string]$InstallDirectory)

    foreach ($fileName in @('air_companion_service.exe', 'air_companion_tray.exe', 'air_companion_helper.exe', 'air_companion_updater.exe')) {
        $path = Join-Path $InstallDirectory $fileName
        if (-not (Test-Path $path)) {
            continue
        }

        Invoke-NativeCommand -FilePath 'takeown.exe' -Arguments @('/F', $path, '/A') -FailureMessage "Failed to take ownership of $path." -IgnoreFailure
        Invoke-NativeCommand -FilePath 'icacls.exe' -Arguments @($path, '/setowner', '*S-1-5-32-544', '/C') -FailureMessage "Failed to set owner for $path." -IgnoreFailure
        Invoke-NativeCommand -FilePath 'icacls.exe' -Arguments @($path, '/inheritance:e', '/grant:r', '*S-1-5-18:F', '*S-1-5-32-544:F', '*S-1-5-32-545:RX', '/C') -FailureMessage "Failed to repair ACLs for $path." -IgnoreFailure
    }
}

function Repair-CompanionDataAcls {
    Set-CompanionAcl -Path $programDataRoot -UsersAccess Modify -AllowPartial
    Set-CompanionAcl -Path (Join-Path $programDataRoot 'Service') -UsersAccess Modify
    Set-CompanionAcl -Path (Join-Path $programDataRoot 'Internal') -UsersAccess Modify
    Set-CompanionAcl -Path (Join-Path $programDataRoot 'Captures') -UsersAccess Modify
    Set-CompanionAcl -Path $logDirectory -UsersAccess Modify
    Set-CompanionAcl -Path $systemProfileDataRoot -UsersAccess Modify

    if (-not [string]::IsNullOrWhiteSpace($UserDataDirectory)) {
        Set-CompanionAcl -Path $UserDataDirectory -UsersAccess Modify -AllowPartial
    }

    Set-CompanionAcl -Path $publicRoot -UsersAccess Modify -AllowPartial
    Set-CompanionAcl -Path (Join-Path $publicRoot 'InteractiveCapture') -UsersAccess Modify
}

function Assert-CompanionDataAcls {
    Assert-PathReadable -Path $programDataRoot -Name 'ProgramData AIRCompanion root'
    Assert-PathReadable -Path (Join-Path $programDataRoot 'Service') -Name 'Service folder'
    Assert-PathReadable -Path (Join-Path $programDataRoot 'Internal') -Name 'Internal enrollment folder'
    Assert-PathWritable -Path (Join-Path $programDataRoot 'Captures') -Name 'Captures folder'
    Assert-PathWritable -Path $logDirectory -Name 'Logs folder'
    Assert-PathWritable -Path $systemProfileDataRoot -Name 'System profile AIRCompanion folder'

    if (-not [string]::IsNullOrWhiteSpace($UserDataDirectory)) {
        Assert-PathWritable -Path $UserDataDirectory -Name 'User AIRCompanion folder'
    }

    Assert-PathWritable -Path $publicRoot -Name 'Public AIRCompanion folder'
    Assert-PathWritable -Path (Join-Path $publicRoot 'InteractiveCapture') -Name 'Interactive capture folder'
}

function Stop-CompanionRuntime {
    param([string]$ServiceName)

    Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
    foreach ($processName in @('air_companion_tray.exe', 'air_companion_helper.exe', 'air_companion_updater.exe')) {
        Invoke-NativeCommand -FilePath 'taskkill.exe' -Arguments @('/IM', $processName, '/F') -FailureMessage "Failed to stop $processName." -IgnoreFailure
    }
}

function Ensure-ServiceWatchdogTasks {
    param([string]$ServiceName)

    $taskDefinitions = @(
        @{ Name = 'AIR Companion Service (Boot)'; Trigger = New-ScheduledTaskTrigger -AtStartup },
        @{ Name = 'AIR Companion Service (Logon)'; Trigger = New-ScheduledTaskTrigger -AtLogOn },
        @{ Name = 'AIR Companion Service (Watchdog)'; Trigger = New-CompanionRepeatingMinuteTrigger }
    )

    foreach ($task in $taskDefinitions) {
        try {
            Unregister-ScheduledTask -TaskName $task.Name -Confirm:$false -ErrorAction SilentlyContinue
            $action = New-ScheduledTaskAction -Execute 'sc.exe' -Argument "start $ServiceName"
            $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
            $settings = New-CompanionScheduledTaskSettings
            Register-ScheduledTask -TaskName $task.Name -Action $action -Trigger $task.Trigger -Principal $principal -Settings $settings -Force | Out-Null
        } catch {
            throw "Failed to repair watchdog task '$($task.Name)'. $($_.Exception.Message)"
        }
    }
}

function Repair-Service {
    param(
        [string]$ServiceName,
        [string]$InstallDirectory
    )

    $serviceBinary = Join-Path $InstallDirectory 'air_companion_service.exe'
    if (-not (Test-Path $serviceBinary)) {
        throw "Missing service binary: $serviceBinary"
    }

    $quotedBinary = '"' + $serviceBinary + '"'
    $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue

    if ($null -eq $service) {
        New-Service -Name $ServiceName -BinaryPathName $quotedBinary -DisplayName 'AIR Companion' -StartupType Automatic | Out-Null
    } else {
        Invoke-NativeCommand -FilePath 'sc.exe' -Arguments @('config', $ServiceName, 'binPath=', $quotedBinary, 'start=', 'delayed-auto', 'obj=', 'LocalSystem') -FailureMessage 'Failed to repair AIR Companion service configuration.'
    }

    Invoke-NativeCommand -FilePath 'sc.exe' -Arguments @('failure', $ServiceName, 'reset=', '86400', 'actions=', 'restart/5000/restart/15000/restart/30000') -FailureMessage 'Failed to repair AIR Companion service restart policy.'
    Invoke-NativeCommand -FilePath 'reg.exe' -Arguments @('add', "HKLM\SYSTEM\CurrentControlSet\Services\$ServiceName", '/v', 'DelayedAutostart', '/t', 'REG_DWORD', '/d', '1', '/f') -FailureMessage 'Failed to enable delayed autostart for AIR Companion service.'
    Ensure-ServiceWatchdogTasks -ServiceName $ServiceName
}

try {
    Ensure-Elevated
    Invoke-SystemRepair

    try {
        Ensure-Directory $logDirectory
        Start-Transcript -Path $logPath -Append | Out-Null
    } catch {
    }

    Stop-CompanionRuntime -ServiceName $serviceName
    Start-Sleep -Seconds 2

    Set-CompanionAcl -Path $InstallDirectory -UsersAccess ReadExecute -AllowPartial
    Repair-CompanionBinaryAcls -InstallDirectory $InstallDirectory
    Repair-CompanionDataAcls
    Assert-CompanionDataAcls

    Repair-Service -ServiceName $serviceName -InstallDirectory $InstallDirectory

    Start-Service -Name $serviceName -ErrorAction SilentlyContinue
    if ((Get-Service -Name $serviceName -ErrorAction Stop).Status -ne 'Running') {
        Invoke-NativeCommand -FilePath 'sc.exe' -Arguments @('start', $serviceName) -FailureMessage 'Failed to start AIR Companion service.'
    }

    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        $service = Get-Service -Name $serviceName -ErrorAction Stop
        if ($service.Status -eq 'Running') {
            Set-Content -Path $resultPath -Value 'OK: AIR Companion permissions repaired and service is running.'
            Write-Host "AIR Companion permissions repaired and service is running."
            Write-Host "Repair log: $logPath"
            try {
                Stop-Transcript | Out-Null
            } catch {
            }
            exit 0
        }

        Start-Sleep -Seconds 1
    }

    throw "AIR Companion service did not reach Running state after permission repair."
} catch {
    if ($SystemRepair) {
        try {
            Set-Content -Path $resultPath -Value $_.Exception.Message
        } catch {
        }
        exit 1
    }

    try {
        Stop-Transcript | Out-Null
    } catch {
    }

    Write-Host ''
    Write-Host 'AIR Companion permission repair failed.' -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host "Log: $logPath" -ForegroundColor Yellow
    Write-Host ''
    Read-Host 'Press Enter to close'
    try {
        Set-Content -Path $resultPath -Value $_.Exception.Message
    } catch {
    }
    exit 1
}
POWERSHELL;

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="repair-companion-permissions.ps1"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
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

function Ensure-Directory {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
    }
}

function Invoke-NativeCommand {
    param(
        [string]$FilePath,
        [string[]]$Arguments,
        [string]$FailureMessage
    )

    $output = & $FilePath @Arguments 2>&1
    $exitCode = if ($LASTEXITCODE -ne $null) { $LASTEXITCODE } else { 0 }

    if ($exitCode -ne 0) {
        $details = ($output | ForEach-Object { "$_" }) -join [Environment]::NewLine
        if ([string]::IsNullOrWhiteSpace($details)) {
            throw $FailureMessage
        }

        throw "$FailureMessage`n$details"
    }
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
    Ensure-Directory $logDirectory
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
            Invoke-NativeCommand -FilePath 'sc.exe' -Arguments @('start', $serviceName) -FailureMessage 'Failed to start AIR Companion service.'
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
                'revoked_at' => null,
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
                'revoked_at' => null,
            ],
        );

        return $this->issueBrowserExtensionSetupToken($device);
    }

    private function ensureBrowserExtensionSetupToken(Student $student, ?string $ipAddress): string
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
                'revoked_at' => null,
            ],
        );

        $setupToken = is_array($device->meta) && is_string($device->meta['setup_token'] ?? null)
            ? $device->meta['setup_token']
            : '';

        if ($setupToken !== '' && hash('sha256', $setupToken) === $device->token_hash) {
            return $setupToken;
        }

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
            'revoked_at' => null,
        ])->save();

        return $token;
    }
}
