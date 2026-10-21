#!/usr/bin/env bash
# =====================================================================
# TGS - Vigilante del tunel de publicacion
#
# El tunel temporal de Cloudflare puede caerse (se reinicia la maquina,
# se corta la red, el proceso muere). Este script lo revisa cada
# pocos minutos y lo vuelve a levantar si hace falta.
#
# Ademas escribe la URL publica vigente en dos lugares utiles:
#   - .tunel.url        (dentro del proyecto)
#   - url-publica.txt   (en la carpeta web, se puede consultar con el
#                        navegador en http://<ip>:8080/url-publica.txt)
#
# Se instala en el crontab del usuario:
#   */2 * * * * bash scripts/tunel_vigilante.sh
#   @reboot  bash scripts/tunel_vigilante.sh iniciar
# =====================================================================
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"
tgs_cargar_entorno || exit 1

PID_FILE="$TGS_RAIZ/.tunel.pid"
URL_FILE="$TGS_RAIZ/.tunel.url"
PUBLICO="$TGS_RAIZ/url-publica.txt"
LOG="$TGS_RAIZ/.tunel.log"

registrar() {
    local url="$1"
    echo "$url" > "$URL_FILE"
    {
        echo "TGS - Taller Global de Servicios"
        echo "URL publica de la aplicacion: $url"
        echo "Ultima actualizacion: $(date '+%Y-%m-%d %H:%M:%S')"
        echo
        echo "Para levantar el tunel a mano:  bash scripts/tunel.sh iniciar"
        echo "Para ver el estado:               bash scripts/tunel.sh estado"
    } > "$PUBLICO"
}

# El tunel esta vivo?
vivo() {
    [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null
}

iniciar() {
    if vivo; then
        registrar "$(cat "$URL_FILE" 2>/dev/null)"
        return 0
    fi

    echo "[$(date '+%H:%M:%S')] el tunel no esta vivo, se levanta de nuevo" >> "$LOG"

    local url
    url="$(bash "$TGS_RAIZ/scripts/tunel.sh" iniciar 2>/dev/null | grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' | head -1)"

    if [ -n "$url" ]; then
        registrar "$url"
        echo "[$(date '+%H:%M:%S')] tunel arriba: $url" >> "$LOG"
    else
        echo "[$(date '+%H:%M:%S')] ERROR: no se pudo levantar el tunel" >> "$LOG"
    fi
}

case "${1:-vigilar}" in
    iniciar) iniciar ;;
    vigilar)
        # El watchdog corre cada 2 minutos: solo hay que actuar si el
        # proceso murio o si la URL guardada no responde.
        if ! vivo; then
            iniciar
            exit 0
        fi

        local_url="$(cat "$URL_FILE" 2>/dev/null)"
        if [ -n "$local_url" ]; then
            codigo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$local_url" 2>/dev/null || echo '000')"
            if [ "$codigo" != "200" ]; then
                echo "[$(date '+%H:%M:%S')] la URL publica no responde (HTTP $codigo), se reinicia el tunel" >> "$LOG"
                bash "$TGS_RAIZ/scripts/tunel.sh" detener >/dev/null 2>&1
                iniciar
            fi
        fi
        ;;
    *)
        echo "  Uso: bash scripts/tunel_vigilante.sh [vigilar|iniciar]"
        ;;
esac
