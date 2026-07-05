$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dbScript = Join-Path $PSScriptRoot 'run-local-database.ps1'
$appScript = Join-Path $PSScriptRoot 'run-local-app.ps1'

Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $dbScript) -WorkingDirectory $root -WindowStyle Hidden
Start-Sleep -Seconds 6
Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $appScript) -WorkingDirectory $root -WindowStyle Hidden

Write-Host 'QSI AMS local database and app server are starting.'
Write-Host 'Open http://localhost:8080/login'
