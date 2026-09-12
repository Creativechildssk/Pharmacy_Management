param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\dist'),
    [string]$XamppInstaller = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if (-not [IO.Path]::IsPathRooted($OutputDirectory)) {
    $OutputDirectory = Join-Path $RepoRoot $OutputDirectory
}
New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
$OutputDirectory = (Resolve-Path -LiteralPath $OutputDirectory).Path

$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BundleName = "Pharmacy_Management_Offline_USB_$Timestamp"
$BundleRoot = Join-Path $OutputDirectory $BundleName
$AppRoot = Join-Path $BundleRoot 'app'
$VendorRoot = Join-Path $AppRoot 'public/assets/vendor'

function Assert-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required build command '$Name' was not found. Install it on the internet-connected build PC and retry."
    }
}

function Copy-RepositoryRuntime {
    param([string]$Source, [string]$Destination)

    $excludedTopLevel = @('.git', '.github', 'tests', 'dist', 'vendor')
    New-Item -ItemType Directory -Force -Path $Destination | Out-Null

    Get-ChildItem -LiteralPath $Source -Force | ForEach-Object {
        if ($excludedTopLevel -contains $_.Name) { return }
        Copy-Item -LiteralPath $_.FullName -Destination $Destination -Recurse -Force
    }

    $productionConfig = Join-Path $Destination 'config/app.php'
    if (Test-Path $productionConfig) {
        Remove-Item -LiteralPath $productionConfig -Force
    }

    foreach ($relative in @('storage/logs', 'storage/backups')) {
        $dir = Join-Path $Destination $relative
        if (Test-Path $dir) {
            Get-ChildItem -LiteralPath $dir -File -Force | Where-Object { $_.Name -ne '.gitkeep' } | Remove-Item -Force
        }
    }
}

function Download-File([string]$Url, [string]$Destination) {
    $parent = Split-Path -Parent $Destination
    New-Item -ItemType Directory -Force -Path $parent | Out-Null
    Invoke-WebRequest -Uri $Url -OutFile $Destination -UseBasicParsing
}

function Assert-BundleFile([string]$RelativePath) {
    $path = Join-Path $BundleRoot $RelativePath
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw "Offline bundle validation failed: missing $RelativePath"
    }
}

Assert-Command 'composer'

if (Test-Path $BundleRoot) { Remove-Item -LiteralPath $BundleRoot -Recurse -Force }
New-Item -ItemType Directory -Force -Path $BundleRoot | Out-Null
Copy-RepositoryRuntime -Source $RepoRoot -Destination $AppRoot

Write-Host 'Installing production Composer dependencies into the staged bundle...'
Push-Location $AppRoot
try {
    composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }
} finally {
    Pop-Location
}

Write-Host 'Downloading frontend assets for completely offline runtime...'
Download-File 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css' (Join-Path $VendorRoot 'bootstrap/bootstrap.min.css')
Download-File 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js' (Join-Path $VendorRoot 'bootstrap/bootstrap.bundle.min.js')
Download-File 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js' (Join-Path $VendorRoot 'sweetalert2/sweetalert2.all.min.js')
Download-File 'https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.min.css' (Join-Path $VendorRoot 'datatables/datatables.min.css')
Download-File 'https://cdn.datatables.net/2.3.4/js/dataTables.min.js' (Join-Path $VendorRoot 'datatables/datatables.min.js')

Copy-Item -LiteralPath (Join-Path $RepoRoot 'docs/OFFLINE_INSTALL.md') -Destination (Join-Path $BundleRoot 'OFFLINE_INSTALL.md') -Force
Copy-Item -LiteralPath (Join-Path $RepoRoot 'scripts/install_offline_windows.ps1') -Destination (Join-Path $BundleRoot 'INSTALL_OFFLINE_WINDOWS.ps1') -Force
Copy-Item -LiteralPath (Join-Path $RepoRoot 'scripts/INSTALL_OFFLINE_WINDOWS.bat') -Destination (Join-Path $BundleRoot 'INSTALL_OFFLINE_WINDOWS.bat') -Force

if ($XamppInstaller -ne '') {
    if (-not (Test-Path -LiteralPath $XamppInstaller -PathType Leaf)) {
        throw "XAMPP installer was not found: $XamppInstaller"
    }
    $installerDir = Join-Path $BundleRoot 'installer'
    New-Item -ItemType Directory -Force -Path $installerDir | Out-Null
    Copy-Item -LiteralPath $XamppInstaller -Destination (Join-Path $installerDir (Split-Path -Leaf $XamppInstaller)) -Force
}

$commit = ''
if (Get-Command git -ErrorAction SilentlyContinue) {
    try { $commit = (git -C $RepoRoot rev-parse HEAD 2>$null).Trim() } catch { $commit = '' }
}
$manifest = [ordered]@{
    product = 'Estate Pharmacy Management System'
    developer = 'WFd DeepTech Labs Private Limited'
    bundle = $BundleName
    built_at = (Get-Date).ToString('o')
    source_commit = $commit
    target_runtime = 'Windows + XAMPP/PHP/MySQL, fully offline'
}
$manifest | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $BundleRoot 'MANIFEST.json') -Encoding UTF8

foreach ($required in @(
    'app/vendor/autoload.php',
    'app/public/assets/vendor/bootstrap/bootstrap.min.css',
    'app/public/assets/vendor/bootstrap/bootstrap.bundle.min.js',
    'app/public/assets/vendor/sweetalert2/sweetalert2.all.min.js',
    'app/public/assets/vendor/datatables/datatables.min.css',
    'app/public/assets/vendor/datatables/datatables.min.js',
    'app/database/schema.sql',
    'app/database/seed.sql',
    'app/database/audit_triggers.sql',
    'OFFLINE_INSTALL.md',
    'INSTALL_OFFLINE_WINDOWS.ps1',
    'INSTALL_OFFLINE_WINDOWS.bat',
    'MANIFEST.json'
)) {
    Assert-BundleFile $required
}

if (Test-Path (Join-Path $AppRoot 'config/app.php')) { throw 'Production secret config/app.php must not be included.' }
foreach ($forbidden in @('.git', '.github', 'tests')) {
    if (Test-Path (Join-Path $AppRoot $forbidden)) { throw "Development metadata '$forbidden' must not be included." }
}

$checksumPath = Join-Path $BundleRoot 'SHA256SUMS.txt'
Get-ChildItem -LiteralPath $BundleRoot -Recurse -File |
    Where-Object { $_.FullName -ne $checksumPath } |
    Sort-Object FullName |
    ForEach-Object {
        $hash = Get-FileHash -Algorithm SHA256 -LiteralPath $_.FullName
        $relative = $_.FullName.Substring($BundleRoot.Length + 1).Replace('\', '/')
        "$($hash.Hash.ToLowerInvariant())  $relative"
    } | Set-Content -LiteralPath $checksumPath -Encoding ASCII

Assert-BundleFile 'SHA256SUMS.txt'

$zipPath = Join-Path $OutputDirectory "$BundleName.zip"
if (Test-Path $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
Compress-Archive -Path (Join-Path $BundleRoot '*') -DestinationPath $zipPath -CompressionLevel Optimal

Write-Host ''
Write-Host 'Offline USB bundle created successfully:' -ForegroundColor Green
Write-Host "  Folder: $BundleRoot"
Write-Host "  ZIP:    $zipPath"
Write-Host 'Copy the ZIP (or extracted folder) to the USB drive. The target pharmacy PC does not need internet access.'
