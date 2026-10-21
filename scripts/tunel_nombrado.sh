#!/usr/bin/env bash
# =====================================================================
# TGS - Tunel con nombre de Cloudflare (URL permanente y gratuita)
#
# El tunel temporal (trycloudflare.com) cambia de direccion cada vez
# que se reinicia. El "tunel con nombre" se crea una sola vez en la
# cuenta de Cloudflare y conserva su identificador para siempre, de
# modo que el sitio queda siempre en la misma direccion.
#
# Pasos (una sola vez, con la cuenta de Cloudflare):
#   1) bash scripts/tunel_nombrado.sh autorizar   -> abre la pagina
#      para autorizar esta maquina (equivale a "cloudflared tunnel login")
#   2) bash scripts/tunel_nombrado.sh crear      -> crea el tunel y
#      escribe la configuracion
#   3) bash scripts/tunel_nombrado.sh iniciar    -> lo deja funcionando
#
# Para levantar el tunel con nombre:
#   bash scripts/tunel_nombrado.sh iniciar
# Para verlo:
#   bash scripts/tunel_nombrado.sh estado
# =====================================================================
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"
tgs_cargar_entorno || exit 1

CLOUDFLARED="$HOME/bin/cloudflared"
CF_DIR="$HOME/.cloudflared"
NOMBRE_TUNEL="tgs"
CONFIG="$CF_DIR/config.yml"
PID_FILE="$TGS_RAIZ/.tunel_nombrado.pid"
URL_FILE="$TGS_RAIZ/.tunel_nombrado.url"

# --- Utilidades ------------------------------------------------------------

# Identificador del tunel (UUID), leyendo el archivo de credenciales.
uuid_del_tunel() {
    ls "$CF_DIR"/*.json 2>/dev/null | head -1 | xargs -r basename | sed 's/\.json$//'
}

# Direccion publica del tunel con nombre.
url_del_tunel() {
    local uuid
    uuid="$(uuid_del_tunel)"
    [ -n "$uuid" ] && echo "https://$uuid.cfargotunnel.com"
}

autorizar() {
    if [ ! -x "$CLOUDFLARED" ]; then
        echo "ERROR: cloudflared no esta instalado." >&2
        echo "       Bajalo con: bash scripts/tunel.sh instalar" >&2
        exit 1
    fi

    if [ -f "$CF_DIR/cert.pem" ]; then
        echo "[OK]   Esta maquina ya esta autorizada en tu cuenta de Cloudflare."
        return 0
    fi

    echo "==========================================================="
    echo " Autorizacion de la maquina en Cloudflare"
    echo "==========================================================="
    echo
    echo "  Se abrio (o se abrira) una pagina en el navegador."
    echo "  Inicia sesion con tu cuenta de Cloudflare y aprueba."
    echo
    echo "  Si el navegador no se abrio, copiala de la terminal."
    echo

    "$CLOUDFLARED" tunnel login

    if [ -f "$CF_DIR/cert.pem" ]; then
        echo
        echo "[OK]   Autorizacion completada."
    else
        echo
        echo "ERROR: no se completo la autorizacion." >&2
        exit 1
    fi
}

crear() {
    if [ ! -f "$CF_DIR/cert.pem" ]; then
        echo "ERROR: la maquina todavia no esta autorizada." >&2
        echo "       Ejecuta primero: bash scripts/tunel_nombrado.sh autorizar" >&2
        exit 1
    fi

    local uuid
    uuid="$(uuid_del_tunel)"

    if [ -z "$uuid" ]; then
        echo "[....] creando el tunel con nombre '$NOMBRE_TUNEL'"
        "$CLOUDFLARED" tunnel create "$NOMBRE_TUNEL" 2>&1 | grep -v "^$" | sed 's/^/  /'
        uuid="$(uuid_del_tunel)"
    else
        echo "[OK]   el tunel ya existe (uuid $uuid)"
    fi

    if [ -z "$uuid" ]; then
        echo "ERROR: no se pudo crear el tunel." >&2
        exit 1
    fi

    # Configuracion del tunel: todo lo que llegue se reenvia al sitio local.
    cat > "$CONFIG" <<EOF
tunnel: $uuid
credentials-file: $CF_DIR/$uuid.json

ingress:
  - service: http://localhost:${APP_PUERTO}
  - service: http_status:404
EOF
    chmod 600 "$CONFIG"
    echo "[OK]   configuracion escrita en $CONFIG"

    echo
    echo "  URL publica permanente del tunel:"
    echo "    $(url_del_tunel)"
    echo
    echo "  Para agregarle un nombre propio (si tenes un dominio en"
    echo "  Cloudflare) se usa:  cloudflared tunnel route dns $NOMBRE_TUNEL <subdominio>.<tu-dominio>"
}

iniciar() {
    local uuid
    uuid="$(uuid_del_tunel)"

    if [ -z "$uuid" ]; then
        echo "ERROR: no hay ningun tunel con nombre. Ejecuta: bash scripts/tunel_nombrado.sh crear" >&2
        exit 1
    fi

    if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
        echo "[OK]   el tunel ya esta corriendo (pid $(cat "$PID_FILE"))"
        estado
        return 0
    fi

    nohup "$CLOUDFLARED" tunnel --no-autoupdate run "$NOMBRE_TUNEL" \
        > "$TGS_RAIZ/.tunel_nombrado.log" 2>&1 &
    echo $! > "$PID_FILE"
    sleep 6

    url_del_tunel > "$URL_FILE"

    echo "[OK]   Tunel con nombre iniciado."
    echo "  URL publica: $(url_del_tunel)"
    echo "  Log: $TGS_RAIZ/.tunel_nombrado.log"
}

detener() {
    if [ -f "$PID_FILE" ]; then
        local pid
        pid="$(cat "$PID_FILE")"
        kill "$pid" 2>/dev/null
        rm -f "$PID_FILE"
        echo "[OK]   tunel detenido"
    else
        echo "  no hay ningun tunel con nombre corriendo"
    fi
}

estado() {
    if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
        echo "  Tunel con nombre: CORRIENDO (pid $(cat "$PID_FILE"))"
    else
        echo "  Tunel con nombre: DETENIDO"
    fi

    local url
    url="$(url_del_tunel)"
    if [ -n "$url" ]; then
        local codigo
        codigo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 25 "$url" 2>/dev/null || echo '000')"
        echo "  URL publica: $url  (HTTP $codigo)"
    fi
}

case "${1:-estado}" in
    autorizar) autorizar ;;
    crear)     crear ;;
    iniciar)   iniciar ;;
    detener)   detener ;;
    estado)    estado ;;
    *)
        echo "  Uso: bash scripts/tunel_nombrado.sh [autorizar|crear|iniciar|detener|estado]"
        exit 1
        ;;
esac
