Set-StrictMode -Version Latest

function Read-SasEnv([string]$Path) {
    if (!(Test-Path -LiteralPath $Path -PathType Leaf)) { throw 'No existe la configuración indicada.' }
    $result=@{}
    foreach($line in [IO.File]::ReadAllLines($Path)) {
        if($line -match '^([A-Z_][A-Z0-9_]*)=(.*)$') { $result[$matches[1]]=$matches[2].Trim().Trim('"').Trim("'") }
    }
    return $result
}
function Write-SasEnv([hashtable]$Values,[string]$Path) {
    $lines=foreach($name in ($Values.Keys | Sort-Object)) {
        $value=[string]$Values[$name]
        if($value -match '[\r\n"]') { throw ('Valor de configuración no admitido: '+$name) }
        if($value -match '[\s#]') { $value='"'+$value+'"' }
        $name+'='+$value
    }
    [IO.File]::WriteAllLines($Path,[string[]]$lines,[Text.UTF8Encoding]::new($false))
}
function New-SasSecret {
    $bytes=New-Object byte[] 32
    $rng=[Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return [Convert]::ToBase64String($bytes)
}
function Invoke-SasChecked([string]$File,[string[]]$Arguments) {
    & $File @Arguments
    if($LASTEXITCODE -ne 0) { throw ('Falló '+[IO.Path]::GetFileName($File)+' (código '+$LASTEXITCODE+').') }
}
function Remove-SasWork([string]$Root,[string]$Path) {
    $allowed=[IO.Path]::GetFullPath((Join-Path $Root '.runtime'))+[IO.Path]::DirectorySeparatorChar
    $resolved=[IO.Path]::GetFullPath($Path)
    if(!$resolved.StartsWith($allowed,[StringComparison]::OrdinalIgnoreCase) -or (Split-Path $resolved -Leaf) -notmatch '^(backup|restore)-work-[a-f0-9]{32}$') { throw 'Ruta temporal no autorizada.' }
    if(Test-Path -LiteralPath $resolved) { Assert-SasTree $resolved; Remove-Item -LiteralPath $resolved -Recurse -Force }
}
function Assert-SasTree([string]$Path) {
    if(!(Test-Path -LiteralPath $Path)) { return }
    $entries=@(Get-Item -LiteralPath $Path -Force)+@(Get-ChildItem -LiteralPath $Path -Recurse -Force)
    if(@($entries | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }).Count) { throw 'No se admiten enlaces simbólicos en los archivos de entrega.' }
}
function Get-SasContext([string]$Root,[string]$Mode='Auto',[string]$Configuration='') {
    $rootPath=[IO.Path]::GetFullPath($Root)
    $appPath=Join-Path $rootPath 'app'
    if($Mode -eq 'Auto') { $Mode=if(Test-Path -LiteralPath (Join-Path $appPath '.env.native')){'Nativo'}else{'Docker'} }
    if(!$Configuration) { $Configuration=Join-Path $appPath $(if($Mode -eq 'Nativo'){'.env.native'}else{'.env'}) }
    $map=Read-SasEnv $Configuration
    if($map['DB_CONNECTION'] -ne 'pgsql') { throw 'Esta entrega requiere PostgreSQL.' }
    $database=[string]$map['DB_DATABASE']
    if($database -notmatch '^[a-z][a-z0-9_]{0,62}$') { throw 'Nombre de base no admitido.' }
    $php=Join-Path $rootPath '.runtime/php/php.exe'
    if(!(Test-Path -LiteralPath $php)) { $php=(Get-Command php -ErrorAction Stop).Source }
    $pgBin=Join-Path $rootPath '.runtime/pg18/pgsql/bin'
    $ownerFile=Join-Path $rootPath '.runtime/pg-password.txt'
    $password=if($Mode -eq 'Nativo' -and (Test-Path -LiteralPath $ownerFile)){[IO.File]::ReadAllText($ownerFile).Trim()}else{[string]$map['DB_PASSWORD']}
    if(!$password) { throw 'Falta la credencial privada de sas_owner.' }
    $dbHost=if($map['DB_HOST']){[string]$map['DB_HOST']}else{'127.0.0.1'}
    $dbPort=if($map['DB_PORT']){[string]$map['DB_PORT']}else{'5432'}
    if($Mode -eq 'Nativo' -and $dbHost -notin @('127.0.0.1','localhost')) { throw 'El modo local requiere PostgreSQL en loopback.' }
    return [pscustomobject]@{Root=$rootPath; App=$appPath; Mode=$Mode; Config=[IO.Path]::GetFullPath($Configuration); Map=$map; Database=$database; Host=$dbHost; Port=$dbPort; Php=$php; PgBin=$pgBin; OwnerPassword=$password}
}
function Invoke-SasPg($Context,[string]$Tool,[string[]]$Arguments) {
    $previous=$env:PGPASSWORD
    $env:PGPASSWORD=$Context.OwnerPassword
    try {
        if($Context.Mode -eq 'Docker') {
            Push-Location $Context.App
            try { Invoke-SasChecked 'docker' (@('compose','exec','-T','-e','PGPASSWORD','db',$Tool,'-U','sas_owner')+$Arguments) } finally { Pop-Location }
        } else {
            $exe=Join-Path $Context.PgBin ($Tool+'.exe')
            if(!(Test-Path -LiteralPath $exe)) { $exe=(Get-Command $Tool -ErrorAction Stop).Source }
            Invoke-SasChecked $exe (@('-h',$Context.Host,'-p',$Context.Port,'-U','sas_owner')+$Arguments)
        }
    } finally { $env:PGPASSWORD=$previous }
}
function Invoke-SasDump($Context,[string]$Destination) {
    if($Context.Mode -eq 'Nativo') { Invoke-SasPg $Context 'pg_dump' @('-d',$Context.Database,'-F','c','-f',$Destination); return }
    $remote='/tmp/sas-'+[guid]::NewGuid().ToString('N')+'.dump'
    Push-Location $Context.App
    try {
        Invoke-SasPg $Context 'pg_dump' @('-d',$Context.Database,'-F','c','-f',$remote)
        Invoke-SasChecked 'docker' @('compose','cp',('db:'+$remote),$Destination)
    } finally { & docker compose exec -T db rm -f $remote; Pop-Location }
}
function Invoke-SasRestore($Context,[string]$Database,[string]$Source) {
    if($Database -notmatch '^sas_san_restore_[a-f0-9]{12}$') { throw 'La recuperación solo admite una base nueva generada por la herramienta.' }
    Invoke-SasPg $Context 'createdb' @('-O','sas_owner',$Database)
    if($Context.Mode -eq 'Nativo') { Invoke-SasPg $Context 'pg_restore' @('--exit-on-error','--no-owner','--role=sas_owner','-d',$Database,$Source); return }
    $remote='/tmp/sas-'+[guid]::NewGuid().ToString('N')+'.dump'
    Push-Location $Context.App
    try {
        Invoke-SasChecked 'docker' @('compose','cp',$Source,('db:'+$remote))
        Invoke-SasPg $Context 'pg_restore' @('--exit-on-error','--no-owner','--role=sas_owner','-d',$Database,$remote)
    } finally { & docker compose exec -T db rm -f $remote; Pop-Location }
}
function Get-SasKey([string]$Root,[string]$Path='', [switch]$Create) {
    if(!$Path) {
        foreach($candidate in @('claves/backup.key','.runtime/backup.key')) {
            $file=Join-Path $Root $candidate
            if(Test-Path -LiteralPath $file) { return $file }
        }
        $Path=Join-Path $Root 'claves/backup.key'
    }
    $Path=[IO.Path]::GetFullPath($Path)
    if(!(Test-Path -LiteralPath $Path)) {
        if(!$Create) { throw 'Falta backup.key. No es posible recuperar datos sin su clave original.' }
        New-Item -ItemType Directory -Path (Split-Path $Path) -Force | Out-Null
        [IO.File]::WriteAllBytes($Path,[Convert]::FromBase64String((New-SasSecret)))
    }
    if((Get-Item -LiteralPath $Path).Length -ne 32) { throw 'La clave de respaldo debe contener 32 bytes.' }
    return $Path
}
