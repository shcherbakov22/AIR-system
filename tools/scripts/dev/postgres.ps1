[CmdletBinding()]
param(
    [ValidateSet('ensure', 'start', 'stop', 'status')]
    [string]$Action = 'status'
)

$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$postgresRoot = Join-Path $repoRoot '.local\postgres'
$dataDir = Join-Path $postgresRoot 'data'
$logDir = Join-Path $postgresRoot 'log'
$logFile = Join-Path $logDir 'postgres.log'
$port = 55432
$hostAddress = '127.0.0.1'
$superUser = 'postgres'
$superPassword = 'postgres_dev'
$appUser = 'school_system'
$appPassword = 'school_system_dev'
$appDatabase = 'school_system_redo'

function Get-PostgresBinDir {
    $candidates = @(
        $env:SCHOOL_SYSTEM_POSTGRES_BIN,
        (Join-Path $env:USERPROFILE 'scoop\apps\postgresql\current\bin')
    ) | Where-Object { $_ }

    foreach ($candidate in $candidates) {
        if (Test-Path (Join-Path $candidate 'pg_ctl.exe')) {
            return $candidate
        }
    }

    throw 'PostgreSQL binaries were not found. Install PostgreSQL with scoop or set SCHOOL_SYSTEM_POSTGRES_BIN.'
}

function Get-PostgresCommands {
    $binDir = Get-PostgresBinDir

    return @{
        InitDb = Join-Path $binDir 'initdb.exe'
        PgCtl = Join-Path $binDir 'pg_ctl.exe'
        Psql = Join-Path $binDir 'psql.exe'
    }
}

function Test-ClusterInitialized {
    return Test-Path (Join-Path $dataDir 'PG_VERSION')
}

function Test-ClusterRunning {
    param(
        [hashtable]$Commands
    )

    & $Commands.PgCtl -D $dataDir status *> $null
    return $LASTEXITCODE -eq 0
}

function Ensure-ClusterInitialized {
    param(
        [hashtable]$Commands
    )

    if (Test-ClusterInitialized) {
        return
    }

    New-Item -ItemType Directory -Force -Path $postgresRoot, $logDir | Out-Null

    $passwordFile = Join-Path $postgresRoot 'postgres.password'

    try {
        Set-Content -Path $passwordFile -Value $superPassword -NoNewline

        & $Commands.InitDb `
            -D $dataDir `
            --username=$superUser `
            --auth=scram-sha-256 `
            --pwfile=$passwordFile `
            --encoding=UTF8 `
            --locale=C

        if ($LASTEXITCODE -ne 0) {
            throw 'initdb failed.'
        }
    }
    finally {
        if (Test-Path $passwordFile) {
            Remove-Item $passwordFile -Force
        }
    }

    $configFile = Join-Path $dataDir 'postgresql.conf'
    Add-Content -Path $configFile -Value ''
    Add-Content -Path $configFile -Value "# School System local overrides"
    Add-Content -Path $configFile -Value "listen_addresses = '$hostAddress'"
    Add-Content -Path $configFile -Value "port = $port"
    Add-Content -Path $configFile -Value "timezone = 'UTC'"
    Add-Content -Path $configFile -Value "password_encryption = 'scram-sha-256'"
}

function Start-Cluster {
    param(
        [hashtable]$Commands
    )

    if (Test-ClusterRunning -Commands $Commands) {
        return
    }

    New-Item -ItemType Directory -Force -Path $logDir | Out-Null

    & $Commands.PgCtl -D $dataDir -l $logFile start | Out-Null

    if ($LASTEXITCODE -ne 0) {
        throw 'pg_ctl start failed.'
    }

    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        Start-Sleep -Milliseconds 500

        if (Test-ClusterRunning -Commands $Commands) {
            return
        }
    }

    throw 'PostgreSQL did not report a healthy status after startup.'
}

function Stop-Cluster {
    param(
        [hashtable]$Commands
    )

    if (-not (Test-ClusterInitialized)) {
        Write-Host 'Cluster is not initialized.'
        return
    }

    if (-not (Test-ClusterRunning -Commands $Commands)) {
        Write-Host 'Cluster is already stopped.'
        return
    }

    & $Commands.PgCtl -D $dataDir stop -m fast | Out-Null

    if ($LASTEXITCODE -ne 0) {
        throw 'pg_ctl stop failed.'
    }
}

function Invoke-PsqlCommand {
    param(
        [hashtable]$Commands,
        [string]$Database,
        [string]$Sql
    )

    $previousPassword = $env:PGPASSWORD
    $env:PGPASSWORD = $superPassword

    try {
        $output = & $Commands.Psql `
            -h $hostAddress `
            -p $port `
            -U $superUser `
            -d $Database `
            -v ON_ERROR_STOP=1 `
            -tAc $Sql

        if ($LASTEXITCODE -ne 0) {
            throw "psql failed while executing: $Sql"
        }

        return $output
    }
    finally {
        $env:PGPASSWORD = $previousPassword
    }
}

function Ensure-AppDatabase {
    param(
        [hashtable]$Commands
    )

    $escapedAppPassword = $appPassword.Replace("'", "''")
    $roleExists = Invoke-PsqlCommand -Commands $Commands -Database 'postgres' -Sql "SELECT 1 FROM pg_roles WHERE rolname = '$appUser';"

    if ($roleExists -ne '1') {
        Invoke-PsqlCommand -Commands $Commands -Database 'postgres' -Sql "CREATE ROLE $appUser LOGIN PASSWORD '$escapedAppPassword';" | Out-Null
    }
    else {
        Invoke-PsqlCommand -Commands $Commands -Database 'postgres' -Sql "ALTER ROLE $appUser WITH LOGIN PASSWORD '$escapedAppPassword';" | Out-Null
    }

    $databaseExists = Invoke-PsqlCommand -Commands $Commands -Database 'postgres' -Sql "SELECT 1 FROM pg_database WHERE datname = '$appDatabase';"

    if ($databaseExists -ne '1') {
        Invoke-PsqlCommand -Commands $Commands -Database 'postgres' -Sql "CREATE DATABASE $appDatabase OWNER $appUser ENCODING 'UTF8' TEMPLATE template0;" | Out-Null
    }
}

$commands = Get-PostgresCommands

switch ($Action) {
    'ensure' {
        Ensure-ClusterInitialized -Commands $commands
        Start-Cluster -Commands $commands
        Ensure-AppDatabase -Commands $commands

        Write-Host "PostgreSQL is ready on $hostAddress`:$port"
        Write-Host "Database: $appDatabase"
        Write-Host "User: $appUser"
    }
    'start' {
        Ensure-ClusterInitialized -Commands $commands
        Start-Cluster -Commands $commands
        Write-Host "PostgreSQL started on $hostAddress`:$port"
    }
    'stop' {
        Stop-Cluster -Commands $commands
        Write-Host 'PostgreSQL stopped.'
    }
    'status' {
        if (-not (Test-ClusterInitialized)) {
            Write-Host 'Cluster is not initialized.'
            exit 1
        }

        if (Test-ClusterRunning -Commands $commands) {
            Write-Host "PostgreSQL is running on $hostAddress`:$port"
            exit 0
        }

        Write-Host 'Cluster exists but is stopped.'
        exit 1
    }
}
