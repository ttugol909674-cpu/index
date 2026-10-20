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
echo
