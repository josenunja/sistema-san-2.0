# ==============================================================================
# SAS SAN - Script de Respaldo Desacoplado para el Cliente
# Versión: 2.0 - Entrega Comercial
# ==============================================================================
param(
    [ValidateSet('Auto','Docker','Nativo')][string]$Modo = 'Auto',
    [string]$Destino = '',
    [string]$CopiaSecundaria = '',
    [string]$KeyPath = '',
    [int]$RetencionDias = 30
)

$ErrorActionPreference = 'Stop'
$ScriptRoot = [IO.Path]::GetFullPath($PSScriptRoot)
$AppRoot = Join-Path $ScriptRoot 'app'
$LogFile = Join-Path $ScriptRoot 'registro_respaldo.log'
$EstadoFile = Join-Path $ScriptRoot 'ESTADO_ULTIMO_RESPALDO.txt'

function Escribir-Log {
    param([string]$Mensaje, [string]$Nivel = 'INFO')
    $timestamp = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    $linea = "[$timestamp] [$Nivel] $Mensaje"
    Write-Host $linea
    Add-Content -LiteralPath $LogFile -Value $linea -Encoding UTF8 -ErrorAction SilentlyContinue
}

function Actualizar-Estado {
    param([bool]$Exitoso, [string]$Detalle, [string]$Archivo = '')
    $fecha = (Get-Date).ToString('dd/MM/yyyy HH:mm:ss')
    $estadoTxt = if ($Exitoso) { "EXITOSO" } else { "ERROR" }
    $contenido = @"
============================================================
SAS SAN - ESTADO DEL ÚLTIMO RESPALDO
============================================================
Fecha y Hora : $fecha
Resultado    : $estadoTxt
Detalle      : $Detalle
Archivo      : $Archivo
============================================================
Este archivo se actualiza automáticamente en cada ejecución.
Conserve su clave backup.key fuera de este equipo.
"@
    Set-Content -LiteralPath $EstadoFile -Value $contenido -Encoding UTF8
    if ($Destino -and (Test-Path -LiteralPath $Destino)) {
        Set-Content -LiteralPath (Join-Path $Destino 'ESTADO_ULTIMO_RESPALDO.txt') -Value $contenido -Encoding UTF8 -ErrorAction SilentlyContinue
    }
}

Escribir-Log "Iniciando proceso de respaldo..."

try {
    # 1. Definir carpeta de destino principal
    if ([string]::IsNullOrWhiteSpace($Destino)) {
        $Destino = Join-Path $ScriptRoot 'respaldos'
    }
    $Destino = [IO.Path]::GetFullPath($Destino)
    if (!(Test-Path -LiteralPath $Destino)) {
        New-Item -ItemType Directory -Path $Destino -Force | Out-Null
        Escribir-Log "Carpeta de destino creada: $Destino"
    }

    # 2. Localizar archivo .env y credenciales
    $envPath = Join-Path $AppRoot '.env'
    if (!(Test-Path -LiteralPath $envPath)) {
        $envPath = Join-Path $AppRoot '.env.native'
    }
    if (!(Test-Path -LiteralPath $envPath)) {
        throw "No se encontró el archivo de configuración (.env o .env.native) en $AppRoot."
    }

    $envMap = @{}
    foreach ($line in Get-Content -LiteralPath $envPath) {
        if ($line -match '^\s*([A-Z_][A-Z0-9_]*)=(.*)$') {
            $key = $matches[1]
            $val = $matches[2].Trim().Trim('"').Trim("'")
            $envMap[$key] = $val
        }
    }

    $dbName = if ($envMap.ContainsKey('DB_DATABASE')) { $envMap['DB_DATABASE'] } else { 'sas_san' }
    $dbUser = if ($envMap.ContainsKey('DB_USERNAME')) { $envMap['DB_USERNAME'] } else { 'sas_owner' }
    $dbPass = if ($envMap.ContainsKey('DB_PASSWORD')) { $envMap['DB_PASSWORD'] } else { '' }
    $dbHost = if ($envMap.ContainsKey('DB_HOST')) { $envMap['DB_HOST'] } else { '127.0.0.1' }
    $dbPort = if ($envMap.ContainsKey('DB_PORT')) { $envMap['DB_PORT'] } else { '5432' }

    # 3. Detectar modo de ejecución si está en Auto
    if ($Modo -eq 'Auto') {
        if (Test-Path -LiteralPath (Join-Path $ScriptRoot '.runtime\php\php.exe')) {
            $Modo = 'Nativo'
        } elseif (Get-Command 'docker' -ErrorAction SilentlyContinue) {
            $Modo = 'Docker'
        } elseif (Get-Command 'php' -ErrorAction SilentlyContinue) {
            $Modo = 'Nativo'
        } else {
            throw "No se pudo detectar automáticamente el entorno (Docker o PHP/PostgreSQL no encontrados)."
        }
    }
    Escribir-Log "Modo seleccionado: $Modo"

    # 4. Localizar o crear la clave de cifrado
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
        if ([string]::IsNullOrWhiteSpace($KeyPath)) {
            $clavesDir = Join-Path $ScriptRoot 'claves'
            if (!(Test-Path -LiteralPath $clavesDir)) { New-Item -ItemType Directory -Path $clavesDir -Force | Out-Null }
            $KeyPath = Join-Path $clavesDir 'backup.key'
        }
    }
    $KeyPath = [IO.Path]::GetFullPath($KeyPath)
    Escribir-Log "Ubicación de clave de cifrado: $KeyPath"

    # 5. Crear área de trabajo temporal limpia
    $tempWork = Join-Path $AppRoot ('storage\app\backup-work-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
    New-Item -ItemType Directory -Path $tempWork -Force | Out-Null

    $dbDumpFile = Join-Path $tempWork 'database.dump'
    $envDumpFile = Join-Path $tempWork 'config.env'
    $privateTarget = Join-Path $tempWork 'private'

    # Copiar configuración .env
    Copy-Item -LiteralPath $envPath -Destination $envDumpFile -Force

    # Copiar archivos privados (fotos, comprobantes, etc.)
    $privateSource = Join-Path $AppRoot 'storage\app\private'
    if (Test-Path -LiteralPath $privateSource) {
        Copy-Item -LiteralPath $privateSource -Destination $privateTarget -Recurse -Force
    }

    # 6. Ejecutar volcado de base de datos según el modo
    Escribir-Log "Ejecutando volcado de base de datos ($dbName)..."

    if ($Modo -eq 'Docker') {
        Push-Location $AppRoot
        try {
            # Se ejecuta pg_dump en el contenedor db hacia una ruta de volumen montado
            $relWork = [IO.Path]::GetRelativePath($AppRoot, $tempWork).Replace('\', '/')
            $containerDumpPath = "/var/www/html/$relWork/database.dump"
            
            # Ejecutamos pg_dump usando sas_owner
            $dumpCmd = "pg_dump -U sas_owner -d $dbName -F c -f $containerDumpPath"
            docker compose exec -T -e PGPASSWORD=$dbPass db sh -c $dumpCmd
            if ($LASTEXITCODE -ne 0) {
                # Alternativa si el contenedor corre bajo otro usuario o parámetros
                docker compose run --rm -e PGPASSWORD=$dbPass db pg_dump -h db -U sas_owner -d $dbName -F c -f $containerDumpPath
                if ($LASTEXITCODE -ne 0) { throw "Fallo al ejecutar pg_dump en contenedor Docker." }
            }
        } finally {
            Pop-Location
        }
    } else {
        # Modo Nativo / Portable
        $runtime = Join-Path $ScriptRoot '.runtime'
        $pgDump = if (Test-Path -LiteralPath (Join-Path $runtime 'pg18\pgsql\bin\pg_dump.exe')) {
            Join-Path $runtime 'pg18\pgsql\bin\pg_dump.exe'
        } else {
            'pg_dump'
        }

        $runtimePassFile = Join-Path $runtime 'pg-password.txt'
        if ([string]::IsNullOrWhiteSpace($dbPass) -and (Test-Path -LiteralPath $runtimePassFile)) {
            $dbPass = [IO.File]::ReadAllText($runtimePassFile).Trim()
        }

        $env:PGPASSWORD = $dbPass
        & $pgDump -h $dbHost -p $dbPort -U $dbUser -d $dbName -F c -f $dbDumpFile
        if ($LASTEXITCODE -ne 0) {
            throw "pg_dump devolvió código de error $LASTEXITCODE."
        }
    }

    if (!(Test-Path -LiteralPath $dbDumpFile) -or (Get-Item -LiteralPath $dbDumpFile).Length -eq 0) {
        throw "El archivo de volcado database.dump no se generó o está vacío."
    }

    # 7. Empaquetar payload.zip
    Escribir-Log "Empaquetando archivos del respaldo..."
    $zipFile = Join-Path $tempWork 'payload.zip'
    $itemsToZip = @($dbDumpFile, $envDumpFile)
    if (Test-Path -LiteralPath $privateTarget) { $itemsToZip += $privateTarget }
    Compress-Archive -LiteralPath $itemsToZip -DestinationPath $zipFile -Force

    # 8. Cifrado del respaldo (.ssbk) con backup-crypt.php
    Escribir-Log "Cifrando paquete con algoritmo autenticado (Libsodium)..."
    $cryptScript = Join-Path $AppRoot 'tools\backup-crypt.php'
    $timestamp = (Get-Date).ToString('yyyyMMdd-HHmmss')
    $randomSuffix = [guid]::NewGuid().ToString('N').Substring(0, 6)
    $finalBackupName = "$dbName-$timestamp-$randomSuffix.ssbk"
    $finalBackupPath = Join-Path $Destino $finalBackupName

    if ($Modo -eq 'Docker') {
        Push-Location $AppRoot
        try {
            $relZip = [IO.Path]::GetRelativePath($AppRoot, $zipFile).Replace('\', '/')
            $relOutput = [IO.Path]::GetRelativePath($AppRoot, $finalBackupPath).Replace('\', '/')
            $relKey = [IO.Path]::GetRelativePath($AppRoot, $KeyPath).Replace('\', '/')

            docker compose run --rm app php tools/backup-crypt.php encrypt "/var/www/html/$relZip" "/var/www/html/$relOutput" "/var/www/html/$relKey"
            if ($LASTEXITCODE -ne 0) { throw "Falló el cifrado en contenedor Docker." }
        } finally {
            Pop-Location
        }
    } else {
        $php = if (Test-Path -LiteralPath (Join-Path $runtime 'php\php.exe')) {
            Join-Path $runtime 'php\php.exe'
        } else {
            'php'
        }
        & $php $cryptScript encrypt $zipFile $finalBackupPath $KeyPath
        if ($LASTEXITCODE -ne 0) { throw "Falló el cifrado con backup-crypt.php." }
    }

    if (!(Test-Path -LiteralPath $finalBackupPath)) {
        throw "No se encontró el archivo cifrado generado en $finalBackupPath."
    }

    $tamanoMb = [math]::Round(((Get-Item -LiteralPath $finalBackupPath).Length / 1MB), 2)
    Escribir-Log "Respaldo generado exitosamente: $finalBackupName ($tamanoMb MB)"

    # 9. Copia Secundaria / Externa si se configuró (ej. USB o Cloud Drive)
    if (![string]::IsNullOrWhiteSpace($CopiaSecundaria)) {
        try {
            if (!(Test-Path -LiteralPath $CopiaSecundaria)) {
                New-Item -ItemType Directory -Path $CopiaSecundaria -Force | Out-Null
            }
            $secTargetPath = Join-Path $CopiaSecundaria $finalBackupName
            Copy-Item -LiteralPath $finalBackupPath -Destination $secTargetPath -Force
            Escribir-Log "Copia secundaria externa realizada en: $secTargetPath"
        } catch {
            Escribir-Log "ADVERTENCIA: No se pudo completar la copia secundaria: $($_.Exception.Message)" "WARN"
        }
    }

    # 10. Rotación y limpieza de respaldos antiguos (> RetencionDias)
    if ($RetencionDias -gt 0) {
        $fechaLimite = (Get-Date).AddDays(-$RetencionDias)
        $antiguos = Get-ChildItem -Path $Destino -Filter "$dbName-*.ssbk" | Where-Object { $_.LastWriteTime -lt $fechaLimite }
        foreach ($old in $antiguos) {
            Escribir-Log "Eliminando respaldo antiguo según retención de $RetencionDias días: $($old.Name)"
            Remove-Item -LiteralPath $old.FullName -Force -ErrorAction SilentlyContinue
        }
    }

    # 11. Actualizar estado exitoso
    Actualizar-Estado -Exitoso $true -Detalle "Respaldo generado exitosamente ($tamanoMb MB). Retención: $RetencionDias días." -Archivo $finalBackupPath
    Escribir-Log "Proceso de respaldo concluido con éxito."
    Write-Output $finalBackupPath
    exit 0

} catch {
    $err = $_.Exception.Message
    Escribir-Log "ERROR FATAL: $err" "ERROR"
    Actualizar-Estado -Exitoso $false -Detalle "ERROR: $err"
    exit 1
} finally {
    # Limpieza de archivos temporales
    if ($tempWork -and (Test-Path -LiteralPath $tempWork)) {
        Remove-Item -LiteralPath $tempWork -Recurse -Force -ErrorAction SilentlyContinue
    }
}
