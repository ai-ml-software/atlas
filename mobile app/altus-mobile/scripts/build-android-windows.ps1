param([string]$Architectures='arm64-v8a', [string]$BuildDirectory='D:\altus-build', [switch]$Bundle)
$ErrorActionPreference='Stop'
$sourcePath=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
. (Join-Path $PSScriptRoot 'android-env.ps1')
# Public configuration is optional. Do not compile a workstation's .env key into
# a release unless a matching production key is explicitly supplied to the build.
if(-not $env:EXPO_PUBLIC_ALTUS_APP_KEY) { $env:EXPO_PUBLIC_ALTUS_APP_KEY='disabled' }
$stagePath=[IO.Path]::GetFullPath($BuildDirectory).TrimEnd('\')
if($stagePath.Length -gt 40 -or $stagePath -eq $sourcePath -or $stagePath.StartsWith($sourcePath+'\',[StringComparison]::OrdinalIgnoreCase)) { throw 'Choose a separate short native build directory, such as D:\altus-build.' }
$marker=Join-Path $stagePath '.altus-build-origin'
if(Test-Path -LiteralPath $stagePath) {
  if(-not (Test-Path -LiteralPath $marker) -or (Get-Content -LiteralPath $marker -Raw).Trim() -ne $sourcePath) { throw 'Build directory belongs to another project. Choose a different directory.' }
} else {
  New-Item -ItemType Directory -Path $stagePath | Out-Null
  Set-Content -LiteralPath $marker -Value $sourcePath
}
$oldLock=if(Test-Path -LiteralPath (Join-Path $stagePath 'package-lock.json')) { (Get-FileHash -LiteralPath (Join-Path $stagePath 'package-lock.json')).Hash } else { '' }
Push-Location $sourcePath
try {
  & node (Join-Path $PSScriptRoot 'prepare-signing.cjs')
  if($LASTEXITCODE -ne 0) { throw 'Release signing key preparation failed.' }
  # Exclude both sides of /PURGE: source-only absolute exclusions can erase the
  # staged native/dependency caches even though those sources were not copied.
  $excludes=@('node_modules','android','ios','dist','.expo','.credentials','.git') | ForEach-Object { $_; Join-Path $sourcePath $_; Join-Path $stagePath $_ }
  & robocopy.exe $sourcePath $stagePath /E /PURGE /XD $excludes /XF '*.log' '.altus-build-origin' /R:1 /W:1 /NFL /NDL /NP /NJH /NJS
  if($LASTEXITCODE -ge 8) { throw 'Copying native build sources failed.' }
  Push-Location $stagePath
  try {
    $newLock=(Get-FileHash -LiteralPath (Join-Path $stagePath 'package-lock.json')).Hash
    if(-not (Test-Path -LiteralPath (Join-Path $stagePath 'node_modules')) -or $oldLock -ne $newLock) {
      & npm.cmd ci
      if($LASTEXITCODE -ne 0) { throw 'Installing native build dependencies failed.' }
    }
    $arguments=@('-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $stagePath 'scripts\build-release.ps1'),'-Architectures',$Architectures,'-SigningDirectory',(Join-Path $env:LOCALAPPDATA 'ALTUS\Signing\com.altusgulf.knowledge'),'-OutputDirectory',(Join-Path $sourcePath '..\client-deliverables\android'))
    if($Bundle) { $arguments+='-Bundle' }
    & powershell @arguments
    if($LASTEXITCODE -ne 0) { throw 'Release build failed; inspect the Gradle output above.' }
  } finally { Pop-Location }
} finally { Pop-Location }
exit 0
