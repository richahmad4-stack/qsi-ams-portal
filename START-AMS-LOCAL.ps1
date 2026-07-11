$ErrorActionPreference = 'Stop'

$amsRoot = 'C:\Users\pc\Documents\Codex\qsi-ams-portal'
$databaseScript = Join-Path $amsRoot 'scripts\run-local-database.ps1'
$appScript = Join-Path $amsRoot 'scripts\run-local-app.ps1'

if (-not (Test-Path $amsRoot)) {
    throw "AMS folder not found: $amsRoot"
}

if (-not (Test-Path $databaseScript)) {
    throw "AMS database launcher not found: $databaseScript"
}

Write-Host 'Starting QSI AMS MySQL on 127.0.0.1:3308...' -ForegroundColor Cyan
Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $databaseScript) -WorkingDirectory $amsRoot

Start-Sleep -Seconds 6

Write-Host 'Starting QSI AMS on http://127.0.0.1:8080/login ...' -ForegroundColor Cyan
Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $appScript) -WorkingDirectory $amsRoot

Start-Sleep -Seconds 3
Start-Process 'http://127.0.0.1:8080/login'

Write-Host ''
Write-Host 'Keep both command windows open while using AMS.' -ForegroundColor Yellow
