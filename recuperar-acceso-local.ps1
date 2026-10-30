# ==============================================================================
# SAS SAN - Script de Recuperación de Acceso de Emergencia (Sin SMTP)
# Versión: 2.0 - Entrega Comercial
# ==============================================================================
param(
    [string]$Email = '',
    [ValidateSet('Auto','Docker','Nativo')][string]$Modo = 'Auto',
    [switch]$Limpiar2FA
)

$ErrorActionPreference = 'Stop'
$ScriptRoot = [IO.Path]::GetFullPath($PSScriptRoot)
$AppRoot = Join-Path $ScriptRoot 'app'

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "SAS SAN - Asistente de Recuperación de Acceso Local" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Este procedimiento permite restablecer contraseñas o el 2FA"
Write-Host "directamente en este equipo sin requerir servicio de correo."
Write-Host ""

if ($Modo -eq 'Auto') {
    if (Test-Path -LiteralPath (Join-Path $ScriptRoot '.runtime\php\php.exe')) {
        $Modo = 'Nativo'
    } elseif (Get-Command 'docker' -ErrorAction SilentlyContinue) {
        $Modo = 'Docker'
    } elseif (Get-Command 'php' -ErrorAction SilentlyContinue) {
        $Modo = 'Nativo'
    } else {
        throw "No se encontró el entorno de ejecución (Docker o PHP)."
    }
}

$cmdArgs = @("artisan", "auth:emergency-reset")
if (![string]::IsNullOrWhiteSpace($Email)) {
    $cmdArgs += $Email
}
if ($Limpiar2FA) {
    $cmdArgs += "--clear-2fa"
}

if ($Modo -eq 'Docker') {
    Push-Location $AppRoot
    try {
        docker compose run --rm app php @cmdArgs
    } finally {
        Pop-Location
    }
} else {
    $runtime = Join-Path $ScriptRoot '.runtime'
    $php = if (Test-Path -LiteralPath (Join-Path $runtime 'php\php.exe')) {
        Join-Path $runtime 'php\php.exe'
    } else {
        'php'
    }
    
    Push-Location $AppRoot
    try {
        & $php @cmdArgs
    } finally {
        Pop-Location
    }
}
