param([Parameter(Mandatory=$true)][string]$Manifest)
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Speech
$altusNarrator=New-Object System.Speech.Synthesis.SpeechSynthesizer
try {
    $altusNarrator.SelectVoice('Microsoft Zira Desktop')
    $altusNarrator.Rate=0
    $altusEntries=Get-Content -LiteralPath $Manifest -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach($altusEntry in $altusEntries) {
        $altusNarrator.SetOutputToWaveFile($altusEntry.file)
        $altusNarrator.Speak($altusEntry.text)
        $altusNarrator.SetOutputToNull()
    }
} finally { $altusNarrator.Dispose() }
