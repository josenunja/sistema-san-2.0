# ==============================================================================
# SAS SAN - Ensamblador Automático del Entorno Portátil (.runtime)
# ==============================================================================
$ErrorActionPreference = 'Stop'
$ScriptRoot = [IO.Path]::GetFullPath($PSScriptRoot)
$RuntimeDir = Join-Path $ScriptRoot '.runtime'
$AppDir = Join-Path $ScriptRoot 'app'

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Iniciando preparación del entorno portátil..." -ForegroundColor Cyan
Write-Host "Ubicación: $RuntimeDir" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

if (!(Test-Path -LiteralPath $RuntimeDir)) {
    New-Item -ItemType Directory -Path $RuntimeDir -Force | Out-Null
}

$phpDir = Join-Path $RuntimeDir 'php'
$phpExe = Join-Path $phpDir 'php.exe'

# -----------------------------------------------------------------------------
# 3. Descargar y preparar PostgreSQL 18 Portátil
# -----------------------------------------------------------------------------
$pgRoot = Join-Path $RuntimeDir 'pg18'
$pgBin = Join-Path $pgRoot 'pgsql\bin'
$pgCtl = Join-Path $pgBin 'pg_ctl.exe'
$pgZip = Join-Path $RuntimeDir 'pg18.zip'

if (!(Test-Path -LiteralPath $pgCtl)) {
    if (!(Test-Path -LiteralPath $pgZip) -or (Get-Item -LiteralPath $pgZip).Length -lt 300MB) {
        Write-Host "[3/6] Descargando binarios portátiles de PostgreSQL 18 con curl..." -ForegroundColor Yellow
        $pgUrl = "https://get.enterprisedb.com/postgresql/postgresql-18.1-1-windows-x64-binaries.zip"
        & curl.exe -L -C - -o $pgZip $pgUrl --progress-bar
    }
    Write-Host "Descomprimiendo PostgreSQL 18..." -ForegroundColor Yellow
    if (!(Test-Path -LiteralPath $pgRoot)) { New-Item -ItemType Directory -Path $pgRoot -Force | Out-Null }
    & tar.exe -xf $pgZip -C $pgRoot
    if ($LASTEXITCODE -ne 0) {
        Expand-Archive -LiteralPath $pgZip -DestinationPath $pgRoot -Force
    }
    Remove-Item -LiteralPath $pgZip -Force -ErrorAction SilentlyContinue
    Write-Host "PostgreSQL 18 listo en $pgBin." -ForegroundColor Green
} else {
    Write-Host "[3/6] PostgreSQL 18 ya presente." -ForegroundColor Green
}

# -----------------------------------------------------------------------------
# 4. Inicializar Cluster de Base de Datos y Contraseñas
# -----------------------------------------------------------------------------
$pgData = Join-Path $RuntimeDir 'pgdata'
$passFile = Join-Path $RuntimeDir 'pg-password.txt'
if (!(Test-Path -LiteralPath $passFile)) {
    $dbPass = [guid]::NewGuid().ToString('N')
    Set-Content -LiteralPath $passFile -Value $dbPass -Encoding ASCII
}
$dbPass = (Get-Content -LiteralPath $passFile).Trim()

if (!(Test-Path -LiteralPath (Join-Path $pgData 'PG_VERSION'))) {
    Write-Host "[4/6] Inicializando cluster de base de datos (initdb)..." -ForegroundColor Yellow
    $initDb = Join-Path $pgBin 'initdb.exe'
    & $initDb -D $pgData -U sas_owner --pwfile=$passFile -E UTF8 --locale=C
    if ($LASTEXITCODE -ne 0) { throw "Fallo al inicializar base de datos con initdb." }

    # Configurar puerto 55432 y localhost
    $confFile = Join-Path $pgData 'postgresql.conf'
    Add-Content -LiteralPath $confFile -Value "`nport = 55432`nlisten_addresses = '127.0.0.1'`nmax_connections = 100`n" -Encoding ASCII
}

# Iniciar servidor PostgreSQL si no está activo
$postgresLog = Join-Path $RuntimeDir 'postgres.log'
$env:PGPASSWORD = $dbPass
& (Join-Path $pgBin 'pg_isready.exe') -h 127.0.0.1 -p 55432
if ($LASTEXITCODE -ne 0) {
    Write-Host "Iniciando motor PostgreSQL en puerto 55432..." -ForegroundColor Yellow
    & $pgCtl -D $pgData -l $postgresLog -w start
}

# Crear bases de datos si no existen
$createdb = Join-Path $pgBin 'createdb.exe'
$psql = Join-Path $pgBin 'psql.exe'

try { & $createdb -h 127.0.0.1 -p 55432 -U sas_owner sas_san 2>$null } catch {}
try { & $createdb -h 127.0.0.1 -p 55432 -U sas_owner sas_san_test 2>$null } catch {}

# Crear rol sas_runtime
$runtimeRoleSql = "DO `$\$ BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'sas_runtime') THEN CREATE ROLE sas_runtime WITH LOGIN PASSWORD '$dbPass'; END IF; END `$\$;"
& $psql -h 127.0.0.1 -p 55432 -U sas_owner -d sas_san -c $runtimeRoleSql

# -----------------------------------------------------------------------------
# 5. Generar .env.native y Claves
# -----------------------------------------------------------------------------
Write-Host "[5/6] Configurando archivo .env.native y claves criptográficas..." -ForegroundColor Yellow
$backupKey = Join-Path $RuntimeDir 'backup.key'
if (!(Test-Path -LiteralPath $backupKey)) {
    & $phpExe -r "file_put_contents('$($backupKey.Replace('\','/'))', sodium_crypto_secretstream_xchacha20poly1305_keygen());"
}

$envNative = Join-Path $AppDir '.env.native'
if (!(Test-Path -LiteralPath $envNative)) {
    $envContent = @"
APP_NAME="SAS SAN"
APP_ENV=native
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_PORT=8000
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=sas_san
DB_USERNAME=sas_runtime
DB_PASSWORD=$dbPass
SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=file
QUEUE_CONNECTION=database
MAIL_MAILER=log
FILESYSTEM_DISK=local
VITE_APP_NAME="SAS SAN"
BUSINESS_MODE=single
BUSINESS_SLUG=demo
BUSINESS_NAME="Boutique San"
BUSINESS_TYPE=fashion
COMPOSE_PROJECT_NAME=sas-san-native
"@
    Set-Content -LiteralPath $envNative -Value $envContent -Encoding UTF8
    
    # Generar APP_KEY
    Push-Location $AppDir
    try {
        & $phpExe artisan key:generate --env=native
    } finally {
        Pop-Location
    }
}

# -----------------------------------------------------------------------------
# 6. Ejecutar Migraciones de Base de Datos y Compilar Frontend
# -----------------------------------------------------------------------------
Write-Host "[6/6] Ejecutando migraciones iniciales de base de datos..." -ForegroundColor Yellow
Push-Location $AppDir
try {
    $env:DB_USERNAME = 'sas_owner'
    $env:DB_PASSWORD = $dbPass
    & $phpExe artisan migrate --env=native --force
    & $phpExe artisan platform:runtime --env=native
} finally {
    Pop-Location
}

Write-Host "Compilando frontend (Vite)..." -ForegroundColor Yellow
Push-Location $AppDir
try {
    npm.cmd run build
} finally {
    Pop-Location
}

Write-Host "==========================================================" -ForegroundColor Green
Write-Host "¡ENTORNO PORTÁTIL ENSAMBLADO Y LISTO!" -ForegroundColor Green
Write-Host "Ahora el sistema puede iniciarse con: .\iniciar.ps1" -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
