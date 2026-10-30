# ==============================================================================
# SAS SAN - Lanzador en Modo Soporte / Servidor Local
# ==============================================================================
$ErrorActionPreference = 'Stop'
$ScriptRoot = [IO.Path]::GetFullPath($PSScriptRoot)
$RuntimeDir = Join-Path $ScriptRoot '.runtime'
$AppDir = Join-Path $ScriptRoot 'app'

$php = Join-Path $RuntimeDir 'php\php.exe'
$pgBin = Join-Path $RuntimeDir 'pg18\pgsql\bin'
$pgData = Join-Path $RuntimeDir 'pgdata'
$postgresLog = Join-Path $RuntimeDir 'postgres.log'

# 1. Iniciar PostgreSQL
Write-Host "Iniciando PostgreSQL en 127.0.0.1:55432..."
$pgProcess = Start-Process -FilePath (Join-Path $pgBin 'postgres.exe') -ArgumentList @("-D", $pgData) -PassThru -WindowStyle Hidden

# Esperar que acepte conexiones
$ready = $false
for ($i = 0; $i -lt 15; $i++) {
    & (Join-Path $pgBin 'pg_isready.exe') -h 127.0.0.1 -p 55432
    if ($LASTEXITCODE -eq 0) {
        $ready = $true
        break
    }
    Start-Sleep -Seconds 1
}

if (!$ready) {
    throw "PostgreSQL no pudo iniciar en el puerto 55432."
}
Write-Host "PostgreSQL está listo y aceptando conexiones." -ForegroundColor Green

# 2. Iniciar Laravel Artisan Serve
Write-Host "Iniciando servidor web en http://localhost:8000..." -ForegroundColor Cyan
Set-Location $AppDir

& $php artisan serve --env=native --host=127.0.0.1 --port=8000
