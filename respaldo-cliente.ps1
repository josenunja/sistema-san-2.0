param(
    [ValidateSet('Auto','Docker','Nativo')][string]$Modo='Auto',
    [string]$Destino='', [string]$CopiaSecundaria='', [string]$KeyPath='',
    [string]$Configuracion='', [ValidateRange(0,3650)][int]$RetencionDias=30
)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'scripts/entrega-comun.ps1')
$rootPath=[IO.Path]::GetFullPath($PSScriptRoot)
$work=Join-Path $rootPath ('.runtime/backup-work-'+[guid]::NewGuid().ToString('N'))
$lock=$null
$status=[ordered]@{version=2; fecha=[DateTime]::UtcNow.ToString('o'); resultado='ERROR'; archivo=''; secundaria=''; detalle='Respaldo no completado.'}
try {
    $context=Get-SasContext $rootPath $Modo $Configuracion
    if(!$Destino) { $Destino=Join-Path $rootPath 'respaldos' }
    $Destino=[IO.Path]::GetFullPath($Destino)
    if($Destino.StartsWith((Join-Path $context.App 'public')+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) { throw 'No guarde respaldos bajo el directorio público.' }
    New-Item -ItemType Directory -Path $Destino -Force | Out-Null
    $lock=[IO.File]::Open((Join-Path $Destino 'respaldo.lock'),[IO.FileMode]::OpenOrCreate,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None)
    $key=Get-SasKey $rootPath $KeyPath -Create
    if([IO.Path]::GetFullPath($key).StartsWith($Destino+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) { throw 'Guarde la clave fuera de la carpeta de respaldos.' }
    $payload=Join-Path $work 'payload'
    New-Item -ItemType Directory -Path $payload -Force | Out-Null
    Copy-Item -LiteralPath $context.Config -Destination (Join-Path $payload 'config.env')
    $private=if($context.Map['PRIVATE_STORAGE_ROOT']){$context.Map['PRIVATE_STORAGE_ROOT']}else{Join-Path $context.App 'storage/app/private'}
    if(Test-Path -LiteralPath $private) { Assert-SasTree $private; Copy-Item -LiteralPath $private -Destination (Join-Path $payload 'private') -Recurse }
    Invoke-SasDump $context (Join-Path $payload 'database.dump')
    if((Get-Item -LiteralPath (Join-Path $payload 'database.dump')).Length -eq 0) { throw 'El volcado está vacío.' }
    $entries=@(Get-ChildItem -LiteralPath $payload -Recurse -File -Force | ForEach-Object {
        [ordered]@{path=$_.FullName.Substring($payload.Length+1).Replace('\','/'); size=$_.Length; sha256=(Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash}
    })
    $manifest=[ordered]@{version=2; database=$context.Database; generated_utc=[DateTime]::UtcNow.ToString('o'); files=$entries}
    [IO.File]::WriteAllText((Join-Path $payload 'manifest.json'),($manifest | ConvertTo-Json -Depth 6),[Text.UTF8Encoding]::new($false))
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip=Join-Path $work 'payload.zip'
    [IO.Compression.ZipFile]::CreateFromDirectory($payload,$zip,[IO.Compression.CompressionLevel]::Optimal,$false)
    $name=$context.Database+'-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[guid]::NewGuid().ToString('N').Substring(0,8)+'.ssbk'
    $output=Join-Path $Destino $name
    Invoke-SasChecked $context.Php @((Join-Path $context.App 'tools/backup-crypt.php'),'encrypt',$zip,$output,$key) | Out-Null
    $status.archivo=$output
    if($CopiaSecundaria) {
        $secondary=[IO.Path]::GetFullPath($CopiaSecundaria)
        if($secondary.TrimEnd('\','/') -eq $Destino.TrimEnd('\','/')) { throw 'La segunda copia debe tener otro destino.' }
        New-Item -ItemType Directory -Path $secondary -Force | Out-Null
        $secondaryFile=Join-Path $secondary $name
        Copy-Item -LiteralPath $output -Destination $secondaryFile
        if((Get-FileHash -LiteralPath $output).Hash -ne (Get-FileHash -LiteralPath $secondaryFile).Hash) { throw 'La segunda copia no coincide.' }
        $status.secundaria=$secondaryFile
    }
    $status.resultado='EXITOSO'
    $status.detalle='Volcado, archivos y configuración cifrados; copia secundaria verificada si se solicitó. Conserve backup.key por separado.'
    # No automatic deletion: retaining historical backups is safer than silent pruning.
    if($RetencionDias -gt 0) { Write-Host ('Revisar manualmente respaldos mayores de '+$RetencionDias+' días; no se elimina ninguno.') }
    Write-Output $output
} catch {
    $status.detalle=$_.Exception.Message
    throw
} finally {
    $json=$status | ConvertTo-Json -Depth 3
    [IO.File]::WriteAllText((Join-Path $rootPath 'ESTADO_ULTIMO_RESPALDO.txt'),$json,[Text.UTF8Encoding]::new($false))
    if($Destino -and (Test-Path -LiteralPath $Destino -PathType Container)) {
        [IO.File]::WriteAllText((Join-Path $Destino 'ESTADO_ULTIMO_RESPALDO.txt'),$json,[Text.UTF8Encoding]::new($false))
    }
    if($lock) { $lock.Dispose() }
    if(Test-Path -LiteralPath $work) { Remove-SasWork $rootPath $work }
}
