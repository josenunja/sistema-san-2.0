param([ValidateSet('Nativo','Docker')][string]$Modo='Nativo', [switch]$Compilar, [string]$Configuracion='')
$ErrorActionPreference='Stop'
$previousEnvironment=@{}
foreach($name in @('APP_ENV','DB_USERNAME','DB_PASSWORD')){$previousEnvironment[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
Push-Location (Join-Path $PSScriptRoot 'app')
try {
function Invoke-Checked([scriptblock]$Step){ & $Step; if($LASTEXITCODE -ne 0){throw 'No se pudo completar el inicio. Revise el mensaje anterior.'} }
if($Modo -eq 'Docker'){
    if(!(Test-Path -LiteralPath '.env')){
        $template=[IO.File]::ReadAllText((Join-Path (Get-Location) '.env.example'))
        $template=$template.Replace('DB_PASSWORD=', 'DB_PASSWORD='+[guid]::NewGuid().ToString('N'))
        [IO.File]::WriteAllText((Join-Path (Get-Location) '.env'),$template)
    }
    Invoke-Checked {docker compose build app}
    Invoke-Checked {docker compose up -d db}
    Invoke-Checked {docker compose run --rm app composer install --no-interaction --prefer-dist}
    if((Get-Content -LiteralPath '.env' -Raw) -match '(?m)^APP_KEY=\s*$'){
        Invoke-Checked {docker compose run --rm app php artisan key:generate}
    }
    Invoke-Checked {docker compose run --rm -e DB_USERNAME=sas_owner app php artisan migrate --force}
    Invoke-Checked {docker compose run --rm -e DB_USERNAME=sas_owner app php artisan platform:runtime}
    if($Compilar -or !(Test-Path -LiteralPath 'public\build\manifest.json')){Invoke-Checked {npm.cmd ci}; Invoke-Checked {npm.cmd run build}}
    Invoke-Checked {docker compose up -d app scheduler worker}
}else{
    $runtime=Join-Path $PSScriptRoot '.runtime'
    $php=Join-Path $runtime 'php\php.exe'
    $pg=Join-Path $runtime 'pg18\pgsql\bin'
    if(!$Configuracion){$Configuracion=Join-Path (Get-Location) '.env.native'}
    if(!(Test-Path -LiteralPath $php) -or !(Test-Path -LiteralPath $Configuracion)){throw 'Falta el entorno portátil o la configuración. Consulte README.md.'}
    foreach($line in Get-Content -LiteralPath $Configuracion){
        if($line -match '^([A-Z_][A-Z0-9_]*)=(.*)$'){
            if(!$previousEnvironment.ContainsKey($matches[1])){$previousEnvironment[$matches[1]]=[Environment]::GetEnvironmentVariable($matches[1],'Process')}
            [Environment]::SetEnvironmentVariable($matches[1],$matches[2].Trim().Trim('"'),'Process')
        }
    }
    if($env:APP_ENV -ne 'testing'){$env:APP_ENV='native'}
    if($env:DB_HOST -notin @('127.0.0.1','localhost')){throw 'El inicio local requiere PostgreSQL en loopback.'}
    $dbPort=[int]$env:DB_PORT
    $webPort=if($env:APP_PORT){[int]$env:APP_PORT}else{8000}
    $runtimePassword=$env:DB_PASSWORD
    & (Join-Path $pg 'pg_isready.exe') -h 127.0.0.1 -p $dbPort | Out-Null
    if($LASTEXITCODE -ne 0){Invoke-Checked { & (Join-Path $pg 'pg_ctl.exe') -D (Join-Path $runtime 'pgdata') -l (Join-Path $runtime 'postgres.log') -w start }}
    $env:DB_USERNAME='sas_owner'
    $env:DB_PASSWORD=[IO.File]::ReadAllText((Join-Path $runtime 'pg-password.txt')).Trim()
    $applicationEnvironment=$env:APP_ENV
    $env:APP_ENV='native'
    Invoke-Checked { & $php artisan migrate --env=native --force }
    $runtimeFile=Join-Path $runtime 'runtime-password.txt'
    if(Test-Path -LiteralPath $runtimeFile){Invoke-Checked { & $php artisan platform:runtime --env=native "--runtime-password-file=$runtimeFile" }}
    else{Invoke-Checked { & $php artisan platform:runtime --env=native }}
    $env:DB_USERNAME='sas_runtime'
    $env:DB_PASSWORD=$runtimePassword
    $env:APP_ENV=$applicationEnvironment
    if($Compilar){Invoke-Checked {npm.cmd ci}; Invoke-Checked {npm.cmd run build}}
    if(!(Test-Path -LiteralPath 'public\build\manifest.json')){Invoke-Checked {npm.cmd run build}}
    $webPid=Join-Path $runtime "web-$webPort.pid"
    if(Get-NetTCPConnection -LocalPort $webPort -State Listen -ErrorAction SilentlyContinue){
        $ownerProcess=if(Test-Path -LiteralPath $webPid){Get-CimInstance Win32_Process -Filter ('ProcessId='+[int](Get-Content $webPid)) -ErrorAction SilentlyContinue}else{$null}
        if(!$ownerProcess -and $webPort -eq 8000 -and (Test-Path -LiteralPath (Join-Path $runtime 'web.pid'))){$ownerProcess=Get-CimInstance Win32_Process -Filter ('ProcessId='+[int](Get-Content (Join-Path $runtime 'web.pid'))) -ErrorAction SilentlyContinue}
        if(!$ownerProcess -or $ownerProcess.ExecutablePath -ne $php){throw 'El puerto web está ocupado por otro proceso. No se detuvo ni reemplazó.'}
    }else{
        $server=Start-Process -FilePath $php -ArgumentList @('artisan','serve','--env=native','--no-reload','--host=127.0.0.1',"--port=$webPort",'--tries=1') -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime "web-$webPort-out.log") -RedirectStandardError (Join-Path $runtime "web-$webPort-error.log") -PassThru
        $server.Id | Set-Content $webPid
    }
    $schedulerPid=Join-Path $runtime "scheduler-$webPort.pid"
    if($webPort -eq 8000 -and !(Test-Path -LiteralPath $schedulerPid) -and (Test-Path -LiteralPath (Join-Path $runtime 'scheduler.pid'))){$schedulerPid=Join-Path $runtime 'scheduler.pid'}
    $running=if(Test-Path -LiteralPath $schedulerPid){Get-CimInstance Win32_Process -Filter ('ProcessId='+[int](Get-Content $schedulerPid)) -ErrorAction SilentlyContinue}else{$null}
    if($running -and ($running.ExecutablePath -ne $php -or $running.CommandLine -notmatch 'schedule:work')){$running=$null}
    if(!$running){
        $scheduler=Start-Process -FilePath $php -ArgumentList @('artisan','schedule:work','--env=native') -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'scheduler-out.log') -RedirectStandardError (Join-Path $runtime 'scheduler-error.log') -PassThru
        $scheduler.Id | Set-Content $schedulerPid
    }
    $workerPid=Join-Path $runtime "worker-$webPort.pid"
    $worker=if(Test-Path -LiteralPath $workerPid){Get-CimInstance Win32_Process -Filter ('ProcessId='+[int](Get-Content $workerPid)) -ErrorAction SilentlyContinue}else{$null}
    if(!$worker -or $worker.ExecutablePath -ne $php -or $worker.CommandLine -notmatch 'queue:work'){
        $worker=Start-Process -FilePath $php -ArgumentList @('artisan','queue:work','--env=native','--sleep=3','--tries=3','--timeout=90') -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime "worker-$webPort-out.log") -RedirectStandardError (Join-Path $runtime "worker-$webPort-error.log") -PassThru
        $worker.Id | Set-Content $workerPid
    }
}
$publicUrl='http://localhost:8000'
if($Modo -eq 'Docker'){
    $urlLine=Get-Content -LiteralPath '.env' | Where-Object {$_ -match '^APP_URL='} | Select-Object -First 1
    if($urlLine){$publicUrl=($urlLine -replace '^APP_URL=','').Trim().Trim('"')}
}elseif($env:APP_URL){$publicUrl=$env:APP_URL}
Write-Host "Sistema: $publicUrl - Configuracion inicial en $publicUrl/instalacion."
} finally {
    foreach($name in $previousEnvironment.Keys){
        if($null -eq $previousEnvironment[$name]){Remove-Item -LiteralPath ('Env:'+$name) -ErrorAction SilentlyContinue}
        else{[Environment]::SetEnvironmentVariable($name,$previousEnvironment[$name],'Process')}
    }
    Pop-Location
}
