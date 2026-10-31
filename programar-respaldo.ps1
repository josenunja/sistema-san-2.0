param(
    [ValidatePattern('^(?:[01][0-9]|2[0-3]):[0-5][0-9]$')][string]$Hora='22:00',
    [string]$Destino='', [string]$CopiaSecundaria='', [string]$TaskName='',
    [ValidateSet('Auto','Docker','Nativo')][string]$Modo='Auto',
    [string]$Configuracion='', [switch]$Eliminar, [switch]$SoloXml
)
$ErrorActionPreference='Stop'
$rootPath=[IO.Path]::GetFullPath($PSScriptRoot)
$sha=[Security.Cryptography.SHA256]::Create()
try { $id=([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($rootPath.ToLowerInvariant())))).Replace('-','').Substring(0,12) } finally { $sha.Dispose() }
if(!$TaskName) { $TaskName='SAS_SAN_Respaldo_'+$id }
if($TaskName -notmatch '^[a-zA-Z0-9_-]{1,100}$') { throw 'Nombre de tarea no admitido.' }
$description='SAS SAN backup instance '+$id
$existing=Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if($existing -and $existing.Description -ne $description) { throw 'La tarea pertenece a otra instalación. No se modificó.' }
if($Eliminar) {
    if($existing) { Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false }
    Write-Output ('Tarea retirada de esta instalación: '+$TaskName)
    return
}
$exe=if(Get-Command pwsh.exe -ErrorAction SilentlyContinue){(Get-Command pwsh.exe).Source}else{Join-Path $env:SystemRoot 'System32/WindowsPowerShell/v1.0/powershell.exe'}
function Quote-SasArgument([string]$Value) {
    if($Value -match '["\r\n]' -or $Value.EndsWith('\')) { throw 'Argumento de tarea no admitido: use una ruta sin comillas ni barra final.' }
    return '"'+$Value+'"'
}
$arguments='-NoProfile -NonInteractive -WindowStyle Hidden -File '+(Quote-SasArgument (Join-Path $rootPath 'respaldo-cliente.ps1'))+' -Modo '+$Modo
foreach($pair in @(@('Destino',$Destino),@('CopiaSecundaria',$CopiaSecundaria),@('Configuracion',$Configuracion))) {
    if($pair[1]) { $arguments+=' -'+$pair[0]+' '+(Quote-SasArgument $pair[1]) }
}
$action=New-ScheduledTaskAction -Execute $exe -Argument $arguments -WorkingDirectory $rootPath
$time=[DateTime]::ParseExact($Hora,'HH:mm',[Globalization.CultureInfo]::InvariantCulture)
$trigger=New-ScheduledTaskTrigger -Daily -At $time
$settings=New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 2)
$principal=New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$task=New-ScheduledTask -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description $description
if($SoloXml) { $task | Export-ScheduledTask; return }
Register-ScheduledTask -TaskName $TaskName -InputObject $task -Force | Out-Null
Write-Output $TaskName
Write-Host ('Respaldo diario a las '+$Hora+' según la hora de Windows. El equipo debe estar encendido y el usuario con sesión iniciada.')
