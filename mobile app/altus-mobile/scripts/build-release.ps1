param([string]$Architectures='arm64-v8a', [switch]$Bundle, [string]$SigningDirectory='', [string]$OutputDirectory='')
$ErrorActionPreference = 'Stop'
$appPath = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
. (Join-Path $PSScriptRoot 'android-env.ps1')
if(-not $env:EXPO_PUBLIC_ALTUS_APP_KEY) { $env:EXPO_PUBLIC_ALTUS_APP_KEY='disabled' }
Push-Location $appPath
try {
  if (-not $SigningDirectory) {
    & node (Join-Path $PSScriptRoot 'prepare-signing.cjs')
    if ($LASTEXITCODE -ne 0) { throw 'Signing key preparation failed.' }
  }
  $credentialsPath = if ($SigningDirectory) { (Resolve-Path -LiteralPath $SigningDirectory).Path } else { Join-Path $env:LOCALAPPDATA 'ALTUS\Signing\com.altusgulf.knowledge' }
  & icacls.exe $credentialsPath /inheritance:r /grant:r "${env:USERNAME}:(OI)(CI)F" /grant:r 'SYSTEM:(OI)(CI)F' | Out-Null
  $signing = Get-Content -LiteralPath (Join-Path $credentialsPath 'android-signing.json') -Raw | ConvertFrom-Json
  $env:ALTUS_ANDROID_KEYSTORE = Join-Path $credentialsPath $signing.keystore
  $env:ALTUS_ANDROID_STORE_PASSWORD = $signing.storePassword
  $env:ALTUS_ANDROID_KEY_ALIAS = $signing.alias
  $env:ALTUS_ANDROID_KEY_PASSWORD = $signing.keyPassword
  & npx.cmd expo prebuild --platform android --no-install
  if ($LASTEXITCODE -ne 0) { throw 'Android prebuild failed.' }
  Push-Location (Join-Path $appPath 'android')
  try {
    $task = if ($Bundle) { ':app:bundleRelease' } else { ':app:assembleRelease' }
    & .\gradlew.bat $task "-PreactNativeArchitectures=$Architectures" --no-daemon --max-workers=2
    if ($LASTEXITCODE -ne 0) { throw 'Android release build failed.' }
  } finally { Pop-Location }
  $outputDir = if ($OutputDirectory) { $OutputDirectory } else { Join-Path $appPath '..\client-deliverables\android' }
  New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
  $appVersion = (Get-Content -LiteralPath (Join-Path $appPath 'app.json') -Raw | ConvertFrom-Json).expo.version
  $outputName = if ($Bundle) { "ALTUS-$appVersion-release.aab" } else { "ALTUS-$appVersion-release.apk" }
  $sourcePath = if ($Bundle) { 'android\app\build\outputs\bundle\release\app-release.aab' } else { 'android\app\build\outputs\apk\release\app-release.apk' }
  Copy-Item -LiteralPath (Join-Path $appPath $sourcePath) -Destination (Join-Path $outputDir $outputName) -Force
  Get-FileHash -LiteralPath (Join-Path $outputDir $outputName) -Algorithm SHA256 | Format-List
  Write-Host "Release build saved: $(Join-Path $outputDir $outputName)"
} finally {
  Remove-Item Env:ALTUS_ANDROID_KEYSTORE,Env:ALTUS_ANDROID_STORE_PASSWORD,Env:ALTUS_ANDROID_KEY_ALIAS,Env:ALTUS_ANDROID_KEY_PASSWORD -ErrorAction SilentlyContinue
  Pop-Location
}
exit 0
