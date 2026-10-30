# ==============================================================================
# SAS SAN - Programador Automático de Respaldos Diarios en Windows
# Versión: 2.0 - Entrega Comercial
# ==============================================================================
param(
    [string]$Hora = "22:00",
    [string]$Destino = "",
    [string]$CopiaSecundaria = "",
    [string]$TaskName = "SAS_SAN_Respaldo_Diario",
    [switch]$Eliminar
)

$ErrorActionPreference = 'Stop'
$ScriptRoot = [IO.Path]::GetFullPath($PSScriptRoot)
$RespaldoScript = Join-Path $ScriptRoot 'respaldo-cliente.ps1'

if ($Eliminar) {
    Write-Host "Eliminando tarea programada: $TaskName..."
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "Tarea '$TaskName' eliminada exitosamente." -ForegroundColor Green
    exit 0
}

if (!(Test-Path -LiteralPath $RespaldoScript)) {
    throw "No se encontró el script de respaldo en $RespaldoScript"
}

# Preparar argumentos
$argList = @("-ExecutionPolicy", "Bypass", "-WindowStyle", "Hidden", "-File", "`"$RespaldoScript`"")
if (![string]::IsNullOrWhiteSpace($Destino)) {
    $argList += @("-Destino", "`"$Destino`"")
}
if (![string]::IsNullOrWhiteSpace($CopiaSecundaria)) {
    $argList += @("-CopiaSecundaria", "`"$CopiaSecundaria`"")
}
$argString = $argList -join ' '

Write-Host "=========================================================="
Write-Host "Configurando Respaldo Automático Diario en Windows"
Write-Host "=========================================================="
Write-Host "Tarea      : $TaskName"
Write-Host "Hora       : $Hora (todos los días)"
Write-Host "Destino    : $(if($Destino){$Destino}else{'Por defecto: ' + (Join-Path $ScriptRoot 'respaldos')})"
if ($CopiaSecundaria) {
    Write-Host "Secundario : $CopiaSecundaria"
}
Write-Host "=========================================================="

try {
    # 1. Definir la acción
    $action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $argString -WorkingDirectory $ScriptRoot

    # 2. Definir el disparador (Diario a la hora especificada)
    $triggerTime = [DateTime]::ParseExact($Hora, "HH:mm", [System.Globalization.CultureInfo]::InvariantCulture)
    $trigger = New-ScheduledTaskTrigger -Daily -At $triggerTime

    # 3. Configuraciones de energía y ejecución
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 2)

    # 4. Registrar la tarea (usa la cuenta del usuario actual)
    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Description "Respaldo diario automático de base de datos y archivos de SAS SAN." -Force | Out-Null

    Write-Host "¡ÉXITO! La tarea '$TaskName' ha sido programada para ejecutarse diariamente a las $Hora." -ForegroundColor Green
    Write-Host "Los registros y estado se guardarán automáticamente en ESTADO_ULTIMO_RESPALDO.txt."
} catch {
    Write-Error "Error al programar la tarea: $($_.Exception.Message)"
    Write-Host "Nota: Si PowerShell no se ejecutó como Administrador, intente abrir PowerShell como Administrador para registrar la tarea programada." -ForegroundColor Yellow
}
