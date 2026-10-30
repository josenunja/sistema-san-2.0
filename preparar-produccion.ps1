param(
    [Parameter(Mandatory)][ValidatePattern('^[\p{L}\p{N} .,&()_-]{1,160}$')][string]$Nombre,
    [Parameter(Mandatory)][ValidatePattern('^[a-z0-9]+(?:-[a-z0-9]+)*$')][ValidateLength(1,80)][string]$Identificador,
    [Parameter(Mandatory)][ValidatePattern('^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$')][string]$Dominio,
    [Parameter(Mandatory)][ValidatePattern('^[^\s@]+@[^\s@]+\.[^\s@]+$')][string]$CorreoTLS
)
$ErrorActionPreference='Stop'
$target=Join-Path $PSScriptRoot ('.runtime/produccion/'+$Identificador)
if(Test-Path -LiteralPath $target){throw 'La configuración ya existe. No se sobrescriben secretos: utiliza la carpeta existente.'}
New-Item -ItemType Directory -Path $target | Out-Null
function New-RandomSecret {
    $bytes=New-Object byte[] 32
    $generator=[Security.Cryptography.RandomNumberGenerator]::Create()
    try{$generator.GetBytes($bytes)}finally{$generator.Dispose()}
    return [Convert]::ToBase64String($bytes)
}
$encoding=[Text.UTF8Encoding]::new($false)
foreach($name in @('owner-password','runtime-password')){[IO.File]::WriteAllText((Join-Path $target $name),(New-RandomSecret),$encoding)}
[IO.File]::WriteAllText((Join-Path $target 'app-key'),('base64:'+(New-RandomSecret)),$encoding)
$content=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'app/deploy/production.env.example'))
$content=$content.Replace('APP_NAME="Mi negocio"','APP_NAME="'+$Nombre+'"').Replace('https://tienda.example.invalid','https://'+$Dominio).Replace('BUSINESS_SLUG=mi-negocio','BUSINESS_SLUG='+$Identificador).Replace('BUSINESS_NAME="Mi negocio"','BUSINESS_NAME="'+$Nombre+'"').Replace('SESSION_COOKIE=mi_negocio_session','SESSION_COOKIE='+$Identificador.Replace('-','_')+'_'+[guid]::NewGuid().ToString('N').Substring(0,8)+'_session')
[IO.File]::WriteAllText((Join-Path $target 'runtime.env'),$content,$encoding)
$project='negocio-'+$Identificador+'-'+[guid]::NewGuid().ToString('N').Substring(0,8)
$entries=@("COMPOSE_PROJECT_NAME=$project", "DOMAIN=$Dominio", "TLS_EMAIL=$CorreoTLS", ('RUNTIME_ENV_FILE='+((Join-Path $target 'runtime.env').Replace('\','/'))), ('OWNER_PASSWORD_FILE='+((Join-Path $target 'owner-password').Replace('\','/'))), ('RUNTIME_PASSWORD_FILE='+((Join-Path $target 'runtime-password').Replace('\','/'))), ('APP_KEY_FILE='+((Join-Path $target 'app-key').Replace('\','/'))))
[IO.File]::WriteAllLines((Join-Path $target 'compose.env'),$entries,$encoding)
Write-Output "Configuración privada preparada en $target"
Write-Output 'No se desplegó nada. Configura correo, permisos del directorio, dominio y respaldo externo; sigue docs/DESPLIEGUE_Y_ENTREGA.md.'
