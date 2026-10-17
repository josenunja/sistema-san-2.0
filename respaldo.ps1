param([ValidateSet('sas_san','sas_san_test')][string]$Database='sas_san')
$ErrorActionPreference='Stop'
$runtimeRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '.runtime'))
$work=Join-Path $runtimeRoot ('backup-work-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $work | Out-Null
$backupDir=Join-Path $runtimeRoot 'backups'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$env:PGPASSWORD=[IO.File]::ReadAllText((Join-Path $runtimeRoot 'pg-password.txt'))
$pgDump=Join-Path $runtimeRoot 'pg18\pgsql\bin\pg_dump.exe'
$php=Join-Path $runtimeRoot 'php\php.exe'
try {
    & $pgDump -h 127.0.0.1 -p 55432 -U sas_owner -d $Database -F c -f (Join-Path $work 'database.dump')
    if($LASTEXITCODE -ne 0){throw 'No se pudo completar pg_dump.'}
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'app\.env.native') -Destination (Join-Path $work 'config.env')
    $files=@((Join-Path $work 'database.dump'),(Join-Path $work 'config.env'))
    $private=if($Database -eq 'sas_san_test'){Join-Path $runtimeRoot 'test-objects'}else{Join-Path $PSScriptRoot 'app\storage\app\private'}
    if(Test-Path -LiteralPath $private){ Copy-Item -LiteralPath $private -Destination (Join-Path $work 'private') -Recurse; $files+=Join-Path $work 'private' }
    $zip=Join-Path $work 'payload.zip'
    Compress-Archive -LiteralPath $files -DestinationPath $zip
    $output=Join-Path $backupDir ($Database+'-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[guid]::NewGuid().ToString('N').Substring(0,6)+'.ssbk')
    & $php (Join-Path $PSScriptRoot 'app\tools\backup-crypt.php') encrypt $zip $output (Join-Path $runtimeRoot 'backup.key') | Out-Null
    if($LASTEXITCODE -ne 0){throw 'No se pudo cifrar el respaldo.'}
    Write-Output $output
} finally {
    $resolved=[IO.Path]::GetFullPath($work)
    if(!$resolved.StartsWith($runtimeRoot+[IO.Path]::DirectorySeparatorChar) -or !(Split-Path $resolved -Leaf).StartsWith('backup-work-')){throw 'Ruta de limpieza no autorizada.'}
    if(Test-Path -LiteralPath $resolved){Remove-Item -LiteralPath $resolved -Recurse -Force}
}
