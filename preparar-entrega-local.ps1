param(
    [Parameter(Mandatory)][string]$Nombre,
    [Parameter(Mandatory)][string]$Identificador,
    [ValidateSet('general','fashion','bakery','mechanic','grocery','hardware','restaurant','services','footwear','accessories','mixed','other')][string]$Rubro='general',
    [ValidateRange(1024,65535)][int]$Puerto=8000,
    [Parameter(Mandatory)][string]$Destino,
    [switch]$Comprimir
)
$ErrorActionPreference='Stop'
$rootPath=[IO.Path]::GetFullPath($PSScriptRoot)
foreach($required in @('.runtime/php/php.exe','.runtime/php/cacert.pem','.runtime/pg18/pgsql/bin/pg_ctl.exe','app/vendor/autoload.php','app/public/build/manifest.json','licenses/POSTGRESQL.txt')) {
    if(!(Test-Path -LiteralPath (Join-Path $rootPath $required))) { throw ('Falta un componente de entrega: '+$required) }
}
& (Join-Path $rootPath 'preparar-copia.ps1') -Nombre $Nombre -Identificador $Identificador -Rubro $Rubro -Puerto $Puerto -Destino $Destino
$target=[IO.Path]::GetFullPath($Destino)
function Copy-SasComponent([string]$From,[string]$To) {
    & robocopy $From $To /E /XJ /NFL /NDL /NJH /NJS /NP /XF '*.log' '*.pdb' '*.key' '*.pem' 'auth.json' | Out-Null
    if($LASTEXITCODE -ge 8) { throw 'No se pudo copiar un componente del entorno local.' }
}
Copy-SasComponent (Join-Path $rootPath 'app/vendor') (Join-Path $target 'app/vendor')
Copy-SasComponent (Join-Path $rootPath '.runtime/php') (Join-Path $target '.runtime/php')
# This is a public CA bundle, not a private certificate/key.
Copy-Item -LiteralPath (Join-Path $rootPath '.runtime/php/cacert.pem') -Destination (Join-Path $target '.runtime/php/cacert.pem')
foreach($folder in @('bin','lib','share')) { Copy-SasComponent (Join-Path $rootPath ('.runtime/pg18/pgsql/'+$folder)) (Join-Path $target ('.runtime/pg18/pgsql/'+$folder)) }
Copy-Item -LiteralPath (Join-Path $rootPath 'licenses/POSTGRESQL.txt') -Destination (Join-Path $target '.runtime/pg18/pgsql/COPYRIGHT')
# Produce a relocatable php.ini without any developer path.
$ini=Get-Content -LiteralPath (Join-Path $rootPath '.runtime/php/php.ini') -Raw
$ini=[regex]::Replace($ini,'(?m)^extension_dir=.*$','extension_dir="ext"')
$ini=[regex]::Replace($ini,'(?m)^(curl.cainfo|openssl.cafile)=.*$','$1="cacert.pem"')
$ini=[regex]::Replace($ini,'(?m)^opcache.enable(?:_cli)?=.*$','')
$ini+="`nopcache.enable=0`nopcache.enable_cli=0`n"
[IO.File]::WriteAllText((Join-Path $target '.runtime/php/php.ini'),$ini,[Text.UTF8Encoding]::new($false))
$manifest=@(Get-ChildItem -LiteralPath (Join-Path $target '.runtime') -Recurse -File | ForEach-Object {
    [ordered]@{path=$_.FullName.Substring($target.Length+1).Replace('\','/'); sha256=(Get-FileHash -LiteralPath $_.FullName).Hash}
})
[IO.File]::WriteAllText((Join-Path $target 'COMPONENTES_LOCALES.json'),($manifest | ConvertTo-Json -Depth 3),[Text.UTF8Encoding]::new($false))
$guide=@"
# $Nombre — entrega local

Windows x64. Incluye PHP, PostgreSQL, dependencias de aplicación y recursos compilados; no necesita Node ni Docker para el uso local.

1. Copie toda esta carpeta a un disco local del cliente. No ejecute desde dentro del ZIP.
2. Abra PowerShell en esta carpeta: .\armar-entorno-portatil.ps1
3. Ejecute .\iniciar.ps1 y visite http://localhost:$Puerto/instalacion.
4. El propietario crea su contraseña y activa MFA personalmente. No existen credenciales universales.
5. Configure y pruebe respaldos con docs/GUIA_RESPALDOS_Y_RECUPERACION.md. Registre la aceptación con docs/ACTA_ENTREGA_CONFORMIDAD.md.

Para otro servidor PostgreSQL en el mismo equipo elija -PuertoPostgres libre al preparar el entorno. Cada entrega crea su propio clúster, claves y cookie. Nunca copiar una carpeta ya instalada a otro cliente.

Dominio, hosting, emisión fiscal, correo real, pagos automáticos y equipos periféricos no están incluidos. La compatibilidad de Windows y sus componentes de ejecución se comprueba en el equipo de destino. Incluye licencias de los componentes en sus carpetas correspondientes; no implica exclusividad sobre componentes de terceros.
"@
[IO.File]::WriteAllText((Join-Path $target 'EMPEZAR_AQUI.md'),$guide,[Text.UTF8Encoding]::new($false))
if($Comprimir) {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip=$target+'.zip'
    if(Test-Path -LiteralPath $zip) { throw 'El ZIP ya existe; no se reemplaza una entrega anterior.' }
    [IO.Compression.ZipFile]::CreateFromDirectory($target,$zip,[IO.Compression.CompressionLevel]::Optimal,$false)
    Write-Output $zip
}
$global:LASTEXITCODE=0
Write-Output ('Entrega local sin datos preparada: '+$target)
