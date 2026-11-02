<#
.SYNOPSIS
    Sincroniza los cambios de codigo de esta maquina (Windows) con la
    maquina virtual del proyecto, sin necesidad de commit ni push.

.DESCRIPTION
    La web publicada corre dentro de la VM, con el codigo montado en
    caliente. Este script vigila el proyecto local y copia a la VM los
    archivos modificados, para que lo que editas en Visual Studio este
    en linea de inmediato.

    Los datos NO se copian: la copia de desarrollo de Windows ya usa la
    base de datos de la VM (ver .env.local), asi que hay una sola fuente
    de datos entre el desarrollo y la web publicada.

.PARAMETER Intervalo
    Segundos entre cada revision (por omision 5).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts/sync-vm.ps1
#>
param(
    [string]$Servidor  = "192.168.1.20",
    [string]$Usuario   = "taller",
    [string]$Remoto    = "/home/taller/Desktop/tgs",
    [string]$Clave     = "$env:USERPROFILE\.ssh\tgs_sync",
    [string]$MacVm     = "080027F61961",
    [int]$Intervalo    = 5,
    [switch]$UnaVez
)

$ErrorActionPreference = 'Continue'
$Raiz      = Split-Path -Parent $PSScriptRoot
$EstadoDir = Join-Path $env:LOCALAPPDATA 'TGS'
$ArchivoEstado = Join-Path $EstadoDir 'sync-estado.tsv'
$Log       = Join-Path $EstadoDir 'sync-vm.log'

New-Item -ItemType Directory -Force -Path $EstadoDir | Out-Null

# Solo puede haber una instancia: si ya hay un sincronizador corriendo
# (por ejemplo, uno arrancado a mano y otro del inicio de sesion), este
# script se retira.
$mutex = New-Object System.Threading.Mutex($false, 'TGS_Sincronizador_VM')
try {
    $esElUnico = $mutex.WaitOne(0)
} catch [System.Threading.AbandonedMutexException] {
    # El anterior owner termino de golpe (reinicio, suspension): el
    # mutex queda abandonado y este proceso puede tomar el relevo.
    $esElUnico = $true
}
if (-not $esElUnico) {
    Write-Host 'Ya hay un sincronizador en marcha. No se arranca otro.'
    exit 0
}

function Escribir-Log([string]$Texto) {
    $linea = "[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Texto
    Add-Content -Path $Log -Value $linea -Encoding utf8
    Write-Host $linea
}

<#
    La VM toma la IP por DHCP, asi que puede cambiar (ya paso: 192.168.1.12
    luego 192.168.1.20). Si el servidor configurado no responde, se busca
    en la tabla ARP por la MAC de la tarjeta de la VM y se usa esa IP.
#>
function Test-PuertoSsh([string]$Host_, [int]$Puerto = 22, [int]$EsperaMs = 1500) {
    $cliente = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $cliente.BeginConnect($Host_, $Puerto, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne($EsperaMs, $false)) { return $false }
        return $cliente.Connected
    } catch {
        return $false
    } finally {
        $cliente.Close()
    }
}

function Get-IpDeLaVm([string]$Mac) {
    $normalizada = ($Mac -replace '[^0-9A-Fa-f]', '').ToUpper()
    if ($normalizada.Length -ne 12) { return $null }
    $conGuiones = '{0}-{1}-{2}-{3}-{4}-{5}' -f $normalizada.Substring(0,2), $normalizada.Substring(2,2), `
        $normalizada.Substring(4,2), $normalizada.Substring(6,2), $normalizada.Substring(8,2), $normalizada.Substring(10,2)
    try {
        $linea = (arp -a | Select-String -Pattern $conGuiones -SimpleMatch | Select-Object -First 1)
        if ($linea) {
            if ($linea -match '(\d+\.\d+\.\d+\.\d+)') { return $Matches[1] }
        }
    } catch { }
    return $null
}

function Resolve-Servidor {
    if (Test-PuertoSsh $Servidor) { return $Servidor }
    $ip = Get-IpDeLaVm $MacVm
    if ($ip -and (Test-PuertoSsh $ip)) {
        Escribir-Log "El servidor $Servidor no responde; la VM esta en $ip (buscada por la MAC $MacVm)"
        return $ip
    }
    return $Servidor
}


# --- Que carpetas nunca se recorren (son grandes o son de la maquina) ---
$DirectoriosIgnorados = @(
    '.git', 'entrega', '.obsidian', 'CARPETA-OBCIDIAN',
    'backups', 'node_modules', '.idea', '.vs'
)

# --- Que archivos nunca se copian (configuracion propia de cada maquina) ---
$ArchivosIgnorados = @(
    '.env', '.env.local', '.env.example', '.env.lf', '.env.example.lf',
    'url-publica.txt', '.tunel.url', '.tunel.log', '.tunel.pid',
    '.tunel_nombrado.url', '.tunel_nombrado.log', '.tunel_nombrado.pid',
    # Los crea opencode para sus plugins: no forman parte del proyecto.
    'package.json', 'package-lock.json'
)

# --- Extensiones que no se copian (binarios grandes ya versioningados) ---
# Las imagenes SI se copian: el sincronizador detecta si un archivo es
# binario y en ese caso lo transfiere tal cual, sin tocar sus bytes.
$ExtensionesIgnoradas = @('.lf', '.pdf', '.docx', '.7z', '.ova')

function Get-ArchivosSincronizables([string]$Base) {
    $cola = New-Object System.Collections.Stack
    $cola.Push($Base)
    $resultado = New-Object System.Collections.ArrayList

    while ($cola.Count -gt 0) {
        $dir = $cola.Pop()
        foreach ($sub in Get-ChildItem -LiteralPath $dir -Directory -Force -ErrorAction SilentlyContinue) {
            if ($DirectoriosIgnorados -notcontains $sub.Name) { $cola.Push($sub.FullName) }
        }
        foreach ($arch in Get-ChildItem -LiteralPath $dir -File -Force -ErrorAction SilentlyContinue) {
            if ($arch.Name -like '*.bak') { continue }
            if ($ArchivosIgnorados -contains $arch.Name) { continue }
            if ($ExtensionesIgnoradas -contains $arch.Extension) { continue }
            [void]$resultado.Add($arch)
        }
    }
    return $resultado
}

function Get-Firma([System.IO.FileInfo]$Archivo) {
    return "{0}|{1}" -f $Archivo.LastWriteTimeUtc.Ticks, $Archivo.Length
}

<#
    Prepara un archivo para mandarlo a la VM.

    El repositorio exige finales de linea LF (ver .gitattributes: "* text=auto
    eol=lf"). Un archivo que llega a Linux con CRLF hace que git lo marque como
    modificado y rompe los scripts de bash. Por eso los archivos de texto se
    normalizan a LF antes de copiarse; los binarios se copian tal cual.
#>
function Preparar-Archivo([string]$Ruta) {
    $bytes = [System.IO.File]::ReadAllBytes($Ruta)
    $limite = [Math]::Min(4096, $bytes.Length)
    $esBinario = $false
    for ($i = 0; $i -lt $limite; $i++) {
        if ($bytes[$i] -eq 0) { $esBinario = $true; break }
    }
    if ($esBinario) { return $null }   # se copia tal cual

    $crlf = 0
    for ($i = 1; $i -lt $bytes.Length; $i++) {
        if ($bytes[$i] -eq 10 -and $bytes[$i - 1] -eq 13) { $crlf++ }
    }
    if ($crlf -eq 0) { return $null }   # ya esta en LF

    $salida = New-Object System.IO.MemoryStream
    for ($i = 0; $i -lt $bytes.Length; $i++) {
        if ($bytes[$i] -eq 13 -and ($i + 1) -lt $bytes.Length -and $bytes[$i + 1] -eq 10) { continue }
        $salida.WriteByte($bytes[$i])
    }
    $destino = Join-Path $env:TEMP ("tgs-sync-" + [Guid]::NewGuid().ToString('N'))
    [System.IO.File]::WriteAllBytes($destino, $salida.ToArray())
    return $destino
}

function Leer-Estado {
    $estado = @{}
    if (Test-Path $ArchivoEstado) {
        foreach ($linea in Get-Content -Path $ArchivoEstado -Encoding utf8) {
            if ($linea -match '^(.+?)\|(.+?)\|(.+)$') {
                $estado[$Matches[3]] = "$($Matches[1])|$($Matches[2])"
            }
        }
    }
    return $estado
}

function Guardar-Estado($Estado) {
    $lineas = foreach ($clave in $Estado.Keys) {
        $firma = $Estado[$clave] -split '\|'
        "$($firma[0])|$($firma[1])|$clave"
    }
    Set-Content -Path $ArchivoEstado -Value $lineas -Encoding utf8
}

$OpcionesSsh = @(
    '-i', $Clave, '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15',
    '-o', 'StrictHostKeyChecking=yes', '-o', 'LogLevel=ERROR'
)

Escribir-Log "Sincronizador arrancado -> $Usuario@${Servidor}:$Remoto"

# La IP de la VM puede cambiar (toma la IP por DHCP). Se resuelve una vez al
# arrancar y se vuelve a resolver si el servidor deja de responder.
$ServidorEfectivo = Resolve-Servidor

while ($true) {
    try {
        $estado = Leer-Estado
        $cambios = New-Object System.Collections.ArrayList

        foreach ($archivo in Get-ArchivosSincronizables $Raiz) {
            $rel = $archivo.FullName.Substring($Raiz.Length).TrimStart('\', '/') -replace '\\', '/'
            $firma = Get-Firma $archivo
            if (-not $estado.ContainsKey($rel) -or $estado[$rel] -ne $firma) {
                [void]$cambios.Add(@{ Rel = $rel; Full = $archivo.FullName; Firma = $firma })
            }
        }

        if ($cambios.Count -gt 0) {
            # 1) Crear en la VM los directorios que hagan falta (una sola conexion).
            $dirs = @{}
            foreach ($cambio in $cambios) {
                $dir = [System.IO.Path]::GetDirectoryName($cambio.Rel) -replace '\\', '/'
                if ($dir) { $dirs[$dir] = $true }
            }
            if ($dirs.Count -gt 0) {
                $orden = ($dirs.Keys | ForEach-Object { "mkdir -p '$Remoto/$_'" }) -join '; '
                & ssh @OpcionesSsh "$Usuario@$ServidorEfectivo" $orden 2>&1 | Out-Null
            }

            # 2) Copiar los archivos (normalizando los finales de linea a LF).
            $copiados = 0
            foreach ($cambio in $cambios) {
                $temporal = Preparar-Archivo $cambio.Full
                $origen = if ($temporal) { $temporal } else { $cambio.Full }
                & scp @OpcionesSsh $origen "$Usuario@$ServidorEfectivo`:$Remoto/$($cambio.Rel)" 2>&1 | Out-Null
                if ($temporal) { Remove-Item $temporal -Force -ErrorAction SilentlyContinue }
                if ($LASTEXITCODE -eq 0) {
                    $estado[$cambio.Rel] = $cambio.Firma
                    $copiados++
                }
            }
            Guardar-Estado $estado
            Escribir-Log "copiados $copiados archivo(s): $(($cambios | ForEach-Object { $_.Rel }) -join ', ')"
        }
    } catch {
        Escribir-Log "ERROR: $($_.Exception.Message)"
    }

    # Si el servidor dejo de responder, se vuelve a resolver la IP de la VM.
    if (-not (Test-PuertoSsh $ServidorEfectivo)) { $ServidorEfectivo = Resolve-Servidor }

    if ($UnaVez) { break }
    Start-Sleep -Seconds $Intervalo
}


