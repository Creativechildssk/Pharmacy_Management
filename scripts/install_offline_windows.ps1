param(
    [string]$XamppPath = 'C:\xampp',
    [string]$InstallPath = 'C:\xampp\htdocs\Pharmacy_Management',
    [string]$DatabaseName = 'pharmacy_management',
    [string]$DatabaseUser = 'pharmacy_user',
    [string]$MySqlRootUser = 'root',
    [string]$MySqlRootPassword = '',
    [switch]$ForceInstall
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$BundleRoot = $PSScriptRoot
$SourceApp = Join-Path $BundleRoot 'app'
$ChecksumFile = Join-Path $BundleRoot 'SHA256SUMS.txt'
$MySqlExe = Join-Path $XamppPath 'mysql\bin\mysql.exe'
$PhpExe = Join-Path $XamppPath 'php\php.exe'

function Convert-ToSqlLiteral([string]$Value) {
    return $Value.Replace("'", "''")
}

function Invoke-MySqlText {
    param([string]$Sql)
    $args = @('-u', $MySqlRootUser)
    if ($MySqlRootPassword -ne '') { $args += "--password=$MySqlRootPassword" }
    $args += @('--default-character-set=utf8mb4', '-e', $Sql)
    & $MySqlExe @args
    if ($LASTEXITCODE -ne 0) { throw 'MySQL command failed. Confirm MySQL is running and the root credentials are correct.' }
}

function Import-MySqlFile {
    param([string]$Database, [string]$File)
    $args = @('-u', $MySqlRootUser)
    if ($MySqlRootPassword -ne '') { $args += "--password=$MySqlRootPassword" }
    $args += @('--default-character-set=utf8mb4', $Database)
    Get-Content -LiteralPath $File -Raw | & $MySqlExe @args
    if ($LASTEXITCODE -ne 0) { throw "Failed importing $File" }
}

function Test-BundleChecksums {
    if (-not (Test-Path -LiteralPath $ChecksumFile)) { throw 'SHA256SUMS.txt is missing from the USB bundle.' }
    foreach ($line in Get-Content -LiteralPath $ChecksumFile) {
        if ($line -notmatch '^([0-9a-fA-F]{64})  (.+)$') { continue }
        $expected = $Matches[1].ToLowerInvariant()
        $relative = $Matches[2].Replace('/', [IO.Path]::DirectorySeparatorChar)
        $path = Join-Path $BundleRoot $relative
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Bundle file is missing: $relative" }
        $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath $path).Hash.ToLowerInvariant()
        if ($actual -ne $expected) { throw "Checksum mismatch: $relative. Do not install this USB bundle." }
    }
}

Write-Host 'Estate Pharmacy Management System - Offline Installer' -ForegroundColor Cyan
Write-Host 'Developed by WFd DeepTech Labs Private Limited'
Write-Host ''

Test-BundleChecksums
Write-Host 'USB bundle checksum verification passed.' -ForegroundColor Green

if (-not (Test-Path -LiteralPath $SourceApp -PathType Container)) { throw 'The app folder is missing from the USB bundle.' }
if (-not (Test-Path -LiteralPath $MySqlExe -PathType Leaf)) { throw "MySQL was not found at $MySqlExe. Install XAMPP from the USB first." }
if (-not (Test-Path -LiteralPath $PhpExe -PathType Leaf)) { throw "PHP was not found at $PhpExe. Install XAMPP from the USB first." }

if (Test-Path -LiteralPath $InstallPath) {
    $hasFiles = (Get-ChildItem -LiteralPath $InstallPath -Force -ErrorAction SilentlyContinue | Measure-Object).Count -gt 0
    if ($hasFiles -and -not $ForceInstall) {
        throw "Install path already contains files: $InstallPath. For safety, use a fresh folder or rerun with -ForceInstall only if you intend to replace the application files."
    }
}
New-Item -ItemType Directory -Force -Path $InstallPath | Out-Null
Copy-Item -Path (Join-Path $SourceApp '*') -Destination $InstallPath -Recurse -Force

$alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%_-'
$randomBytes = New-Object byte[] 32
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $rng.GetBytes($randomBytes)
} finally {
    $rng.Dispose()
}
$databasePassword = -join ($randomBytes | ForEach-Object { $alphabet[$_ % $alphabet.Length] })

$dbNameSql = $DatabaseName.Replace('`', '``')
$dbUserSql = Convert-ToSqlLiteral $DatabaseUser
$dbPasswordSql = Convert-ToSqlLiteral $databasePassword
$setupSql = @"
CREATE DATABASE IF NOT EXISTS ``$dbNameSql`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$dbUserSql'@'localhost' IDENTIFIED BY '$dbPasswordSql';
ALTER USER '$dbUserSql'@'localhost' IDENTIFIED BY '$dbPasswordSql';
GRANT SELECT,INSERT,UPDATE,DELETE ON ``$dbNameSql``.* TO '$dbUserSql'@'localhost';
FLUSH PRIVILEGES;
"@
Invoke-MySqlText $setupSql

Import-MySqlFile $DatabaseName (Join-Path $InstallPath 'database\schema.sql')
Import-MySqlFile $DatabaseName (Join-Path $InstallPath 'database\seed.sql')
Import-MySqlFile $DatabaseName (Join-Path $InstallPath 'database\audit_triggers.sql')

$configPath = Join-Path $InstallPath 'config\app.php'
$escapedPassword = $databasePassword.Replace('\', '\\').Replace("'", "\'")
$escapedDatabase = $DatabaseName.Replace('\', '\\').Replace("'", "\'")
$escapedUser = $DatabaseUser.Replace('\', '\\').Replace("'", "\'")
$config = @"
<?php
return [
    'app' => [
        'name' => 'Estate Pharmacy Management System',
        'developer' => 'WFd DeepTech Labs Private Limited',
        'timezone' => 'Asia/Kolkata',
        'session_timeout_minutes' => 30,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => '$escapedDatabase',
        'username' => '$escapedUser',
        'password' => '$escapedPassword',
    ],
];
"@
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($configPath, $config, $utf8NoBom)

Write-Host ''
Write-Host 'Offline application and database installed successfully.' -ForegroundColor Green
Write-Host "Application path: $InstallPath"
Write-Host "Database: $DatabaseName"
Write-Host ''
Write-Host 'NEXT:' -ForegroundColor Yellow
Write-Host '1. Create the first Admin account from Command Prompt:'
Write-Host "   `"$PhpExe`" `"$InstallPath\scripts\create_admin.php`" admin `"Pharmacy Administrator`" `"Choose-A-Strong-Password`""
Write-Host '2. Start Apache from the XAMPP Control Panel.'
Write-Host '3. Open http://localhost/Pharmacy_Management/public/ in the browser.'
Write-Host '4. Keep the USB bundle and SHA256SUMS.txt as the installation master copy.'
Write-Host ''
Write-Host 'No internet or network connection is required for normal operation.'
