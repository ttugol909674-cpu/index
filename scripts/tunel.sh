#!/usr/bin/env bash
# =====================================================================
# TGS - Publicacion del sitio en internet con Cloudflare Tunnel
#
# La maquina virtual del proyecto esta en una red domestica, detras del
# router y sin IP publica. Cloudflare Tunnel resuelve eso sin tocar el
# router y sin abrir puertos: el servidor se conecta hacia afuera a
# Cloudflare y Cloudflare reenvia el trafico a la aplicacion.
#
# Ventajas para el proyecto:
#   - no hace falta configurar el router ni abrir puertos
#   - el sitio queda con HTTPS (certificado de Let's Encrypt automatico)
#   - sirve para la defensa: la URL funciona desde cualquier red
#
# Modo de uso:
#   bash scripts/tunel.sh iniciar     levanta el tunel temporal
#   bash scripts/tunel.sh detener     corta el tunel
#   bash scripts/tunel.sh estado      muestra la URL publica y el estado
#   bash scripts/tunel.sh instalar    deja cloudflared en ~/bin
# =====================================================================
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"
tgs_cargar_entorno || exit 1

CLOUDFLARED="$HOME/bin/cloudflared"
PID_FILE="$TGS_RAIZ/.tunel.pid"
URL_FILE="$TGS_RAIZ/.tunel.url"
LOG_FILE="$TGS_RAIZ/.tunel.log"

instalar_cloudflared() {
    if [ -x "$CLOUDFLARED" ]; then
        echo "[OK]   cloudflared ya esta instalado ($($CLOUDFLARED --version))"
        return 0
    fi

    echo "[....] descargando cloudflared"
    mkdir -p "$HOME/bin"
    if ! curl -fsSL -o /tmp/cloudflared \
        https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64; then
        echo "ERROR: no se pudo descargar cloudflared." >&2
        return 1
    fi
    install -m 755 /tmp/cloudflared "$CLOUDFLARED"
    echo "[OK]   cloudflared instalado en $CLOUDFLARED"
}

iniciar() {
    if [ ! -x "$CLOUDFLARED" ]; then
        instalar_cloudflared || exit 1
    fi

    if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
        echo "[OK]   el tunel ya esta corriendo (pid $(cat "$PID_FILE"))"
        estado
        return 0
    fi

    echo "==========================================================="
    echo " TGS - Publicacion en internet (Cloudflare Tunnel)"
    echo "==========================================================="
    echo
    echo "  URL local del sitio: http://localhost:${APP_PUERTO}"
    echo "  Iniciando el tunel..."
    echo

    nohup "$CLOUDFLARED" tunnel --no-autoupdate --url "http://localhost:${APP_PUERTO}" \
        > "$LOG_FILE" 2>&1 &
    echo $! > "$PID_FILE"

    # La URL aparece en el log cuando el tunel queda establecido.
    local url=""
    for _ in $(seq 1 30); do
        sleep 1
        url="$(grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' "$LOG_FILE" | head -1)"
        [ -n "$url" ] && break
    done

    if [ -z "$url" ]; then
        echo "ERROR: el tunel no levanto. Ultimas lineas del log:" >&2
        tail -15 "$LOG_FILE" >&2
        exit 1
    fi

    echo "$url" > "$URL_FILE"
    echo "[OK]   Tunel establecido"
    echo
    echo "  URL publica:  $url"
    echo "  Log del tunel: $LOG_FILE"
    echo
    echo "  Esta URL es temporal: cambia si se reinicia el tunel."
    echo "  Para un nombre fijo, ver la seccion 'Nombre permanente' del"
    echo "  documento del proyecto (capitulo 11)."
}

detener() {
    if [ -f "$PID_FILE" ]; then
        local pid
        pid="$(cat "$PID_FILE")"
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null
            echo "[OK]   tunel detenido (pid $pid)"
        fi
        rm -f "$PID_FILE" "$URL_FILE"
    else
        echo "  No hay ningun tunel corriendo."
    fi
}

estado() {
    if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
        echo "  Tunel: CORRIENDO (pid $(cat "$PID_FILE"))"
        [ -f "$URL_FILE" ] && echo "  URL publica: $(cat "$URL_FILE")"
    else
        echo "  Tunel: DETENIDO"
    fi

    local url=""
    [ -f "$URL_FILE" ] && url="$(cat "$URL_FILE")"

    if [ -n "$url" ]; then
        local codigo
        codigo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$url" 2>/dev/null || echo '000')"
        echo "  Sitio publico: HTTP $codigo"
        if [ "$codigo" = "200" ]; then
            echo "  [OK]   el sitio responde correctamente desde internet"
        else
            echo "  [AVISO] el sitio no responde desde internet (HTTP $codigo)"
        fi
    fi
}

case "${1:-estado}" in
    iniciar)   iniciar ;;
    detener)   detener ;;
    estado)    estado ;;
    instalar)  instalar_cloudflared ;;
    *)
        echo "  Uso: bash scripts/tunel.sh [iniciar|detener|estado|instalar]"
        exit 1
        ;;
esac
