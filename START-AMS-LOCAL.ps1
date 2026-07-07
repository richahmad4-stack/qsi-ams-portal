$ErrorActionPreference = 'Stop'

$amsRoot = 'C:\Users\PCD\Documents\AMS'
$xamppRoot = 'C:\xampp'
$mysqlStart = Join-Path $xamppRoot 'mysql_start.bat'

if (-not (Test-Path $amsRoot)) {
    throw "AMS folder not found: $amsRoot"
}

if (-not (Test-Path $mysqlStart)) {
    throw "XAMPP MySQL launcher not found: $mysqlStart"
}

Write-Host 'Starting XAMPP MySQL...' -ForegroundColor Cyan
Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', "cd /d `"$xamppRoot`" && `"$mysqlStart`""

Start-Sleep -Seconds 6

Write-Host 'Starting QSI AMS on http://127.0.0.1:8080/login ...' -ForegroundColor Cyan
Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', "cd /d `"$amsRoot`" && php spark serve --host 127.0.0.1 --port 8080"

Start-Sleep -Seconds 3
Start-Process 'http://127.0.0.1:8080/login'

Write-Host ''
Write-Host 'Keep both command windows open while using AMS.' -ForegroundColor Yellow
