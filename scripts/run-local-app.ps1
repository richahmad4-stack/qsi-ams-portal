$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

php spark serve --host 127.0.0.1 --port 8080
