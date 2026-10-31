param([string]$Configuracion='')
$ErrorActionPreference='Stop'
& (Join-Path $PSScriptRoot 'iniciar.ps1') -Configuracion $Configuracion
if($LASTEXITCODE -ne 0) { throw 'No se pudo iniciar el sistema.' }
