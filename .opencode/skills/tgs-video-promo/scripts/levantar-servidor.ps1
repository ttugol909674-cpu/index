# Copia el codigo del proyecto a la carpeta de trabajo y levanta la app con
# `php -S`, apuntando al clon de la base. El repositorio original NO se toca.
param([switch]$Detener)

. "$PSScriptRoot\config.ps1"
Crear-Carpetas

if ($Detener) {
    Get-Process php -ErrorAction SilentlyContinue |
        Where-Object { $_.Id -ne $PID } | ForEach-Object { }
    Write-Host 'Para detener los servidores temporales, cerrar las ventanas de php.exe o usar: Stop-Process -Name php -Force'
    exit 0
}

$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $php)) { throw "No se encuentra PHP en $php" }

# --- copia del codigo (sin .git, docs, entrega, tests ni scripts)
Write-Host "Copiando el codigo de $ProyectoTgs a $DirApp ..."
if (Test-Path -LiteralPath $DirApp) { Remove-Item -Recurse -Force $DirApp }
New-Item -ItemType Directory -Force -Path $DirApp | Out-Null
Copy-Item (Join-Path $ProyectoTgs '*.php') $DirApp
foreach ($d in @('css', 'js', 'lang', 'includes')) {
    Copy-Item -Recurse (Join-Path $ProyectoTgs $d) $DirApp
}
Copy-Item (Join-Path $ProyectoTgs 'Logo.png') $DirApp

# --- .env propio de la copia (config.php solo lee .env y .env.local)
@"
APP_ENV=local
APP_DEBUG=1
APP_PUERTO=8099
DB_HOST=127.0.0.1
DB_NAME=$BaseDatosCapturas
DB_USER=$UsuarioBd
DB_PASS=$ClaveBd
DB_CHARSET=utf8mb4
DB_PUERTO=3306
REGISTRO_ABIERTO=1
"@ | Set-Content -LiteralPath (Join-Path $DirApp '.env') -Encoding UTF8

# --- servidor de la app
# OJO: Start-Process no admite el mismo archivo para stdout y stderr.
Start-Process -FilePath $php -ArgumentList '-S', '127.0.0.1:8099', '-t', $DirApp `
    -WorkingDirectory $DirApp `
    -RedirectStandardOutput (Join-Path $DirWork 'server-app.out.log') `
    -RedirectStandardError (Join-Path $DirWork 'server-app.err.log') -WindowStyle Hidden | Out-Null

# --- servidor de assets (capturas + bundle de Motion) para el render
Start-Process -FilePath $php -ArgumentList '-S', '127.0.0.1:8098', '-t', $RaizTrabajo `
    -WorkingDirectory $RaizTrabajo `
    -RedirectStandardOutput (Join-Path $DirWork 'server-assets.out.log') `
    -RedirectStandardError (Join-Path $DirWork 'server-assets.err.log') -WindowStyle Hidden | Out-Null

Start-Sleep -Seconds 3
foreach ($u in @('http://127.0.0.1:8099/index.php', 'http://127.0.0.1:8098/capturas/')) {
    try {
        $r = Invoke-WebRequest -Uri $u -UseBasicParsing -TimeoutSec 20
        Write-Host "  $u -> $($r.StatusCode)"
    } catch {
        Write-Host "  $u -> FALLA: $($_.Exception.Message)"
    }
}
Write-Host ''
Write-Host 'App:      http://127.0.0.1:8099'
Write-Host 'Assets:   http://127.0.0.1:8098'
Write-Host '(solo localhost: php -S ignora el .htaccess que bloquea .env y db/)'

