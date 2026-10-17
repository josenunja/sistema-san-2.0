param([switch]$Reutilizar)
$ErrorActionPreference='Stop'
Set-Location (Join-Path $PSScriptRoot 'app')
foreach($line in Get-Content -LiteralPath '.env.native'){
    if($line -match '^([A-Z_][A-Z0-9_]*)=(.*)$'){[Environment]::SetEnvironmentVariable($matches[1],$matches[2].Trim().Trim('"'),'Process')}
}
$env:APP_ENV='testing';$env:DB_DATABASE='sas_san_test';$env:DB_USERNAME='sas_runtime';$env:PRIVATE_STORAGE_ROOT=Join-Path $PSScriptRoot '.runtime\test-objects'
$access=Join-Path $PSScriptRoot '.runtime\demo-access.json'
if(!$Reutilizar -or !(Test-Path -LiteralPath $access)){
    & '..\.runtime\php\php.exe' artisan testing:demo $access
    if($LASTEXITCODE -ne 0){throw 'No se pudo preparar la demostración aislada.'}
}
if(Get-NetTCPConnection -LocalPort 8002 -State Listen -ErrorAction SilentlyContinue){Write-Output 'La demostración ya está disponible en http://localhost:8002'; return}
$server=Start-Process -FilePath (Join-Path $PSScriptRoot '.runtime\php\php.exe') -ArgumentList @('artisan','serve','--env=testing','--host=127.0.0.1','--port=8002','--tries=1') -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot '.runtime\test-web-out.log') -RedirectStandardError (Join-Path $PSScriptRoot '.runtime\test-web-error.log') -PassThru
$server.Id | Set-Content (Join-Path $PSScriptRoot '.runtime\test-server.pid')
Write-Output 'Demostración aislada en http://localhost:8002; credenciales aleatorias guardadas en el archivo privado de aceptación.'
