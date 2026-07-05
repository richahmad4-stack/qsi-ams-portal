$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dataDir = Join-Path $root '.mysql-data'
$myIni = Join-Path $dataDir 'my.ini'
$mysqld = 'C:\xampp\mysql\bin\mysqld.exe'

& $mysqld --defaults-file="$myIni" --port=3307 --bind-address=127.0.0.1 --console
