#!/usr/bin/env bash
# =====================================================================
# TGS - Consola de MySQL de la base del proyecto
#
# Uso:  bash ~/Desktop/tgs/scripts/base.sh
#
# Abre el cliente mysql del contenedor tgs_db con la base bluetech_base
# ya seleccionada y la clave leida del .env.local, asi que no hay que
# escribirla.
# =====================================================================
set -uo pipefail

RAIZ="$HOME/Desktop/tgs"
cd "$RAIZ" || exit 1

CLAVE="$(grep -E '^DB_ROOT_PASS=' .env.local 2>/dev/null | cut -d= -f2)"
if [ -z "$CLAVE" ]; then
    echo "ERROR: no se encontro DB_ROOT_PASS en $RAIZ/.env.local" >&2
    exit 1
fi

echo "Base de datos del proyecto TGS (contenedor tgs_db)."
echo "Para salir: \\q    Para la ayuda: \\h"
echo

docker exec -it tgs_db mysql -uroot -p"$CLAVE" bluetech_base
