param(
    [Parameter(Mandatory)][string]$ArchivoSsbk,
    [ValidateSet('Auto','Docker','Nativo')][string]$Modo='Auto',
    [string]$KeyPath='', [string]$Configuracion='',
    [ValidateRange(1024,65535)][int]$Puerto=8010,
    [switch]$Confirmar
)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'scripts/entrega-comun.ps1')
$rootPath=[IO.Path]::GetFullPath($PSScriptRoot)
$context=Get-SasContext $rootPath $Modo $Configuracion
$key=Get-SasKey $rootPath $KeyPath
if(!(Test-Path -LiteralPath $ArchivoSsbk -PathType Leaf)) { throw 'El respaldo indicado no existe.' }
if(!$Confirmar -and (Read-Host 'Se creará una base NUEVA y una carpeta de recuperación. No se reemplaza la instalación actual. Escriba SI') -cne 'SI') { throw 'Recuperación cancelada.' }
$work=Join-Path $rootPath ('.runtime/restore-work-'+[guid]::NewGuid().ToString('N'))
$id=[guid]::NewGuid().ToString('N').Substring(0,12)
$database='sas_san_restore_'+$id
$recovery=Join-Path $rootPath ('.runtime/recuperaciones/'+$id)
New-Item -ItemType Directory -Path $work -Force | Out-Null
try {
    $zip=Join-Path $work 'payload.zip'
    Invoke-SasChecked $context.Php @((Join-Path $context.App 'tools/backup-crypt.php'),'decrypt',([IO.Path]::GetFullPath($ArchivoSsbk)),$zip,$key) | Out-Null
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive=[IO.Compression.ZipFile]::OpenRead($zip)
    try {
        $seen=@{}; [long]$expanded=0
        foreach($entry in $archive.Entries) {
            $name=$entry.FullName.Replace('\','/')
            if(!$name -or $name.StartsWith('/') -or $name -match '(^|/)\.\.(/|$)|:|\x00' -or $seen.ContainsKey($name.ToLowerInvariant())) { throw 'Ruta insegura o duplicada en el respaldo.' }
            if(($entry.ExternalAttributes -shr 16 -band 0xF000) -eq 0xA000) { throw 'El respaldo contiene enlaces simbólicos.' }
            $seen[$name.ToLowerInvariant()]=$true
            $expanded+=$entry.Length
            if($expanded -gt 20GB -or $seen.Count -gt 100000) { throw 'El respaldo supera el límite de extracción de esta herramienta.' }
        }
    } finally { $archive.Dispose() }
    $payload=Join-Path $work 'payload'
    [IO.Compression.ZipFile]::ExtractToDirectory($zip,$payload)
    $manifest=Get-Content -LiteralPath (Join-Path $payload 'manifest.json') -Raw | ConvertFrom-Json
    if($manifest.version -ne 2) { throw 'Use un respaldo de cliente versión 2; conserve las herramientas anteriores para respaldos antiguos.' }
    $expected=@{}
    foreach($entry in $manifest.files) {
        if($entry.path -match '(^|/)\.\.(/|$)|:|^[/\\]' -or $expected.ContainsKey($entry.path)) { throw 'Manifiesto no válido.' }
        $file=Join-Path $payload $entry.path
        if(!(Test-Path -LiteralPath $file -PathType Leaf) -or (Get-Item -LiteralPath $file).Length -ne $entry.size -or (Get-FileHash -LiteralPath $file).Hash -ne $entry.sha256) { throw 'El contenido del respaldo no coincide con su manifiesto.' }
        $expected[$entry.path]=$true
    }
    foreach($file in Get-ChildItem -LiteralPath $payload -Recurse -File -Force) {
        $relative=$file.FullName.Substring($payload.Length+1).Replace('\','/')
        if($relative -ne 'manifest.json' -and !$expected.ContainsKey($relative)) { throw 'Archivo fuera del manifiesto.' }
    }
    if(!$expected.ContainsKey('database.dump') -or !$expected.ContainsKey('config.env')) { throw 'Respaldo incompleto.' }
    $restored=Read-SasEnv (Join-Path $payload 'config.env')
    if(!$restored['APP_KEY']) { throw 'El respaldo no conserva APP_KEY.' }
    # Authentication and every hash are checked before creating any database.
    Invoke-SasRestore $context $database (Join-Path $payload 'database.dump')
    Invoke-SasPg $context 'psql' @('-d',$database,'-v','ON_ERROR_STOP=1','-c','GRANT USAGE ON SCHEMA public TO sas_runtime; GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES IN SCHEMA public TO sas_runtime; GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA public TO sas_runtime; REVOKE ALL ON migrations FROM sas_runtime; GRANT SELECT ON migrations TO sas_runtime;') | Out-Null
    New-Item -ItemType Directory -Path $recovery -Force | Out-Null
    if(Test-Path -LiteralPath (Join-Path $payload 'private')) { Copy-Item -LiteralPath (Join-Path $payload 'private') -Destination (Join-Path $recovery 'private') -Recurse }
    else { New-Item -ItemType Directory -Path (Join-Path $recovery 'private') | Out-Null }
    foreach($name in @('DB_CONNECTION','DB_HOST','DB_PORT','DB_USERNAME','DB_PASSWORD')) { $restored[$name]=$context.Map[$name] }
    $restored['DB_DATABASE']=$database
    $restored['PRIVATE_STORAGE_ROOT']=(Join-Path $recovery 'private').Replace('\','/')
    $restored['MAIL_MAILER']='log'; $restored['APP_DEBUG']='false'
    $restored['APP_PORT']=[string]$Puerto; $restored['APP_URL']='http://localhost:'+$Puerto
    $restored['SESSION_COOKIE']='recuperacion_'+$id+'_session'
    $restored['CACHE_PREFIX']='recuperacion_'+$id
    $configPath=Join-Path $recovery 'config.env'
    Write-SasEnv $restored $configPath
    $result=[ordered]@{estado='RESTAURADO_EN_BASE_NUEVA'; base=$database; configuracion=$configPath; archivos=(Join-Path $recovery 'private'); original_conservado=$true; fecha_utc=[DateTime]::UtcNow.ToString('o')}
    [IO.File]::WriteAllText((Join-Path $recovery 'resultado.json'),($result | ConvertTo-Json),[Text.UTF8Encoding]::new($false))
    Write-Output $configPath
    Write-Host 'Recuperación terminada en una base nueva. Revise saldos y archivos antes de usarla.'
    Write-Host 'Para probarla localmente: .\iniciar.ps1 -Configuracion <ruta de config.env mostrada>'
} finally { if(Test-Path -LiteralPath $work) { Remove-SasWork $rootPath $work } }
