# Configuracion comun del pipeline de video del TGS.
# Todos los scripts parten de aca.

# Raiz del proyecto TGS (el repo, que NO se modifica).
if (-not $env:TGS_PROYECTO) { $env:TGS_PROYECTO = 'C:\xampp\htdocs\tgs' }
$ProyectoTgs = $env:TGS_PROYECTO

# Carpeta de trabajo: todo lo generado vive aca.
if (-not $env:TGS_VIDEO_WORK) { $env:TGS_VIDEO_WORK = Join-Path $env:TEMP 'tgs-video' }
$RaizTrabajo = $env:TGS_VIDEO_WORK

$DirTools    = Join-Path $RaizTrabajo 'tools'
$DirWork     = Join-Path $RaizTrabajo 'work'
$DirApp      = Join-Path $DirWork 'app'
$DirCapturas = Join-Path $RaizTrabajo 'capturas'
$DirAudio    = Join-Path $RaizTrabajo 'audio'
$DirFrames   = Join-Path $RaizTrabajo 'frames'
$DirOut      = Join-Path $RaizTrabajo 'out'

$NodeExe = Join-Path $DirTools 'node-v26.13.0-win-x64\node.exe'
$DirSkill = Split-Path -Parent $PSScriptRoot

$UrlApp    = 'http://127.0.0.1:8099'
$UrlAssets = 'http://127.0.0.1:8098'

$BaseDatosCapturas = 'bluetech_capturas'
$UsuarioBd         = 'tgs_capturas'
$ClaveBd           = 'TgsCapturas2026'

function Crear-Carpetas {
    foreach ($d in @($DirTools, $DirWork, $DirApp, $DirCapturas, $DirAudio, $DirFrames, $DirOut)) {
        if (-not (Test-Path -LiteralPath $d)) { New-Item -ItemType Directory -Force -Path $d | Out-Null }
    }
}
