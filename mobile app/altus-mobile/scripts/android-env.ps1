$sdkPath = [Environment]::GetEnvironmentVariable('ANDROID_HOME','User')
if (-not $sdkPath) { $sdkPath = Join-Path $env:LOCALAPPDATA 'Android\Sdk' }
$javaPath = [Environment]::GetEnvironmentVariable('JAVA_HOME','User')
if (-not $javaPath) { $javaPath = 'C:\Program Files\Android\Android Studio\jbr' }
if (-not (Test-Path -LiteralPath (Join-Path $sdkPath 'platform-tools\adb.exe'))) { throw 'Android platform-tools are missing.' }
if (-not (Test-Path -LiteralPath (Join-Path $javaPath 'bin\java.exe'))) { throw 'Java installation is missing.' }
$env:ANDROID_HOME = $sdkPath
$env:JAVA_HOME = $javaPath
$env:Path = (Join-Path $sdkPath 'platform-tools') + ';' + (Join-Path $javaPath 'bin') + ';' + $env:Path
