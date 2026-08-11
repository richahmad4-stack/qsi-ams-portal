$ErrorActionPreference = 'Stop'

$amsRoot = $PSScriptRoot
$startScript = Join-Path $amsRoot 'scripts\start-local.ps1'

if (-not (Test-Path $amsRoot)) {
    throw "AMS folder not found: $amsRoot"
}

if (-not (Test-Path $startScript)) {
    throw "AMS launcher not found: $startScript"
}

Write-Host 'Starting QSI AMS database and application...' -ForegroundColor Cyan
& $startScript

Start-Sleep -Seconds 3
Start-Process 'http://localhost:8080/login'

Write-Host ''
Write-Host 'QSI AMS is available at http://localhost:8080/login' -ForegroundColor Green
