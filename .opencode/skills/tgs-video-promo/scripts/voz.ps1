# Genera la voz en off escena por escena con la voz de Windows.
# Salida: un WAV por escena + manifiesto.json con la duracion real de cada una.
#
# El timeline del video se arma DESPUES, con esas duraciones: por eso la voz
# y las imagenes quedan sincronizadas por construccion.
param(
    [string]$Guion = '',
    [string]$Salida = ''
)

$raiz = if ($env:TGS_VIDEO_WORK) { $env:TGS_VIDEO_WORK } else { Join-Path $env:TEMP 'tgs-video' }
if (-not $Guion) { $Guion = if (Test-Path (Join-Path $PSScriptRoot 'guion.json')) { Join-Path $PSScriptRoot 'guion.json' } else { Join-Path $raiz 'work\guion.json' } }
if (-not $Salida) { $Salida = Join-Path $raiz 'audio' }

Add-Type -AssemblyName System.Speech

if (-not (Test-Path -LiteralPath $Salida)) { New-Item -ItemType Directory -Force -Path $Salida | Out-Null }

# Voz preferida en espanol; si no esta, la primera instalada.
$voz = $null
foreach ($c in @('Microsoft Helena Desktop', 'Microsoft Zira Desktop')) {
    $s = New-Object System.Speech.Synthesis.SpeechSynthesizer
    if ($s.GetInstalledVoices() | Where-Object { $_.VoiceInfo.Name -eq $c }) { $voz = $c; break }
    $s.Dispose()
}
if (-not $voz) {
    $s = New-Object System.Speech.Synthesis.SpeechSynthesizer
    $voz = ($s.GetInstalledVoices() | Select-Object -First 1).VoiceInfo.Name
    $s.Dispose()
}
Write-Host "Voz: $voz"

$escenas = Get-Content -LiteralPath $Guion -Raw -Encoding UTF8 | ConvertFrom-Json
$manifiesto = @()

foreach ($e in $escenas) {
    $s = New-Object System.Speech.Synthesis.SpeechSynthesizer
    $s.SelectVoice($voz)
    $s.Rate = 0
    $s.Volume = 100
    $destino = Join-Path $Salida ("{0}.wav" -f $e.id)
    $s.SetOutputToWaveFile($destino)
    $s.Speak($e.texto)
    $s.Dispose()
    $bytes = (Get-Item -LiteralPath $destino).Length
    # WAV PCM 16 bits mono a 22050 Hz: bytes / (22050 * 2)
    $segundos = [Math]::Round($bytes / 44100, 2)
    Write-Host ("  {0,-18} {1,6:N2} s" -f $e.id, $segundos)
    $manifiesto += [pscustomobject]@{ id = $e.id; titulo = $e.titulo; texto = $e.texto; wav = $destino; duracion = $segundos }
}

$manifiesto | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $Salida 'manifiesto.json') -Encoding UTF8
$total = ($manifiesto | Measure-Object -Property duracion -Sum).Sum
Write-Host ("Duracion total de la voz: {0:N2} s" -f $total)
