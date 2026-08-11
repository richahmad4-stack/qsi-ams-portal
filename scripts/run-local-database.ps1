$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dataDir = Join-Path $root '.mysql-data'
$myIni = Join-Path $dataDir 'my.ini'
$mysqld = 'C:\xampp\mysql\bin\mysqld.exe'

if (-not (Test-Path $mysqld)) {
    throw "MariaDB executable not found: $mysqld"
}

if (-not (Test-Path $dataDir)) {
    throw "AMS database folder not found: $dataDir"
}

& $mysqld --defaults-file="$myIni" --datadir="$dataDir" --port=3307 --bind-address=127.0.0.1 --console
