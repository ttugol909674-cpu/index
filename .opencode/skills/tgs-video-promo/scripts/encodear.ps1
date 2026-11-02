# Encoda los cuadros y la pista de audio en un MP4 final (H.264 + AAC).
# Para agregar musica de fondo, agregar "-i musica.mp3" y mezclar con
# -filter_complex "amix" o -c:a aac con delay.
param(
    [string]$Cuadros = '',
    [string]$Audio = '',
    [string]$Salida = '',
    [int]$Fps = 30,
    [string]$Crf = '19'
)

$raiz = if ($env:TGS_VIDEO_WORK) { $env:TGS_VIDEO_WORK } else { Join-Path $env:TEMP 'tgs-video' }
if (-not $Cuadros) { $Cuadros = Join-Path $raiz 'frames\f%05d.jpg' }
if (-not $Audio) { $Audio = Join-Path $raiz 'audio\pista.wav' }
if (-not $Salida) { $Salida = Join-Path $raiz 'out\tgs-video.mp4' }

$ffmpeg = Join-Path $raiz 'work\node_modules\ffmpeg-static\ffmpeg.exe'
if (-not (Test-Path -LiteralPath $ffmpeg)) { throw "No se encuentra ffmpeg en $ffmpeg (correr preparar-entorno.ps1)" }
New-Item -ItemType Directory -Force -Path (Split-Path $Salida) | Out-Null

& $ffmpeg -hide_banner -loglevel warning -stats -y `
    -framerate $Fps -i $Cuadros `
    -i $Audio `
    -c:v libx264 -preset medium -crf $Crf -pix_fmt yuv420p -r $Fps `
    -c:a aac -b:a 192k -ar 44100 `
    -movflags +faststart `
    $Salida

if ($LASTEXITCODE -ne 0) { throw "ffmpeg termino con error $LASTEXITCODE" }

$info = & $ffmpeg -hide_banner -i $Salida 2>&1 | Select-String -Pattern 'Duration:|Stream #'
Write-Host "`nVideo final: $Salida"
$info | ForEach-Object { Write-Host "  $($_.ToString().Trim())" }
Write-Host ("  Tamano: {0} MB" -f [Math]::Round((Get-Item -LiteralPath $Salida).Length / 1MB, 2))
