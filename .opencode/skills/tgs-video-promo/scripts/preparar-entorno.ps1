# Prepara el tooling del pipeline: Node portable, paquete npm y bundle de Motion
# para el navegador. No instala nada en el sistema: todo queda en la carpeta
# de trabajo.
param([string]$VersionNode = 'v26.13.0')

. "$PSScriptRoot\config.ps1"
Crear-Carpetas

# ---------------------------------------------------------------- Node portable
if (-not (Test-Path -LiteralPath $NodeExe)) {
    $zip = Join-Path $DirTools 'node.zip'
    $url = "https://nodejs.org/dist/$VersionNode/node-$VersionNode-win-x64.zip"
    Write-Host "Descargando Node $VersionNode ..."
    Invoke-WebRequest -Uri $url -OutFile $zip -TimeoutSec 600
    Expand-Archive -LiteralPath $zip -DestinationPath $DirTools -Force
    Remove-Item -LiteralPath $zip -Force
}
Write-Host "Node: $(& $NodeExe -v)"

# ---------------------------------------------------------------- paquetes npm
$npm = Join-Path $DirTools "node-$VersionNode-win-x64\npm.cmd"
$env:PATH = "$(Join-Path $DirTools "node-$VersionNode-win-x64");$env:PATH"

if (-not (Test-Path -LiteralPath (Join-Path $DirWork 'package.json'))) {
    "{`"name`":`"tgs-video`",`"private`":true,`"type`":`"module`"}" |
        Set-Content -LiteralPath (Join-Path $DirWork 'package.json') -Encoding UTF8
}

$paquetes = @('playwright-core', 'motion', 'ffmpeg-static', 'esbuild')
# npm install tiene que correr DENTRO de la carpeta de trabajo: si corre en el
# directorio actual deja package.json y node_modules en el repositorio.
Push-Location $DirWork
try {
    & $npm install --no-audit --no-fund @paquetes 2>&1 | Select-Object -Last 3

    # npm 11 exige aprobar los scripts de instalacion de forma explicita:
    foreach ($p in @('esbuild', 'ffmpeg-static')) {
        & $npm install-scripts approve $p 2>&1 | Select-Object -Last 2
    }
} finally {
    Pop-Location
}

$esbuild = Get-ChildItem (Join-Path $DirWork 'node_modules\@esbuild') -Recurse -Filter 'esbuild.exe' -ErrorAction SilentlyContinue |
    Select-Object -First 1 -ExpandProperty FullName
if (-not $esbuild) { throw 'No se instalo el binario de esbuild. Ejecutar: npm install-scripts approve esbuild' }

$ffmpeg = Join-Path $DirWork 'node_modules\ffmpeg-static\ffmpeg.exe'
if (-not (Test-Path -LiteralPath $ffmpeg)) { throw 'No se instalo ffmpeg-static' }

# ---------------------------------------------------------------- bundle de Motion
# El paquete 'motion' es ESM con especificadores sin resolver: el navegador no
# lo puede importar solo. Se empaqueta a un IIFE con esbuild.
$entrada = Join-Path $DirWork 'motion-entry.js'
@'
export { animate, animateMini, stagger, spring, motionValue, frame, cancelFrame, transform } from 'motion';
'@ | Set-Content -LiteralPath $entrada -Encoding UTF8

& $esbuild $entrada --bundle --format=iife --global-name=Motion `
    --outfile=(Join-Path $DirWork 'motion.bundle.js') --minify 2>&1 | Select-Object -Last 2

# --- copiar los scripts de la skill a la carpeta de trabajo
# Node resuelve 'playwright-core' desde el directorio del script, asi que los
# .mjs tienen que vivir junto a node_modules.
Write-Host ''
Write-Host 'Copiando los scripts a la carpeta de trabajo ...'
foreach ($f in @('capturar.mjs', 'render.mjs', 'video.html', 'guion.json', 'voz.ps1', 'armar-audio.ps1', 'encodear.ps1', 'smoke.php')) {
    $origen = Join-Path (Join-Path $DirSkill 'scripts') $f
    if (Test-Path -LiteralPath $origen) {
        Copy-Item -LiteralPath $origen -Destination (Join-Path $DirWork $f) -Force
    }
}

Write-Host ''
Write-Host 'Tooling listo:'
Write-Host "  Node      $NodeExe"
Write-Host "  ffmpeg    $ffmpeg"
Write-Host "  Motion    $(Join-Path $DirWork 'motion.bundle.js')"
Write-Host "  Scripts   $DirWork"
Write-Host "  Chrome    (el que ya esta instalado en el sistema)"
