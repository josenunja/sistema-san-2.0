param([string]$Configuracion='', [switch]$DetenerBase)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'scripts/entrega-comun.ps1')
$context=Get-SasContext $PSScriptRoot 'Nativo' $Configuracion
$port=[int]$context.Map['APP_PORT']
foreach($service in @('web','scheduler','worker')) {
    $pidPath=Join-Path $context.Root ('.runtime/'+$service+'-'+$port+'.pid')
    if(!(Test-Path -LiteralPath $pidPath)) { continue }
    $serviceId=[int](Get-Content -LiteralPath $pidPath)
    $process=Get-CimInstance Win32_Process -Filter ('ProcessId='+$serviceId) -ErrorAction SilentlyContinue
    if($process) {
        if($process.ExecutablePath -ne $context.Php -or $process.CommandLine -notmatch ('artisan.*'+$(switch($service){'web'{'serve'};'scheduler'{'schedule:work'};'worker'{'queue:work'}}))) { throw 'El PID no pertenece al servicio esperado. No se detuvo.' }
        Invoke-SasChecked 'taskkill.exe' @('/PID',[string]$serviceId,'/T','/F') | Out-Null
    }
}
if($DetenerBase) { Invoke-SasChecked (Join-Path $context.PgBin 'pg_ctl.exe') @('-D',(Join-Path $context.Root '.runtime/pgdata'),'-m','fast','-w','stop') }
Write-Host 'Servicios de esta instalación detenidos; datos conservados.'
