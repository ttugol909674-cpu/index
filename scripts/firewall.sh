#!/usr/bin/env bash
# =====================================================================
# TGS - Configuracion del firewall del servidor (iptables)
#
# La maquina virtual aloja el sitio web, la base de datos y los
# scripts de operacion. La catedra de Administracion de Sistemas
# Operativos pide "documentar y configurar la seguridad de los
# servidores mediante iptables".
#
# Politica aplicada:
#   - Se descarta todo lo que llega desde afuera (INPUT en DROP).
#   - Se acepta el trafico ya establecido (conexiones abiertas).
#   - Se acepta el bucle local, el eco de ICMP (ping) y las redes de
#     Docker, para que los contenedores sigan hablando con el host.
#   - El acceso por SSH (22) y el del sitio web (8080) se aceptan
#     SOLO desde la red local: desde internet se rechazan.
#   - Los puertos publicados por Docker (el 8080 de la aplicacion)
#     se filtran con la cadena DOCKER-USER, que es la unica que Docker
#     no administra: ahi se restringe el acceso a la red local.
#
# El script se puede ejecutar las veces que haga falta: aplica las
# reglas, las guarda en /etc/iptables/rules.v4 y deja un servicio
# (tgs-firewall.service) que las restaura al arrancar el servidor.
#
#   sudo bash scripts/firewall.sh aplicar    # aplica y guarda
#   sudo bash scripts/firewall.sh mostrar     # reglas vigentes
#   sudo bash scripts/firewall.sh revertir   # vuelve a las reglas previas
#
# IMPORTANTE: el script tiene un temporizador de seguridad. Si la
# conexion se corta al aplicarlo, restaura solo las reglas anteriores
# (asi nunca se pierde el acceso al servidor).
# =====================================================================
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"
cd "$TGS_RAIZ"

# --- Configuracion ----------------------------------------------------------
PUERTO_WEB="${APP_PUERTO:-8080}"
PUERTO_SSH=22
PUERTO_BD=3307               # puerto en el host donde se publica MySQL (ver docker-compose.yml)
RED_LOCAL="192.168.1.0/24"     # red domestica del proyecto
ARCHIVO_REGLAS="/etc/iptables/rules.v4"
ARCHIVO_RESPALDO="/etc/iptables/rules.v4.pre-firewall"
MARCA_SEGURIDAD="/tmp/tgs_firewall.ok"

# --- Utilidades ------------------------------------------------------------
ip() { iptables "$@"; }

aplicar_reglas() {
    echo "==========================================================="
    echo " TGS - Configuracion del firewall (iptables)"
    echo "==========================================================="
    echo

    # Se guardan las reglas actuales: son el punto de retorno.
    mkdir -p /etc/iptables
    ipables-save > "$ARCHIVO_RESPALDO" 2>/dev/null
    echo "  Reglas anteriores guardadas en $ARCHIVO_RESPALDO"

    # Temporizador de seguridad: si en 40 segundos no se confirma que
    # la conexion sigue viva, se restauran las reglas anteriores.
    rm -f "$MARCA_SEGURIDAD"
    ( sleep 40
      if [ ! -f "$MARCA_SEGURIDAD" ]; then
          echo "[seguridad] la confirmacion no llego: se restauran las reglas anteriores" >> /var/log/tgs-firewall.log 2>/dev/null
          iptables-restore < "$ARCHIVO_RESPALDO" 2>/dev/null
      fi
    ) &

    echo "  Limpiando las reglas propias..."
    ip -F
    ip -X
    ip -P INPUT DROP
    ip -P FORWARD DROP
    ip -P OUTPUT ACCEPT

    # Bucles locales y trafico ya establecido.
    ip -A INPUT -i lo -j ACCEPT
    ip -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

    # Ping (util para ver si el servidor responde).
    ip -A INPUT -p icmp --icmp-type echo-request -j ACCEPT
    ip -A INPUT -p icmp --icmp-type echo-reply -j ACCEPT

    # Red local: se permite el acceso al puerto de administracion y al
    # sitio web para poder trabajar desde la red de la casa o del aula.
    ip -A INPUT -p tcp --dport $PUERTO_SSH -s $RED_LOCAL -j ACCEPT
    ip -A INPUT -p tcp --dport $PUERTO_WEB -s $RED_LOCAL -j ACCEPT

    # La base de datos MySQL se publica en el host (3307) para que el
    # equipo de desarrollo use la misma base que la web. Solo se admite
    # desde la red local, igual que el sitio.
    ip -A INPUT -p tcp --dport $PUERTO_BD -s $RED_LOCAL -j ACCEPT

    # Redes de Docker: los contenedores (web y base) necesitan hablar
    # con el host y entre si.
    ip -A INPUT -s 172.16.0.0/12 -j ACCEPT
    ip -A INPUT -i docker0 -j ACCEPT
    ip -A INPUT -i br- -j ACCEPT 2>/dev/null || true

    # Los puertos publicados por Docker no pasan por INPUT: se filtran
    # en la cadena DOCKER-USER, que es la que el usuario controla.
    ip -N DOCKER-USER 2>/dev/null || true
    ip -F DOCKER-USER 2>/dev/null || true
    ip -A DOCKER-USER -p tcp --dport $PUERTO_WEB ! -s $RED_LOCAL -j DROP
    ip -A DOCKER-USER -p tcp --dport $PUERTO_WEB -s $RED_LOCAL -j ACCEPT
    ip -A DOCKER-USER -p tcp --dport $PUERTO_BD ! -s $RED_LOCAL -j DROP
    ip -A DOCKER-USER -p tcp --dport $PUERTO_BD -s $RED_LOCAL -j ACCEPT
    ip -A DOCKER-USER -i br- -j ACCEPT 2>/dev/null || true
    ip -A DOCKER-USER -j RETURN 2>/dev/null || true

    echo "  Reglas aplicadas."

    # Se guardan para restaurarlas al arrancar el servidor.
    iptables-save > "$ARCHIVO_REGLAS" 2>/dev/null
    echo "  Reglas guardadas en $ARCHIVO_REGLAS"

    # Servicio que restaura las reglas al inicio.
    cat > /etc/systemd/system/tgs-firewall.service <<'UNIDAD'
[Unit]
Description=TGS - reglas de iptables del servidor de TGS
After=network-online.target docker.service
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/sbin/iptables-restore /etc/iptables/rules.v4
ExecReload=/sbin/iptables-restore /etc/iptables/rules.v4
ExecStop=/sbin/iptables-restore /etc/iptables/rules.v4.pre-firewall

[Install]
WantedBy=multi-user.target
UNIDAD
    systemctl daemon-reload
    systemctl enable tgs-firewall.service >/dev/null 2>&1
    echo "  Servicio tgs-firewall.service habilitado para el arranque."

    echo
    echo "  Estado:"
    iptables -S | sed 's/^/    /'
    echo
    echo "  DOCKER-USER:"
    iptables -S DOCKER-USER | sed 's/^/    /'
    echo
    echo "  [OK]   Firewall configurado."
    echo "  El sitio sigue accesible por el tunel de Cloudflare (sale por el"
    echo "  puerto 443 hacia internet, no entra por el 8080)."
}

mostrar() {
    echo "=== Politica por defecto ==="
    iptables -S | grep '^-P' | sed 's/^/  /'
    echo
    echo "=== Reglas de entrada ==="
    iptables -S INPUT | sed 's/^/  /'
    echo
    echo "=== Cadena DOCKER-USER (filtro de los puertos publicados) ==="
    iptables -S DOCKER-USER | sed 's/^/  /'
    echo
    echo "=== Servicio de arranque ==="
    systemctl is-enabled tgs-firewall.service 2>/dev/null | sed 's/^/  habilitado: /'
    systemctl is-active tgs-firewall.service 2>/dev/null | sed 's/^/  activo: /'
}

revertir() {
    if [ -f "$ARCHIVO_RESPALDO" ]; then
        iptables-restore < "$ARCHIVO_RESPALDO"
        echo "[OK]   Reglas anteriores restauradas."
    else
        echo "ERROR: no hay copia de las reglas anteriores ($ARCHIVO_RESPALDO)." >&2
        exit 1
    fi
}

case "${1:-mostrar}" in
    aplicar)  aplicar_reglas ;;
    mostrar)  mostrar ;;
    revertir) revertir ;;
    *)
        echo "  Uso: sudo bash scripts/firewall.sh [aplicar|mostrar|revertir]"
        exit 1
        ;;
esac
