#!/usr/bin/env bash
# =====================================================================
# TGS - Herramientas de administracion (perfil de Docker Compose)
#
# Uso:
#   bash ~/Desktop/tgs/scripts/herramientas.sh up      # levanta phpMyAdmin
#   bash ~/Desktop/tgs/scripts/herramientas.sh down    # lo detiene
#   bash ~/Desktop/tgs/scripts/herramientas.sh status  # muestra el estado
#
# IMPORTANTE: docker compose solo lee el archivo .env, no el .env.local
# (que es donde esta la clave real de la base). Por eso este script
# carga el entorno del proyecto con tgs_cargar_entorno ANTES de llamar
# a compose; si no, el contenedor recibe la clave de ejemplo y falla
# con "Access denied".
# =====================================================================
set -uo pipefail

RAIZ="$HOME/Desktop/tgs"
cd "$RAIZ" || exit 1

# El entorno del proyecto: .env y despues .env.local, exportados.
source "$RAIZ/scripts/_entorno.sh"
tgs_cargar_entorno

ACCION="${1:-status}"

case "$ACCION" in
    up)
        echo "Levantando phpMyAdmin..."
        docker compose --profile herramientas up -d phpmyadmin
        sleep 4
        echo
        echo "  Desde la red local: http://$(hostname -I | awk '{print $1}'):8081/"
        echo "  Usuario: tgs    (la clave ya va configurada)"
        ;;
    down)
        echo "Deteniendo phpMyAdmin..."
        docker compose --profile herramientas stop phpmyadmin
        ;;
    status)
        echo "=== Contenedores del proyecto ==="
        docker ps --format "  {{.Names}}  {{.Status}}  {{.Ports}}" | grep tgs || echo "  (ninguno en marcha)"
        echo
        echo "=== phpMyAdmin responde? ==="
        codigo=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "http://127.0.0.1:8081/")
        echo "  http://127.0.0.1:8081/ -> HTTP $codigo"
        ;;
    *)
        echo "Uso: bash scripts/herramientas.sh [up|down|status]"
        exit 1
        ;;
esac
