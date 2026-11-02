param(
    [ValidateRange(0,65535)][int]$PuertoWeb=0,
    [ValidateRange(1024,65535)][int]$PuertoPostgres=55432,
    [ValidateSet('sas_san','sas_san_test')][string]$BaseDatos='sas_san',
    [switch]$Pruebas
)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'scripts/entrega-comun.ps1')
$rootPath=[IO.Path]::GetFullPath($PSScriptRoot)
$runtime=Join-Path $rootPath '.runtime'
$appPath=Join-Path $rootPath 'app'
$php=Join-Path $runtime 'php/php.exe'
$pg=Join-Path $runtime 'pg18/pgsql/bin'
$configPath=Join-Path $appPath '.env.native'
foreach($required in @($php,(Join-Path $pg 'pg_ctl.exe'),(Join-Path $appPath 'vendor/autoload.php'),(Join-Path $appPath 'public/build/manifest.json'))) {
    if(!(Test-Path -LiteralPath $required)) { throw 'Use la entrega local completa preparada con preparar-entrega-local.ps1: faltan binarios o dependencias.' }
}
if($BaseDatos -eq 'sas_san_test' -and !$Pruebas) { throw 'La base de aceptación requiere -Pruebas explícito.' }
if(Test-Path -LiteralPath $configPath) { Write-Host 'La instalación ya tiene configuración. Se conserva; use iniciar.ps1.'; $global:LASTEXITCODE=0; return }
$template=Read-SasEnv (Join-Path $appPath '.env.example')
if(!$PuertoWeb) { $PuertoWeb=if($template['APP_PORT']){[int]$template['APP_PORT']}else{8000} }
if($PuertoWeb -lt 1024 -or $PuertoWeb -eq $PuertoPostgres) { throw 'Puertos locales no válidos.' }
$data=Join-Path $runtime 'pgdata'
$ownerFile=Join-Path $runtime 'pg-password.txt'
$runtimeFile=Join-Path $runtime 'runtime-password.txt'
if((Test-Path -LiteralPath (Join-Path $data 'PG_VERSION')) -and !(Test-Path -LiteralPath $ownerFile)) { throw 'El clúster existente no tiene su credencial. No se reemplazó nada.' }
foreach($secretFile in @($ownerFile,$runtimeFile)) {
    if(!(Test-Path -LiteralPath $secretFile)) { [IO.File]::WriteAllText($secretFile,(New-SasSecret),[Text.UTF8Encoding]::new($false)) }
}
# The distributed php.ini must not retain the developer's absolute paths.
$ini=Join-Path $runtime 'php/php.ini'
$iniContent=[IO.File]::ReadAllText($ini)
$iniContent=[regex]::Replace($iniContent,'(?m)^extension_dir=.*$',('extension_dir="'+(Join-Path $runtime 'php/ext').Replace('\','/')+'"'))
$iniContent=[regex]::Replace($iniContent,'(?m)^(curl.cainfo|openssl.cafile)=.*$',('$1="'+(Join-Path $runtime 'php/cacert.pem').Replace('\','/')+'"'))
$iniContent=[regex]::Replace($iniContent,'(?m)^opcache.enable(?:_cli)?=.*$','')
$iniContent+="`nopcache.enable=0`nopcache.enable_cli=0`n"
[IO.File]::WriteAllText($ini,$iniContent,[Text.UTF8Encoding]::new($false))
Invoke-SasChecked $php @('-r','exit(extension_loaded("pdo_pgsql") && extension_loaded("sodium") && extension_loaded("gd") ? 0 : 1);')
if(!(Test-Path -LiteralPath (Join-Path $data 'PG_VERSION'))) {
    if(Get-NetTCPConnection -LocalPort $PuertoPostgres -State Listen -ErrorAction SilentlyContinue) { throw 'El puerto PostgreSQL está ocupado; elija otro para esta instalación.' }
    Invoke-SasChecked (Join-Path $pg 'initdb.exe') @('-D',$data,'-U','sas_owner',('--pwfile='+$ownerFile),'--auth-host=scram-sha-256','--auth-local=scram-sha-256','-E','UTF8','--locale=C')
    Add-Content -LiteralPath (Join-Path $data 'postgresql.conf') -Value ("`nport = $PuertoPostgres`nlisten_addresses = '127.0.0.1'`n") -Encoding ASCII
}
& (Join-Path $pg 'pg_ctl.exe') status -D $data | Out-Null
if($LASTEXITCODE -ne 0) { Invoke-SasChecked (Join-Path $pg 'pg_ctl.exe') @('-D',$data,'-l',(Join-Path $runtime 'postgres.log'),'-w','start') }
$template['APP_ENV']=if($Pruebas){'testing'}else{'native'}
$template['APP_KEY']='base64:'+(New-SasSecret)
$template['APP_DEBUG']='false'
$template['APP_URL']='http://localhost:'+$PuertoWeb; $template['APP_PORT']=[string]$PuertoWeb
$template['DB_CONNECTION']='pgsql'; $template['DB_HOST']='127.0.0.1'; $template['DB_PORT']=[string]$PuertoPostgres
$template['DB_DATABASE']=$BaseDatos; $template['DB_USERNAME']='sas_runtime'; $template['DB_PASSWORD']=[IO.File]::ReadAllText($runtimeFile).Trim()
$template['MAIL_MAILER']='log'; $template['BUSINESS_MODE']='platform'
$template['SESSION_COOKIE']='negocio_'+[guid]::NewGuid().ToString('N')+'_session'
$ownerPassword=[IO.File]::ReadAllText($ownerFile).Trim()
$previousPg=$env:PGPASSWORD
try {
    $env:PGPASSWORD=$ownerPassword
    $exists=& (Join-Path $pg 'psql.exe') -h 127.0.0.1 -p $PuertoPostgres -U sas_owner -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$BaseDatos'"
    if($LASTEXITCODE -ne 0) { throw 'No se puede consultar el clúster de esta instalación.' }
    if(($exists -join '').Trim() -ne '1') { Invoke-SasChecked (Join-Path $pg 'createdb.exe') @('-h','127.0.0.1','-p',[string]$PuertoPostgres,'-U','sas_owner','-O','sas_owner',$BaseDatos) }
    Write-SasEnv $template $configPath
    Push-Location $appPath
    $previousEnvironment=@{}
    foreach($name in $template.Keys) { $previousEnvironment[$name]=[Environment]::GetEnvironmentVariable($name,'Process') }
    try {
        foreach($name in $template.Keys) { [Environment]::SetEnvironmentVariable($name,[string]$template[$name],'Process') }
        $env:APP_ENV='native'; $env:DB_USERNAME='sas_owner'; $env:DB_PASSWORD=$ownerPassword
        Invoke-SasChecked $php @('artisan','migrate','--env=native','--force')
        Invoke-SasChecked $php @('artisan','platform:runtime','--env=native',('--runtime-password-file='+$runtimeFile))
    } finally {
        foreach($name in $previousEnvironment.Keys) {
            if($null -eq $previousEnvironment[$name]) { Remove-Item -LiteralPath ('Env:'+$name) -ErrorAction SilentlyContinue }
            else { [Environment]::SetEnvironmentVariable($name,$previousEnvironment[$name],'Process') }
        }
        Pop-Location
    }
    Get-SasKey $rootPath '' -Create | Out-Null
} finally { $env:PGPASSWORD=$previousPg }
Write-Host 'Instalación preparada. No se crearon cuentas ni productos. Ejecute .\iniciar.ps1 y complete /instalacion.'
