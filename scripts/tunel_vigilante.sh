#!/usr/bin/env bash
# =====================================================================
# TGS - Vigilante del tunel de publicacion
#
# El tunel se publica como un servicio del sistema (tgs-tunel.service),
# que lo levanta al arrancar la maquina y lo reinicia si se cae. Este
# script es el que mantiene al dia el archivo con la URL publica, para
# que siempre se sepa a que direccion entrar.
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
SERVICIO="tgs-tunel.service"

registrar() {
    local url="$1"
    [ -z "$url" ] && return 1
    echo "$url" > "$URL_FILE"
    {
        echo "TGS - Taller Global de Servicios"
        echo "URL publica de la aplicacion: $url"
        echo "Ultima actualizacion: $(date '+%Y-%m-%d %H:%M:%S')"
        echo
        echo "El tunel corre como servicio del sistema: tgs-tunel.service"
        echo "Para levantarlo a mano:  bash scripts/tunel.sh iniciar"
        echo "Para ver el estado:      bash scripts/tunel.sh estado"
    } > "$PUBLICO"
    return 0
}

# El tunel corre como servicio?
servicio_activo() {
    systemctl is-active "$SERVICIO" >/dev/null 2>&1
}

# URL que tiene el servicio en su registro.
url_del_servicio() {
    grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' "$LOG" 2>/dev/null | tail -1
}

# El tunel manual sigue vivo?
vivo() {
    [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null
}

iniciar() {
    # Si el servicio del sistema esta activo, solo se actualiza la URL.
    if servicio_activo; then
        local url
        url="$(url_del_servicio)"
        if [ -n "$url" ]; then
            registrar "$url"
            echo "[$(date '+%H:%M:%S')] URL actualizada desde el servicio: $url" >> "$LOG"
        fi
        return 0
    fi

    # Si no hay servicio, se levanta el tunel a mano.
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
        # Si el servicio esta activo, la URL puede haber cambiado (por
        # ejemplo, tras un reinicio): se vuelve a publicar.
        if servicio_activo; then
            url="$(url_del_servicio)"
            if [ -n "$url" ] && [ "$url" != "$(cat "$URL_FILE" 2>/dev/null)" ]; then
                registrar "$url"
                echo "[$(date '+%H:%M:%S')] cambio de URL publica: $url" >> "$LOG"
            fi
            exit 0
        fi

        # Sin servicio: se comprueba el tunel manual y, si no responde,
        # se levanta de nuevo.
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
