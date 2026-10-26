#!/usr/bin/env bash
# =====================================================================
# TGS - Publicacion de la URL vigente del tunel
#
# El sitio publico es un Worker de Cloudflare (workers.dev) que hace
# de proxy hacia el tunel. La URL del tunel cambia cada vez que este se
# reinicia, asi que la maquina virtual le avisa por HTTP cual es la
# actual y el Worker la guarda en su almacenamiento KV.
#
# Asi la direccion publica (https://tgs.bluetech.workers.dev) no cambia
# nunca, aunque el tunel se reinicie.
#
# El secreto de escritura esta en ~/.tgs/token-worker (permisos 600).
# =====================================================================
set -uo pipefail

RAIZ="$HOME/Desktop/tgs"
URL_FILE="$RAIZ/.tunel.url"
WORKER="https://tgs.bluetech.workers.dev/actualizar-url"
SECRETO_FILE="$HOME/.tgs/token-worker"
LOG="$RAIZ/backups/publicar-url.log"

[ -f "$URL_FILE" ] || exit 0
[ -f "$SECRETO_FILE" ] || exit 0

URL="$(tr -d ' \t\r\n' < "$URL_FILE")"
[ -z "$URL" ] && exit 0

RESPUESTA="$(curl -s --max-time 25 -X POST "$WORKER" \
    -H "x-tgs-token: $(cat "$SECRETO_FILE")" \
    -H "Content-Type: text/plain" \
    --data "$URL" 2>&1)"

case "$RESPUESTA" in
    ok:*) echo "[$(date '+%Y-%m-%d %H:%M:%S')] publicado: $URL" >> "$LOG" ;;
    *)    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR al publicar: $RESPUESTA" >> "$LOG" ;;
esac
