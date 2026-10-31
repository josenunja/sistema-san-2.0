param([string]$Email='', [ValidateSet('Auto','Docker','Nativo')][string]$Modo='Auto', [string]$Configuracion='', [switch]$Limpiar2FA)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'scripts/entrega-comun.ps1')
$context=Get-SasContext $PSScriptRoot $Modo $Configuracion
$commandArgs=@('artisan','auth:emergency-reset')
if($Email) { $commandArgs+=$Email }
if($Limpiar2FA) { $commandArgs+='--clear-2fa' }
Push-Location $context.App
try {
    if($context.Mode -eq 'Docker') {
        throw 'Use recuperación desde la consola administrativa del contenedor local con su configuración de confianza. Este asistente solo recupera instalaciones nativas.'
    }
    foreach($name in $context.Map.Keys) { [Environment]::SetEnvironmentVariable($name,[string]$context.Map[$name],'Process') }
    $env:APP_ENV='native'
    & $context.Php @commandArgs
    if($LASTEXITCODE -ne 0) { throw 'La recuperación no se completó. No se proporcionan contraseñas por argumentos.' }
} finally { Pop-Location }
