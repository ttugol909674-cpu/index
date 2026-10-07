#!/usr/bin/env bash
# =====================================================================
# TGS - Restauracion de la base de datos desde un respaldo
#
# Uso:
#   bash scripts/restaurar.sh                  (usa el respaldo mas nuevo)
#   bash scripts/restaurar.sh tgs_20260930_120000.sql
#
# Vacia las tablas del contenedor y vuelve a cargar el .sql elegido.
# =====================================================================
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"

echo "==========================================================="
echo " TGS - Restauracion de la base de datos"
echo "==========================================================="

tgs_cargar_entorno

ARCHIVO="${1:-}"

# Sin argumento: se toma el respaldo mas reciente
if [ -z "$ARCHIVO" ]; then
    ARCHIVO="$(find "$TGS_RAIZ/backups" -maxdepth 1 -name 'tgs_*.sql' -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn | head -1 | cut -d' ' -f2- || true)"

    if [ -z "$ARCHIVO" ]; then
        echo "ERROR: no hay ningun respaldo en backups/ para restaurar." >&2
        echo "       Genera uno con: bash scripts/respaldar.sh" >&2
        exit 1
    fi
fi

# Permite pasar solo el nombre o la ruta completa
if [ ! -f "$ARCHIVO" ]; then
    ARCHIVO="$TGS_RAIZ/backups/$ARCHIVO"
fi

if [ ! -f "$ARCHIVO" ]; then
    echo "ERROR: no existe el respaldo '$ARCHIVO'." >&2
    echo "       Disponibles:" >&2
    ls -1 "$TGS_RAIZ/backups" 2>/dev/null | sed 's/^/         /' >&2
    exit 1
fi

if ! docker compose ps --status running --services 2>/dev/null | grep -q '^db$'; then
    echo "ERROR: el contenedor de la base de datos no esta corriendo." >&2
    echo "       Levantalo con: bash scripts/instalar.sh" >&2
    exit 1
fi

echo
echo "  Respaldo a restaurar : $(basename "$ARCHIVO")"
echo "  Tamano               : $(du -h "$ARCHIVO" | cut -f1)"
echo "  Base de datos        : $DB_NAME"
echo
echo "  ATENCION: esto reemplaza los datos actuales del contenedor."
echo

# Confirmacion explicita, para no perder trabajo por un doble clic equivocado
read -r -p "  Confirmar la restauracion? (si/NO): " RESPUESTA
if [ "$RESPUESTA" != "si" ]; then
    echo "  Restauracion cancelada."
    exit 0
fi

echo
echo "[1/3] Preparando la base de datos"

# El respaldo trae sus propios DROP TABLE, pero se limpia igual para
# garantizar que no queden tablas sueltas de una version anterior.
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -e \
    "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[2/3] Cargando el respaldo"
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < "$ARCHIVO"

echo "[3/3] Verificando"

TABLAS="$(docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null | tr -d '[:space:]')"

USUARIOS="$(docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B -e \
    "SELECT COUNT(*) FROM \`$DB_NAME\`.\`usuario\`;" 2>/dev/null | tr -d '[:space:]')"

echo "[OK]   Restauracion completa"
echo "       Tablas en la base : ${TABLAS:-0}"
echo "       Usuarios cargados : ${USUARIOS:-0}"
echo
echo "  Sitio web: http://localhost:${APP_PUERTO}"
echo
