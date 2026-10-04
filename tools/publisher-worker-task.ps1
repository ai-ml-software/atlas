# Registers (or removes) a Windows Task Scheduler job that runs the AI Publisher extraction
# worker every minute. Run from an elevated PowerShell on the server.
#
#   powershell -ExecutionPolicy Bypass -File tools\publisher-worker-task.ps1 -Php "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
#   powershell -ExecutionPolicy Bypass -File tools\publisher-worker-task.ps1 -Remove
#
# Each run processes up to 25 queued documents, records a worker heartbeat (shown in
# Content Studio -> AI Publisher) and removes retained sources past the retention window.
param(
    [string]$Php = (Get-Command php -ErrorAction SilentlyContinue).Source,
    [string]$TaskName = 'ALTUS Publisher Worker',
    [string]$User = 'SYSTEM',
    [int]$Limit = 25,
    [switch]$Remove
)
$ErrorActionPreference = 'Stop'
if ($Remove) { Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false; Write-Output "Removed $TaskName"; return }
if (-not $Php -or -not (Test-Path $Php)) { throw 'Pass -Php with the absolute path to php.exe.' }
$root = Split-Path -Parent $PSScriptRoot
$action = New-ScheduledTaskAction -Execute $Php -Argument "index.php publisher_cli work $Limit" -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -StartWhenAvailable
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -User $User -RunLevel Highest -Force | Out-Null
Write-Output "Registered '$TaskName': $Php index.php publisher_cli work $Limit (every minute, in $root)"
