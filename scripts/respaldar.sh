#!/usr/bin/env bash
# =====================================================================
# TGS - Respaldo de la base de datos
#
# Saca un mysqldump del CONTENEDOR de MySQL y lo guarda en
# backups/tgs_<fecha>.sql. Gira los respaldos viejos para no acumular.
# =====================================================================
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"

echo "==========================================================="
echo " TGS - Respaldo de la base de datos"
echo "==========================================================="

tgs_cargar_entorno

# Sin este chequeo el mysqldump fallaria con un error poco claro
if ! docker compose ps --status running --services 2>/dev/null | grep -q '^db$'; then
    echo "ERROR: el contenedor de la base de datos no esta corriendo." >&2
    echo "       Levantalo con: bash scripts/instalar.sh" >&2
    exit 1
fi

mkdir -p "$TGS_RAIZ/backups"

# mysqldump dentro del contenedor, volcado a un archivo en el host
NOMBRE="tgs_$(date +%Y%m%d_%H%M%S).sql"
DESTINO="$TGS_RAIZ/backups/$NOMBRE"

echo
echo "  Base de datos : $DB_NAME"
echo "  Generando     : backups/$NOMBRE"
echo

docker compose exec -T db mysqldump \
    -u root \
    -p"$DB_ROOT_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    --default-character-set=utf8mb4 \
    "$DB_NAME" > "$DESTINO"

# Un respaldo vacio no sirve para nada: se descarta
if [ ! -s "$DESTINO" ]; then
    rm -f "$DESTINO"
    echo "ERROR: el respaldo salio vacio, se elimino." >&2
    exit 1
fi

chmod 600 "$DESTINO"

TAMANO="$(du -h "$DESTINO" | cut -f1)"
TABLAS="$(grep -c '^CREATE TABLE' "$DESTINO" || true)"

echo "[OK]   Respaldo generado: backups/$NOMBRE"
echo "       Tamano: $TAMANO   Tablas: $TABLAS"
echo

# ---------------------------------------------------------------------
# Respaldo remoto (opcional).
#
# La catedra de Administracion de Sistemas Operativos pide "medios de
# respaldos locales, y remotos en otro servidor Linux". Se configura
# con dos variables de entorno:
#
#   RESPALDO_REMOTO   destino con formato usuario@servidor:/ruta
#                     (por ejemplo  root@192.168.1.30:/var/backups/tgs)
#   RESPALDO_REMOTO_LLAVES
#                     ruta de la clave privada de SSH (si se usa una)
#
# Si RESPALDO_REMOTO queda vacio, el respaldo es solo local.
# ---------------------------------------------------------------------
REMOTO="${RESPALDO_REMOTO:-}"
if [ -n "$REMOTO" ]; then
    echo "  Enviando el respaldo a $REMOTO ..."

    if ! command -v rsync >/dev/null 2>&1; then
        echo "  (rsync no esta instalado: se usa scp)"
        if [ -n "${RESPALDO_REMOTO_LLAVES:-}" ]; then
            scp -i "$RESPALDO_REMOTO_LLAVES" "$DESTINO" "$REMOTO/" >/dev/null 2>&1
        else
            scp "$DESTINO" "$REMOTO/" >/dev/null 2>&1
        fi
    else
        if [ -n "${RESPALDO_REMOTO_LLAVES:-}" ]; then
            rsync -az -e "ssh -i $RESPALDO_REMOTO_LLAVES" "$DESTINO" "$REMOTO/" >/dev/null 2>&1
        else
            rsync -az "$DESTINO" "$REMOTO/" >/dev/null 2>&1
        fi
    fi

    if [ $? -eq 0 ]; then
        echo "  [OK]   Copia remota realizada."
    else
        echo "  [AVISO] No se pudo copiar al servidor remoto."
        echo "           Revisa el acceso por SSH a $REMOTO."
    fi
    echo
fi

# Rotacion: conserva solo los RESPALDO_CONSERVAR respaldos mas recientes
CONSERVAR="${RESPALDO_CONSERVAR:-5}"
TOTAL="$(find "$TGS_RAIZ/backups" -maxdepth 1 -name 'tgs_*.sql' | wc -l)"

if [ "$TOTAL" -gt "$CONSERVAR" ]; then
    echo "       Rotando respaldos (conservando los $CONSERVAR mas recientes):"
    find "$TGS_RAIZ/backups" -maxdepth 1 -name 'tgs_*.sql' -printf '%T@ %p\n' \
        | sort -rn \
        | tail -n "+$((CONSERVAR + 1))" \
        | cut -d' ' -f2- \
        | while read -r viejo; do
            rm -f "$viejo"
            echo "         eliminado $(basename "$viejo")"
        done
fi

echo
echo "  Para restaurar este respaldo:"
echo "    bash scripts/restaurar.sh $NOMBRE"
if [ -n "$REMOTO" ]; then
    echo "  (tambien quedo en el servidor remoto: $REMOTO)"
fi
echo
