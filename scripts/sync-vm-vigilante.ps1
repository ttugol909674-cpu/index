# =====================================================================
# TGS - Vigilante del sincronizador
#
# El sincronizador (scripts/sync-vm.ps1) queda corriendo en segundo
# plano. Si se cae (reinicio del equipo, suspension del portatil,
# cierre de sesion) este vigilante lo vuelve a levantar cada minuto.
#
# Se arranca desde la carpeta de Inicio de Windows, asi que tambien
# arranca solo al iniciar sesion.
# =====================================================================
param(
    [string]$Script = "C:\xampp\htdocs\tgs\scripts\sync-vm.ps1",
    [int]$CadaCuanto = 60
)

$log = Join-Path $env:LOCALAPPDATA 'TGS\sync-vigila.log'
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null

# Solo puede haber un vigilante. Si ya hay otro corriendo, este sale.
$mutex = New-Object System.Threading.Mutex($false, 'TGS_Vigilante_Sincronizador')
try {
    $esElUnico = $mutex.WaitOne(0)
} catch [System.Threading.AbandonedMutexException] {
    $esElUnico = $true
}
if (-not $esElUnico) {
    exit 0
}

function Registrar([string]$Texto) {
    Add-Content -Path $log -Value ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Texto) -Encoding utf8
}

Registrar "vigilante arrancado (revisa cada $CadaCuanto s)"

while ($true) {
    try {
        $vivos = @(Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" | Where-Object {
            $_.ProcessId -ne $PID -and
            $_.CommandLine -and
            $_.CommandLine -match 'sync-vm\.ps1' -and
            $_.CommandLine -notmatch 'vigila'
        })

        if ($vivos.Count -eq 0) {
            Start-Process -FilePath "powershell.exe" `
                -ArgumentList "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", $Script `
                -WindowStyle Hidden
            Start-Sleep -Seconds 5
            Registrar "el sincronizador no estaba vivo: lo volvio a levantar"
        }
    } catch {
        Registrar "ERROR: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds $CadaCuanto
}
