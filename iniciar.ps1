param([ValidateSet('Nativo','Docker')][string]$Modo='Nativo', [switch]$Compilar)
$ErrorActionPreference='Stop'
Set-Location (Join-Path $PSScriptRoot 'app')
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
    if(!(Test-Path -LiteralPath $php) -or !(Test-Path -LiteralPath '.env.native')){throw 'Falta el entorno portátil o .env.native. Consulte README.md.'}
    foreach($line in Get-Content -LiteralPath '.env.native'){
        if($line -match '^([A-Z_][A-Z0-9_]*)=(.*)$'){[Environment]::SetEnvironmentVariable($matches[1],$matches[2].Trim().Trim('"'),'Process')}
    }
    $env:APP_ENV='native'
    $runtimePassword=$env:DB_PASSWORD
    & (Join-Path $pg 'pg_isready.exe') -h 127.0.0.1 -p 55432 | Out-Null
    if($LASTEXITCODE -ne 0){Invoke-Checked { & (Join-Path $pg 'pg_ctl.exe') -D (Join-Path $runtime 'pgdata') -l (Join-Path $runtime 'postgres.log') -w start }}
    $env:DB_USERNAME='sas_owner'
    $env:DB_PASSWORD=[IO.File]::ReadAllText((Join-Path $runtime 'pg-password.txt')).Trim()
    Invoke-Checked { & $php artisan migrate --env=native --force }
    Invoke-Checked { & $php artisan platform:runtime --env=native }
    $env:DB_USERNAME='sas_runtime'
    $env:DB_PASSWORD=$runtimePassword
    if($Compilar){Invoke-Checked {npm.cmd ci}; Invoke-Checked {npm.cmd run build}}
    if(!(Test-Path -LiteralPath 'public\build\manifest.json')){Invoke-Checked {npm.cmd run build}}
    if(!(Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue)){
        $server=Start-Process -FilePath $php -ArgumentList @('artisan','serve','--env=native','--host=127.0.0.1','--port=8000','--tries=1') -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'web-out.log') -RedirectStandardError (Join-Path $runtime 'web-error.log') -PassThru
        $server.Id | Set-Content (Join-Path $runtime 'web.pid')
    }
    $schedulerPid=Join-Path $runtime 'scheduler.pid'
    $running=if(Test-Path -LiteralPath $schedulerPid){Get-CimInstance Win32_Process -Filter ('ProcessId='+[int](Get-Content $schedulerPid)) -ErrorAction SilentlyContinue}else{$null}
    if($running -and ($running.ExecutablePath -ne $php -or $running.CommandLine -notmatch 'schedule:work')){$running=$null}
    if(!$running){
        $scheduler=Start-Process -FilePath $php -ArgumentList @('artisan','schedule:work','--env=native') -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'scheduler-out.log') -RedirectStandardError (Join-Path $runtime 'scheduler-error.log') -PassThru
        $scheduler.Id | Set-Content $schedulerPid
    }
}
$publicUrl='http://localhost:8000'
if($Modo -eq 'Docker'){
    $urlLine=Get-Content -LiteralPath '.env' | Where-Object {$_ -match '^APP_URL='} | Select-Object -First 1
    if($urlLine){$publicUrl=($urlLine -replace '^APP_URL=','').Trim().Trim('"')}
}elseif($env:APP_URL){$publicUrl=$env:APP_URL}
Write-Host "Sistema: $publicUrl — configuración inicial en $publicUrl/instalacion."
