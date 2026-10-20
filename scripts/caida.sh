#!/usr/bin/env bash
# =====================================================================
# TGS - Simulacion de la caida del servidor
#
# Uso:
#   bash scripts/caida.sh          borra contenedores Y volumen (perdida total)
#   bash scripts/caida.sh --suave  solo detiene, conserva los datos
#
# La opcion por defecto usa "down -v", que es la caida que hay que
# mostrar en el video: se lleva por delante el volumen de MySQL y con
# el los datos. Por eso hay que respaldar antes de ejecutarla.
# =====================================================================
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"

MODO="${1:-}"

echo "==========================================================="
echo " TGS - Simulacion de caida del servidor"
echo "==========================================================="

tgs_cargar_entorno

if [ "$MODO" = "--suave" ]; then
    echo
    echo "  Modo: detencion suave (los datos se conservan)"
    echo
    docker compose stop
    echo
    echo "[OK]   Contenedores detenidos. Los datos siguen en el volumen."
    echo "       Para volver:  bash scripts/instalar.sh"
    echo
    exit 0
fi

# Aviso claro: esta operacion destruye los datos del contenedor
RESPUESTA=""
if [ -t 0 ]; then
    read -r -p "  Esto BORRA el volumen y todos los datos. Continuar? (si/NO): " RESPUESTA
else
    RESPUESTA="si"
fi

if [ "$RESPUESTA" != "si" ]; then
    echo "  Cancelado."
    exit 0
fi

echo
echo "  Deteniendo y eliminando contenedores y volumen..."
echo

# -v es lo que elimina el volumen db_data: aqui se pierden los datos
docker compose down -v

echo
echo "==========================================================="
echo " CAIDA SIMULADA"
echo "==========================================================="
echo
echo "  Estado actual:"
docker compose ps 2>/dev/null || true
echo
echo "  El sitio http://localhost:${APP_PUERTO} ya no responde."
echo
echo "  Para recuperar el sistema:"
echo "    1) bash scripts/instalar.sh    (crea variables y levanta de nuevo)"
echo "    2) bash scripts/restaurar.sh   (recupera la base desde el respaldo)"
echo
