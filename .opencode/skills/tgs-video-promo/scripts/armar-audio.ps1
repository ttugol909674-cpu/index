# Arma la pista de audio final: concatena los WAV de cada escena en el orden
# del manifiesto, con una respiracion de 0,3 s entre escenas (la misma que usa
# el timeline del video).
param(
    [string]$Manifiesto = '',
    [string]$Salida = '',
    [double]$Respiracion = 0.3
)

$raiz = if ($env:TGS_VIDEO_WORK) { $env:TGS_VIDEO_WORK } else { Join-Path $env:TEMP 'tgs-video' }
if (-not $Manifiesto) { $Manifiesto = Join-Path $raiz 'audio\manifiesto.json' }
if (-not $Salida) { $Salida = Join-Path $raiz 'audio\pista.wav' }

$ffmpeg = Join-Path $raiz 'work\node_modules\ffmpeg-static\ffmpeg.exe'
if (-not (Test-Path -LiteralPath $ffmpeg)) { throw "No se encuentra ffmpeg en $ffmpeg (correr preparar-entorno.ps1)" }

$escenas = Get-Content -LiteralPath $Manifiesto -Raw -Encoding UTF8 | ConvertFrom-Json

$lista = New-Object System.Collections.Generic.List[string]
$i = 0
foreach ($e in $escenas) {
    if ($i -gt 0) {
        $silencio = Join-Path (Split-Path $Manifiesto) ("sil_{0}.wav" -f $i)
        & $ffmpeg -hide_banner -loglevel error -y -f lavfi -i "anullsrc=r=22050:cl=mono" -t $Respiracion -c:a pcm_s16le $silencio
        $lista.Add("file '$($silencio -replace '\\','/')'")
    }
    $lista.Add("file '$($e.wav -replace '\\','/')'")
    $i++
}

$archivoLista = Join-Path (Split-Path $Manifiesto) 'concat.txt'
$lista | Set-Content -LiteralPath $archivoLista -Encoding ASCII

& $ffmpeg -hide_banner -loglevel error -y -f concat -safe 0 -i $archivoLista -c:a pcm_s16le -ar 44100 -ac 1 $Salida
if ($LASTEXITCODE -ne 0) { throw 'ffmpeg fallo al concatenar el audio' }

$info = & $ffmpeg -hide_banner -i $Salida 2>&1 | Select-String -Pattern 'Duration:'
Write-Host "Pista de audio: $Salida  ->  $info"

Get-ChildItem (Split-Path $Manifiesto) -Filter 'sil_*.wav' -ErrorAction SilentlyContinue | Remove-Item -Force
