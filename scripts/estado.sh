#!/usr/bin/env bash
# =====================================================================
# TGS - Estado del sistema
#
# Muestra si los contenedores estan arriba, si la base responde y si
# el sitio web contestan. Es el comando para comprobar, antes y despues
# de cada paso del video, que el servidor esta en pie.
# =====================================================================
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"

tgs_cargar_entorno

echo "==========================================================="
echo " TGS - Estado del sistema"
echo "==========================================================="
echo

echo "  Contenedores"
echo "  -------------"
docker compose ps
echo

# Estado de la base
if docker compose ps --status running --services 2>/dev/null | grep -q '^db$'; then
    if docker compose exec -T db mysqladmin ping -h 127.0.0.1 -uroot -p"$DB_ROOT_PASS" --silent >/dev/null 2>&1; then
        echo "  Base de datos: ACTIVA  ($DB_NAME en el contenedor tgs_db)"

        USUARIOS="$(docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B -e \
            "SELECT COUNT(*) FROM \`$DB_NAME\`.\`usuario\`;" 2>/dev/null | tr -d '[:space:]' || echo '?')"
        RESERVAS="$(docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B -e \
            "SELECT COUNT(*) FROM \`$DB_NAME\`.\`reserva\`;" 2>/dev/null | tr -d '[:space:]' || echo '?')"
        echo "    usuarios registrados : ${USUARIOS:-?}"
        echo "    reservas registradas : ${RESERVAS:-?}"
    else
        echo "  Base de datos: CONTAINER ARRIBA pero MySQL no responde"
    fi
else
    echo "  Base de datos: CAIDA (el contenedor tgs_db no esta corriendo)"
fi
echo

# Estado del sitio web
if docker compose ps --status running --services 2>/dev/null | grep -q '^app$'; then
    CODIGO="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${APP_PUERTO}/" 2>/dev/null || echo '000')"

    if [ "$CODIGO" = "200" ]; then
        echo "  Sitio web: RESPONDIENDO  http://localhost:${APP_PUERTO}  (HTTP $CODIGO)"
    else
        echo "  Sitio web: CONTENEDOR ARRIBA pero la pagina devuelve HTTP $CODIGO"
    fi
else
    echo "  Sitio web: CAIDO (el contenedor tgs_app no esta corriendo)"
fi
echo

# Ultimo respaldo disponible
if [ -d "$TGS_RAIZ/backups" ]; then
    ULTIMO="$(find "$TGS_RAIZ/backups" -maxdepth 1 -name 'tgs_*.sql' -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn | head -1 | cut -d' ' -f2- || true)"

    if [ -n "$ULTIMO" ]; then
        echo "  Ultimo respaldo: $(basename "$ULTIMO")  ($(du -h "$ULTIMO" | cut -f1))"
    else
        echo "  Ultimo respaldo: ninguno todavia (bash scripts/respaldar.sh)"
    fi
else
    echo "  Ultimo respaldo: la carpeta backups/ todavia no existe"
fi
echo
