# =====================================================================
# TGS - Lanzador del sincronizador hacia la maquina virtual
#
# El sincronizador (scripts/sync-vm.ps1) queda corriendo en segundo
# plano. Si por lo que sea se cae (reinicio del equipo, suspension,
# cierre de sesion), este lanzador lo vuelve a levantar.
#
# Se usa desde una tarea programada que corre cada 5 minutos y tambien
# al iniciar sesion.
# =====================================================================
param(
    [string]$Script = "C:\xampp\htdocs\tgs\scripts\sync-vm.ps1"
)

# Se busca una instancia viva del sincronizador (y no este mismo lanzador).
$vivos = @(Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" | Where-Object {
    $_.ProcessId -ne $PID -and
    $_.CommandLine -and
    $_.CommandLine -match 'sync-vm\.ps1' -and
    $_.CommandLine -notmatch 'sync-vm-iniciar'
})

if ($vivos.Count -gt 0) {
    exit 0
}

Start-Process -FilePath "powershell.exe" `
    -ArgumentList "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", $Script `
    -WindowStyle Hidden
Start-Sleep -Seconds 3
