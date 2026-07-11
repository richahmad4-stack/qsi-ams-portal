$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

# Keep PHP startup warnings from sending response headers before CodeIgniter
# initializes its database-backed session. PHP's built-in server inherits this
# scan directory when `spark serve` launches the request worker.
$env:PHP_INI_SCAN_DIR = $PSScriptRoot

php spark serve --host 127.0.0.1 --port 8080
