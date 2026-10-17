$ErrorActionPreference='Stop'
$timer=[Diagnostics.Stopwatch]::StartNew()
$runtimeRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '.runtime'))
$backup=& (Join-Path $PSScriptRoot 'respaldo.ps1') -Database sas_san_test
$work=Join-Path $runtimeRoot ('restore-work-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $work | Out-Null
$database='sas_san_restore_'+[guid]::NewGuid().ToString('N').Substring(0,12)
$pgBin=Join-Path $runtimeRoot 'pg18\pgsql\bin'
$env:PGPASSWORD=[IO.File]::ReadAllText((Join-Path $runtimeRoot 'pg-password.txt'))
$countSql="SELECT json_build_object('tenants',(SELECT count(*) FROM tenants),'products',(SELECT count(*) FROM products),'orders',(SELECT count(*) FROM orders),'movements',(SELECT count(*) FROM stock_movements),'sales',(SELECT count(*) FROM sales),'physical',(SELECT coalesce(sum(physical),0) FROM stock_positions),'reserved',(SELECT coalesce(sum(reserved),0) FROM stock_positions),'offline',(SELECT coalesce(sum(offline),0) FROM stock_positions),'cash_minor',(SELECT coalesce(sum(amount_minor),0) FROM cash_entries),'payments_minor',(SELECT coalesce(sum(amount_minor),0) FROM payments WHERE status='verified'))::text"
try {
    & (Join-Path $runtimeRoot 'php\php.exe') (Join-Path $PSScriptRoot 'app\tools\backup-crypt.php') decrypt $backup (Join-Path $work 'payload.zip') (Join-Path $runtimeRoot 'backup.key') | Out-Null
    if($LASTEXITCODE -ne 0){throw 'El respaldo no se pudo autenticar o descifrar.'}
    Expand-Archive -LiteralPath (Join-Path $work 'payload.zip') -DestinationPath (Join-Path $work 'payload')
    $source=& (Join-Path $pgBin 'psql.exe') -h 127.0.0.1 -p 55432 -U sas_owner -d sas_san_test -tAc $countSql
    & (Join-Path $pgBin 'createdb.exe') -h 127.0.0.1 -p 55432 -U sas_owner $database
    if($LASTEXITCODE -ne 0){throw 'Se rechazó crear una base de restauración nueva.'}
    & (Join-Path $pgBin 'pg_restore.exe') -h 127.0.0.1 -p 55432 -U sas_owner -d $database --exit-on-error --no-owner (Join-Path $work 'payload\database.dump')
    if($LASTEXITCODE -ne 0){throw 'Falló la restauración aislada.'}
    $restored=& (Join-Path $pgBin 'psql.exe') -h 127.0.0.1 -p 55432 -U sas_owner -d $database -tAc $countSql
    if($source.Trim() -ne $restored.Trim()){throw 'Los conteos restaurados no coinciden.'}
    $objects=Join-Path $runtimeRoot 'test-objects'
    $objectCount=0
    if(Test-Path -LiteralPath $objects){
        foreach($file in Get-ChildItem -LiteralPath $objects -File -Recurse){
            $relative=[IO.Path]::GetRelativePath($objects,$file.FullName)
            $recovered=Join-Path (Join-Path $work 'payload\private') $relative
            if(!(Test-Path -LiteralPath $recovered) -or (Get-FileHash -LiteralPath $file.FullName).Hash -ne (Get-FileHash -LiteralPath $recovered).Hash){throw 'Un archivo privado no coincide después de restaurar.'}
            $objectCount++
        }
    }
    $runtimeEnv=Get-Content -LiteralPath (Join-Path $PSScriptRoot 'app\.env.native') -Raw
    $env:PGPASSWORD=[regex]::Match($runtimeEnv,'(?m)^DB_PASSWORD=(.+)$').Groups[1].Value.Trim()
    $isolated=& (Join-Path $pgBin 'psql.exe') -h 127.0.0.1 -p 55432 -U sas_runtime -d $database -tAc "SELECT count(*) FROM products"
    if($isolated.Trim() -ne '0'){throw 'La restauración no conserva el rechazo de lectura sin contexto RLS.'}
    $timer.Stop()
    [PSCustomObject]@{status='passed';database=$database;backup=$backup;seconds=[math]::Round($timer.Elapsed.TotalSeconds,2);counts=($restored|ConvertFrom-Json);private_files_verified=$objectCount;rls_without_context='denied'} | ConvertTo-Json -Depth 5
} finally {
    $resolved=[IO.Path]::GetFullPath($work)
    if(!$resolved.StartsWith($runtimeRoot+[IO.Path]::DirectorySeparatorChar) -or !(Split-Path $resolved -Leaf).StartsWith('restore-work-')){throw 'Ruta de limpieza no autorizada.'}
    if(Test-Path -LiteralPath $resolved){Remove-Item -LiteralPath $resolved -Recurse -Force}
}
