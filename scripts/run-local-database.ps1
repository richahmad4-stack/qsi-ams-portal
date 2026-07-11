$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dataDir = Join-Path $root '.runtime\mysql-data'
$myIni = Join-Path $dataDir 'my.ini'
$mysqld = 'C:\xampp\mysql\bin\mysqld.exe'

& $mysqld --defaults-file="$myIni" --datadir="$dataDir" --port=3308 --bind-address=127.0.0.1 --console
