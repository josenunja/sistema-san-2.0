param(
    [Parameter(Mandatory)][ValidatePattern('^[\p{L}\p{N} .,&()_-]{1,160}$')][string]$Nombre,
    [Parameter(Mandatory)][ValidateLength(1,80)][ValidatePattern('^[a-z0-9]+(?:-[a-z0-9]+)*$')][string]$Identificador,
    [ValidateSet('general','fashion','bakery','mechanic','grocery','hardware','restaurant','services','footwear','accessories','mixed','other')][string]$Rubro='general',
    [ValidateRange(1024,65535)][int]$Puerto=8001,
    [string]$Destino=''
)
$ErrorActionPreference='Stop'
$root=[IO.Path]::GetFullPath($PSScriptRoot)
if(!$Destino){$Destino=Join-Path $root ('copias/'+$Identificador)}
$target=[IO.Path]::GetFullPath($Destino)
$appSource=Join-Path $root 'app'
if(!$target.StartsWith($root+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase) -or $target.StartsWith($appSource+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) {throw 'El destino debe estar dentro del proyecto y fuera de app/.'}
if(Test-Path -LiteralPath $target){throw 'El destino ya existe. Usa una carpeta nueva para conservar cualquier archivo o dato previo.'}
New-Item -ItemType Directory -Path $target | Out-Null
$appTarget=Join-Path $target 'app'
& robocopy $appSource $appTarget /E /XJ /NFL /NDL /NJH /NJS /NP /XD .git vendor node_modules .agents .codex .claude .cursor .junie .vscode .phpunit.cache .cache .vite .vite-temp (Join-Path $appSource 'storage') (Join-Path $appSource 'public/storage') (Join-Path $appSource 'bootstrap/cache') /XF .env '.env.*' auth.json hot .phpunit.result.cache .php-cs-fixer.cache '*.log' '*.pem' '*.key' '*.pfx' '*.p12' '*.dump' '*.ssbk' | Out-Null
if($LASTEXITCODE -ge 8){throw 'No se pudo preparar la copia de código.'}
Copy-Item -LiteralPath (Join-Path $root 'docs') -Destination $target -Recurse
if(Test-Path -LiteralPath (Join-Path $root '.github')){Copy-Item -LiteralPath (Join-Path $root '.github') -Destination $target -Recurse}
foreach($file in @('README.md','AGENTS.md','.gitignore','iniciar.ps1','verificar.ps1','preparar-copia.ps1','preparar-produccion.ps1','respaldo.ps1','probar-restauracion.ps1','aceptar-en-navegador.ps1','ver-accesos-demo.ps1')){Copy-Item -LiteralPath (Join-Path $root $file) -Destination $target}
foreach($dir in @('storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache')){New-Item -ItemType Directory -Force -Path (Join-Path $appTarget $dir) | Out-Null}
$instanceTag=[guid]::NewGuid().ToString('N').Substring(0,8)
$projectName='negocio-'+$Identificador+'-'+$instanceTag
$values=@{APP_NAME='"'+$Nombre+'"';VITE_APP_NAME='"'+$Nombre+'"';APP_URL="http://localhost:$Puerto";APP_PORT="$Puerto";BUSINESS_MODE='single';BUSINESS_SLUG=$Identificador;BUSINESS_NAME='"'+$Nombre+'"';BUSINESS_TYPE=$Rubro;COMPOSE_PROJECT_NAME=$projectName;SESSION_COOKIE='negocio_'+$Identificador.Replace('-','_')+'_'+$instanceTag+'_session';APP_KEY='';DB_PASSWORD=''}
$lines=Get-Content -LiteralPath (Join-Path $appSource '.env.example')
$result=foreach($line in $lines){if($line -match '^([A-Z_][A-Z0-9_]*)='){$key=$matches[1];if($values.ContainsKey($key)){"$key=$($values[$key])";$values.Remove($key)}else{$line}}else{$line}}
foreach($key in $values.Keys){$result+="$key=$($values[$key])"}
[IO.File]::WriteAllLines((Join-Path $appTarget '.env.example'),$result,[Text.UTF8Encoding]::new($false))
$instructions=@"
# $Nombre

Copia independiente preparada para una sola empresa. No contiene cuentas, productos, fotos privadas, claves ni bases de otra instalación.

En un equipo con Docker Desktop sano, Node y npm: abrir PowerShell aquí y ejecutar:

    .\iniciar.ps1 -Modo Docker -Compilar

Abrir http://localhost:$Puerto/instalacion y crear tu cuenta y contraseña. Después activar MFA. El nombre, identificador y rubro ya están preparados.

El proyecto Docker $projectName tiene su propio PostgreSQL y volumen. No cambiarlo por el nombre de otra empresa ni reutilizar su .env, APP_KEY o volumen. No copiar .runtime/ ni datos de una instalación existente. El script de inicio genera las claves nuevas sin reemplazar claves previas. Para otra copia en el mismo equipo, usar preparar-copia.ps1 y un puerto diferente: no duplicar una carpeta que ya esté instalada.

El entorno portátil del proyecto original no se distribuye en esta copia. Para instalación nativa, usar un clúster PostgreSQL independiente y configurar PHP según docs/INSTALACION_REPOSITORIO.md. No apuntar esta copia a la base de otra empresa.

Correo y comprobantes fiscales permanecen sin integración real. Esta preparación no despliega el sitio ni configura HTTPS, dominio o copias externas.
"@
[IO.File]::WriteAllText((Join-Path $target 'EMPEZAR_AQUI.md'),$instructions,[Text.UTF8Encoding]::new($false))
$global:LASTEXITCODE=0
Write-Output "Copia preparada: $target"
Write-Output "Cada empresa necesita su propia base, claves, archivos privados y puerto. Inicio: http://localhost:$Puerto"
