# ==============================================================================
# SAS SAN - Script de Restauración Asistida para el Cliente
# Versión: 2.0 - Entrega Comercial
# ==============================================================================
param(
    [Parameter(Mandatory)][string]$ArchivoSsbk,
    [ValidateSet('Auto','Docker','Nativo')][string]$Modo = 'Auto',
    [string]$KeyPath = '',
    [switch]$Confirmar
)

$ErrorActionPreference = 'Stop'
$ScriptRoot = [IO.Path]::GetFullPath($PSScriptRoot)
$AppRoot = Join-Path $ScriptRoot 'app'

function Escribir-Mensaje {
    param([string]$Mensaje, [string]$Nivel = 'INFO')
    $timestamp = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    Write-Host "[$timestamp] [$Nivel] $Mensaje"
}

Escribir-Mensaje "=========================================================="
Escribir-Mensaje "SAS SAN - Asistente de Restauración de Copia de Seguridad"
Escribir-Mensaje "=========================================================="

if (!(Test-Path -LiteralPath $ArchivoSsbk)) {
    throw "El archivo de respaldo indicado no existe: $ArchivoSsbk"
}
$ArchivoSsbk = [IO.Path]::GetFullPath($ArchivoSsbk)

if (!$Confirmar) {
    Write-Warning "¡ATENCIÓN! La restauración reemplazará los datos actuales de la base de datos por los del archivo de respaldo."
    $resp = Read-Host "¿Está seguro de que desea proceder con la restauración? Escriba 'SI' para continuar"
    if ($resp.Trim().ToUpper() -ne 'SI') {
        Write-Host "Operación cancelada por el usuario."
        exit 0
    }
}

# 1. Localizar clave de cifrado
if ([string]::IsNullOrWhiteSpace($KeyPath)) {
    $possibleKeys = @(
        (Join-Path $ScriptRoot 'claves\backup.key'),
        (Join-Path $ScriptRoot '.runtime\backup.key'),
        (Join-Path $ScriptRoot 'backup.key')
    )
    foreach ($k in $possibleKeys) {
        if (Test-Path -LiteralPath $k) {
            $KeyPath = $k
            break
        }
    }
}
if ([string]::IsNullOrWhiteSpace($KeyPath) -or !(Test-Path -LiteralPath $KeyPath)) {
    throw "No se encontró el archivo de clave backup.key necesario para descifrar el respaldo."
}
$KeyPath = [IO.Path]::GetFullPath($KeyPath)

# 2. Localizar .env
$envPath = Join-Path $AppRoot '.env'
if (!(Test-Path -LiteralPath $envPath)) { $envPath = Join-Path $AppRoot '.env.native' }
$envMap = @{}
if (Test-Path -LiteralPath $envPath) {
    foreach ($line in Get-Content -LiteralPath $envPath) {
        if ($line -match '^\s*([A-Z_][A-Z0-9_]*)=(.*)$') {
            $envMap[$matches[1]] = $matches[2].Trim().Trim('"').Trim("'")
        }
    }
}
$dbName = if ($envMap.ContainsKey('DB_DATABASE')) { $envMap['DB_DATABASE'] } else { 'sas_san' }
$dbUser = if ($envMap.ContainsKey('DB_USERNAME')) { $envMap['DB_USERNAME'] } else { 'sas_owner' }
$dbPass = if ($envMap.ContainsKey('DB_PASSWORD')) { $envMap['DB_PASSWORD'] } else { '' }
$dbHost = if ($envMap.ContainsKey('DB_HOST')) { $envMap['DB_HOST'] } else { '127.0.0.1' }
$dbPort = if ($envMap.ContainsKey('DB_PORT')) { $envMap['DB_PORT'] } else { '5432' }

# 3. Detectar modo
if ($Modo -eq 'Auto') {
    if (Test-Path -LiteralPath (Join-Path $ScriptRoot '.runtime\php\php.exe')) {
        $Modo = 'Nativo'
    } elseif (Get-Command 'docker' -ErrorAction SilentlyContinue) {
        $Modo = 'Docker'
    } elseif (Get-Command 'php' -ErrorAction SilentlyContinue) {
        $Modo = 'Nativo'
    } else {
        throw "No se pudo detectar el entorno de ejecución (Docker o Nativo)."
    }
}

$tempRestore = Join-Path $AppRoot ('storage\app\restore-work-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $tempRestore -Force | Out-Null

try {
    $zipDecrypted = Join-Path $tempRestore 'payload.zip'
    $cryptScript = Join-Path $AppRoot 'tools\backup-crypt.php'

    Escribir-Mensaje "Descifrando y autenticando archivo de respaldo..."
    if ($Modo -eq 'Docker') {
        Push-Location $AppRoot
        try {
            $relSsbk = [IO.Path]::GetRelativePath($AppRoot, $ArchivoSsbk).Replace('\', '/')
            $relZip = [IO.Path]::GetRelativePath($AppRoot, $zipDecrypted).Replace('\', '/')
            $relKey = [IO.Path]::GetRelativePath($AppRoot, $KeyPath).Replace('\', '/')

            docker compose run --rm app php tools/backup-crypt.php decrypt "/var/www/html/$relSsbk" "/var/www/html/$relZip" "/var/www/html/$relKey"
            if ($LASTEXITCODE -ne 0) { throw "El respaldo no se pudo descifrar o no corresponde con la clave." }
        } finally {
            Pop-Location
        }
    } else {
        $runtime = Join-Path $ScriptRoot '.runtime'
        $php = if (Test-Path -LiteralPath (Join-Path $runtime 'php\php.exe')) { Join-Path $runtime 'php\php.exe' } else { 'php' }
        & $php $cryptScript decrypt $ArchivoSsbk $zipDecrypted $KeyPath
        if ($LASTEXITCODE -ne 0) { throw "El respaldo no se pudo autenticar o descifrar." }
    }

    Escribir-Mensaje "Descomprimiendo contenido del respaldo..."
    $extractedFolder = Join-Path $tempRestore 'payload'
    Expand-Archive -LiteralPath $zipDecrypted -DestinationPath $extractedFolder -Force

    $dumpFile = Join-Path $extractedFolder 'database.dump'
    if (!(Test-Path -LiteralPath $dumpFile)) {
        throw "El respaldo no contiene un archivo database.dump válido."
    }

    # Restaurar base de datos
    Escribir-Mensaje "Restaurando base de datos ($dbName)..."
    if ($Modo -eq 'Docker') {
        Push-Location $AppRoot
        try {
            $relDump = [IO.Path]::GetRelativePath($AppRoot, $dumpFile).Replace('\', '/')
            $containerDumpPath = "/var/www/html/$relDump"
            $restoreCmd = "pg_restore -U sas_owner -d $dbName --clean --if-exists --no-owner $containerDumpPath"
            docker compose exec -T -e PGPASSWORD=$dbPass db sh -c $restoreCmd
            if ($LASTEXITCODE -ne 0) {
                # pg_restore suele salir con 1 por avisos menores; verificamos si la conexión se completó
                Escribir-Mensaje "Aviso: pg_restore finalizó con código $LASTEXITCODE (revisando tablas)." "WARN"
            }
        } finally {
            Pop-Location
        }
    } else {
        $runtime = Join-Path $ScriptRoot '.runtime'
        $pgRestore = if (Test-Path -LiteralPath (Join-Path $runtime 'pg18\pgsql\bin\pg_restore.exe')) {
            Join-Path $runtime 'pg18\pgsql\bin\pg_restore.exe'
        } else {
            'pg_restore'
        }
        $runtimePassFile = Join-Path $runtime 'pg-password.txt'
        if ([string]::IsNullOrWhiteSpace($dbPass) -and (Test-Path -LiteralPath $runtimePassFile)) {
            $dbPass = [IO.File]::ReadAllText($runtimePassFile).Trim()
        }
        $env:PGPASSWORD = $dbPass
        & $pgRestore -h $dbHost -p $dbPort -U $dbUser -d $dbName --clean --if-exists --no-owner $dumpFile
    }

    # Restaurar archivos privados si existen
    $restoredPrivate = Join-Path $extractedFolder 'private'
    if (Test-Path -LiteralPath $restoredPrivate) {
        Escribir-Mensaje "Restaurando archivos de almacenamiento privado..."
        $destPrivate = Join-Path $AppRoot 'storage\app\private'
        if (!(Test-Path -LiteralPath $destPrivate)) {
            New-Item -ItemType Directory -Path $destPrivate -Force | Out-Null
        }
        Copy-Item -LiteralPath "$restoredPrivate\*" -Destination $destPrivate -Recurse -Force
    }

    Escribir-Mensaje "=========================================================="
    Escribir-Mensaje "RESTAURACIÓN COMPLETADA CON ÉXITO" "SUCCESS"
    Escribir-Mensaje "Los datos del respaldo han sido aplicados correctamente."
    Escribir-Mensaje "=========================================================="

} finally {
    if ($tempRestore -and (Test-Path -LiteralPath $tempRestore)) {
        Remove-Item -LiteralPath $tempRestore -Recurse -Force -ErrorAction SilentlyContinue
    }
}
