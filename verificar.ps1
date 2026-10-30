param([string]$Filter = 'Tenant|Commerce|Offline|Platform|Catalog|Customer|Fiscal|StoreOnboarding|SingleBusiness|JornadaCompleta')
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot 'app')
foreach ($line in Get-Content -LiteralPath '.env.native') {
    if ($line -match '^([A-Z_][A-Z0-9_]*)=(.*)$') {
        [Environment]::SetEnvironmentVariable($matches[1], $matches[2].Trim().Trim('"'), 'Process')
    }
}
$env:APP_ENV = 'testing'
$env:BUSINESS_MODE = 'platform'
$env:DB_DATABASE = 'sas_san_test'
$env:DB_USERNAME = 'sas_runtime'
$env:PRIVATE_STORAGE_ROOT = Join-Path $PSScriptRoot '.runtime\test-objects'
& '..\.runtime\php\php.exe' artisan test "--filter=$Filter"
if ($LASTEXITCODE -ne 0) { throw 'Las pruebas fallaron.' }
& '..\.runtime\php\php.exe' artisan testing:stock-race
if ($LASTEXITCODE -ne 0) { throw 'La prueba de concurrencia falló.' }
& '..\.runtime\php\php.exe' artisan testing:operation-races
if ($LASTEXITCODE -ne 0) { throw 'Falló una carrera de cupón, cobro, devolución u offline.' }
