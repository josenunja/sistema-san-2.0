$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$access = Join-Path $root '.runtime\demo-access.json'
$php = Join-Path $root '.runtime\php\php.exe'
$autoload = Join-Path $root 'app\vendor\autoload.php'

if (!(Test-Path -LiteralPath $access)) {
    throw 'No existe la demostración. Ejecuta primero .\aceptar-en-navegador.ps1.'
}

$program = @'
require $argv[1];
$data = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$google2fa = new PragmaRX\Google2FA\Google2FA();
foreach ($data['accounts'] as $key => $account) {
    echo strtoupper($key).PHP_EOL;
    echo 'Correo: '.$account['email'].PHP_EOL;
    echo 'Contraseña: '.$account['password'].PHP_EOL;
    echo 'Código MFA actual: '.$google2fa->getCurrentOtp($account['totp']).PHP_EOL;
    echo 'Rol: '.$account['role'].PHP_EOL.PHP_EOL;
}
echo 'Los códigos MFA cambian cada 30 segundos. Uso exclusivo en sas_san_test.'.PHP_EOL;
'@

& $php -r $program $autoload $access
if ($LASTEXITCODE -ne 0) {
    throw 'No se pudieron leer los accesos de demostración.'
}
