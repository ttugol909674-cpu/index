#!/usr/bin/env bash
# =====================================================================
# TGS - Script operador de centro de computos
#
# Reune en un solo comando las tareas habituales de administracion de un
# servidor, que son las que pide la catedra de Administracion de Sistemas
# Operativos:
#
#     servicios   - estado y control de los servicios (Docker, SSH, cron)
#     red         - configuracion de red, puertos y rutas
#     procesos    - carga del sistema y procesos mas pesados
#     respaldos   - generar y listar respaldos de la base
#     restauracion- restaurar un respaldo existente
#     usuarios    - usuarios del sistema y de la base de datos
#     grupos      - grupos del sistema y pertenencia de los usuarios
#     origenes    - creacion de origenes de datos (bases y usuarios de MySQL)
#     registros   - acceso a los registros (logs y auditoria)
#     estado      - resumen general del servidor
#
# Se puede usar de dos maneras:
#
#     bash scripts/operador.sh              # menu interactivo
#     bash scripts/operador.sh estado       # tarea directa, para automatizar
#     bash scripts/operador.sh ayuda        # lista de tareas
#
# Las tareas que necesitan privilegios de root (crear usuarios o grupos del
# sistema) avisan si no se pueden ejecutar con el usuario actual, en lugar de
# fallar: el resto del operator sigue siendo utilizable.
# =====================================================================
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"

tgs_cargar_entorno || exit 1

# ----------------------------------------------------------------------
# Utilidades
# ----------------------------------------------------------------------

# Encabezado de cada tarea.
titulo() {
    echo
    echo "==========================================================="
    echo " $1"
    echo "==========================================================="
    echo
}

# Mysql como root dentro del contenedor de la base de datos.
tgs_mysql() {
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B "$DB_NAME" -e "$1" 2>/dev/null
}

# Mysql como root sin base de datos seleccionada (para crear bases y usuarios).
tgs_mysql_global() {
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B -e "$1" 2>/dev/null
}

# Indica si se pueden ejecutar tareas que requieren root.
tgs_tiene_sudo() {
    if [ "$(id -u)" -eq 0 ]; then
        return 0
    fi
    sudo -n true >/dev/null 2>&1 && return 0
    return 1
}

tgs_aviso_sudo() {
    echo "  Esta tarea necesita privilegios de administrador (sudo)."
    echo "  Ejecutala con: sudo bash scripts/operador.sh $1"
}

# ----------------------------------------------------------------------
# 1. Servicios
# ----------------------------------------------------------------------
op_servicios() {
    titulo "Servicios"

    echo "  Contenedores del proyecto"
    echo "  -------------------------"
    docker compose ps
    echo

    echo "  Servicios del sistema"
    echo "  ---------------------"
    for servicio in docker ssh cron; do
        estado="$(systemctl is-active "$servicio" 2>/dev/null || echo 'desconocido')"
        habilitado="$(systemctl is-enabled "$servicio" 2>/dev/null || echo 'desconocido')"
        printf "    %-10s %-12s (inicio: %s)\n" "$servicio" "$estado" "$habilitado"
    done
    echo

    echo "  Tareas programadas (cron)"
    echo "  --------------------------"
    if crontab -l 2>/dev/null | grep -v '^#' | grep -q .; then
        crontab -l 2>/dev/null | grep -v '^#' | grep -v '^$' | sed 's/^/    /'
    else
        echo "    (no hay tareas programadas para el usuario $USER)"
    fi
    echo

    echo "  Acciones:  bash scripts/operador.sh servicios [arrancar|parar|reiniciar]"
}

# Control de los servicios (arrancar, parar, reiniciar).
op_servicios_accion() {
    local accion="${1:-estado}"
    local servicio="${2:-app}"

    case "$accion" in
        estado)
            op_servicios
            ;;
        arrancar|parar|reiniciar)
            if ! docker compose ps --status running --services 2>/dev/null | grep -q '^app$'; then
                titulo "Servicio $servicio: $accion"
                if [ "$servicio" = "app" ]; then
                    docker compose up -d app
                else
                    docker compose up -d db
                fi
                echo "  Contenedores actualizados."
            else
                titulo "Servicio $servicio: $accion"
                if [ "$servicio" = "app" ]; then
                    docker compose restart app
                else
                    docker compose restart db
                fi
                echo "  Contenedor reiniciado."
            fi
            ;;
        *)
            echo "  Accion desconocida: $accion (use arrancar, parar, reiniciar o estado)"
            ;;
    esac
}

# ----------------------------------------------------------------------
# 2. Red
# ----------------------------------------------------------------------
op_red() {
    titulo "Red"

    echo "  Interfaces y direcciones"
    echo "  ------------------------"
    ip -4 addr show | grep -E '^[0-9]+:|inet ' | sed 's/^/  /'
    echo

    echo "  Puertos en escucha"
    echo "  ------------------"
    ss -tulpn 2>/dev/null | head -15 | sed 's/^/  /' || echo "  (no se pudo leer la tabla de puertos)"
    echo

    echo "  Tabla de rutas"
    echo "  --------------"
    ip route | sed 's/^/  /'
    echo

    echo "  Servidores de nombres"
    echo "  --------------------"
    cat /etc/resolv.conf | grep -v '^#' | grep . | sed 's/^/  /'
    echo

    echo "  Redes de Docker del proyecto"
    echo "  ----------------------------"
    docker network ls | sed 's/^/  /'
    echo

    echo "  Publicacion de puertos del proyecto"
    echo "  -----------------------------------"
    docker compose ps --format '  {{.Name}} | {{.Ports}}' 2>/dev/null
    echo
    echo "  ACCESO EXTERNO: http://<ip-del-servidor>:${APP_PUERTO}"
    ip -4 addr show | grep 'inet ' | grep -v '127.0.0.1' | head -1 | sed 's/.*inet \([0-9.]*\).*/  IP del servidor: \1/'
}

# ----------------------------------------------------------------------
# 3. Procesos
# ----------------------------------------------------------------------
op_procesos() {
    titulo "Procesos"

    echo "  Carga del sistema"
    echo "  -----------------"
    uptime
    echo

    echo "  Memoria"
    echo "  -------"
    free -h
    echo

    echo "  Disco"
    echo "  -----"
    df -h / /home 2>/dev/null | sed 's/^/  /'
    echo

    echo "  Procesos con mayor uso de CPU"
    echo "  -----------------------------"
    ps -eo pid,user,pcpu,pmem,comm --sort=-pcpu | head -10 | sed 's/^/  /'
    echo

    echo "  Procesos con mayor uso de memoria"
    echo "  ---------------------------------"
    ps -eo pid,user,pcpu,pmem,comm --sort=-pmem | head -10 | sed 's/^/  /'
    echo

    echo "  Procesos de los contenedores"
    echo "  ----------------------------"
    docker compose ps --format '  {{.Name}} | {{.Status}}' 2>/dev/null
}

# ----------------------------------------------------------------------
# 4. Respaldo de datos
# ----------------------------------------------------------------------
op_respaldos() {
    titulo "Respaldos de la base de datos"

    if [ ! -d "$TGS_RAIZ/backups" ]; then
        echo "  Todavia no hay carpeta de respaldos."
        echo
        echo "  Para generar el primero:  bash scripts/respaldar.sh"
        return 0
    fi

    echo "  Respaldos disponibles (se conservan los $RESPALDO_CONSERVAR mas recientes)"
    echo "  ------------------------------------------------------------------"
    if ls "$TGS_RAIZ"/backups/tgs_*.sql >/dev/null 2>&1; then
        ls -lhtr --time-style='+%Y-%m-%d %H:%M' "$TGS_RAIZ"/backups/tgs_*.sql \
            | awk '{printf "    %s  %8s  %s %s  %s\n", $6, $7, $5, $3, $NF}'
    else
        echo "    (no hay respaldos generados todavia)"
    fi
    echo

    echo "  Para generar uno nuevo:  bash scripts/respaldar.sh"
    echo "  Para restaurar alguno:   bash scripts/operador.sh restauracion <archivo>"
}

# ----------------------------------------------------------------------
# 5. Restauracion
# ----------------------------------------------------------------------
op_restauracion() {
    titulo "Restauracion de un respaldo"

    local archivo="${1:-}"

    if [ -z "$archivo" ]; then
        echo "  Respaldos disponibles:"
        ls -1 "$TGS_RAIZ"/backups/tgs_*.sql 2>/dev/null | sed "s|$TGS_RAIZ/backups/|    |"
        echo
        echo "  Uso: bash scripts/operador.sh restauracion <nombre_del_archivo>"
        echo "  Ejemplo: bash scripts/operador.sh restauracion tgs_20261020_030000.sql"
        return 0
    fi

    if [ ! -f "$TGS_RAIZ/backups/$archivo" ]; then
        echo "  ERROR: no existe el respaldo '$archivo' en $TGS_RAIZ/backups" >&2
        return 1
    fi

    echo "  Se va a restaurar: $archivo"
    echo "  ATENCION: esto borra los datos actuales de la base y deja el sistema"
    echo "  con los datos de ese respaldo."
    echo
    read -r -p "  Confirme con SI: " confirmacion
    if [ "$confirmacion" != "SI" ]; then
        echo "  Cancelado. No se modifico nada."
        return 0
    fi

    bash "$TGS_RAIZ/scripts/restaurar.sh" "$archivo"
}

# ----------------------------------------------------------------------
# 6. Usuarios
# ----------------------------------------------------------------------
op_usuarios() {
    titulo "Usuarios"

    echo "  Usuarios del sistema"
    echo "  --------------------"
    awk -F: '$3 >= 1000 && $3 < 65534 {printf "    %-20s uid=%-6s shell=%s\n", $1, $3, $7}' /etc/passwd
    echo "  (los usuarios con shell /bin/false son cuentas de servicio)"
    echo

    echo "  Usuarios de la base de datos"
    echo "  ----------------------------"
    tgs_mysql_global "SELECT user, host FROM mysql.user ORDER BY user;" | sed 's/^/    /'
    echo

    echo "  Permisos del usuario de aplicacion"
    echo "  -----------------------------------"
    tgs_mysql_global "SHOW GRANTS FOR '$DB_USER'@'%';" | sed 's/^/    /'
    echo

    echo "  Acciones:  bash scripts/operador.sh usuarios [crear <nombre> <grupo>]"
}

# Crear un usuario del sistema (requiere sudo).
op_usuarios_crear() {
    local nombre="${1:-}"
    local grupo="${2:-taller}"

    if [ -z "$nombre" ]; then
        echo "  Uso: bash scripts/operador.sh usuarios crear <nombre> [grupo]"
        return 1
    fi

    if ! tgs_tiene_sudo; then
        tgs_aviso_sudo "usuarios crear $nombre"
        return 1
    fi

    if id "$nombre" >/dev/null 2>&1; then
        echo "  El usuario '$nombre' ya existe."
        return 0
    fi

    if ! getent group "$grupo" >/dev/null 2>&1; then
        echo "  ERROR: el grupo '$grupo' no existe." >&2
        return 1
    fi

    sudo useradd -m -g "$grupo" -s /bin/bash "$nombre"
    sudo passwd -d "$nombre" >/dev/null 2>&1
    echo "  Usuario '$nombre' creado en el grupo '$grupo'."
    echo "  No se fijo contrasena: asignala con: sudo passwd $nombre"
}

# ----------------------------------------------------------------------
# 7. Grupos
# ----------------------------------------------------------------------
op_grupos() {
    titulo "Grupos del sistema"

    echo "  Grupos"
    echo "  ------"
    getent group | awk -F: '$3 >= 1000 {printf "    %-20s gid=%-6s usuarios=%s\n", $1, $3, ($4 == "" ? "-" : $4)}'
    echo

    echo "  Acciones:  bash scripts/operador.sh grupos [agregar <usuario> <grupo> | quitar <usuario> <grupo>]"
}

op_grupos_accion() {
    local accion="${1:-}"
    local usuario="${2:-}"
    local grupo="${3:-}"

    if [ -z "$accion" ] || [ -z "$usuario" ] || [ -z "$grupo" ]; then
        echo "  Uso: bash scripts/operador.sh grupos <agregar|quitar> <usuario> <grupo>"
        return 1
    fi

    if ! tgs_tiene_sudo; then
        tgs_aviso_sudo "grupos $accion $usuario $grupo"
        return 1
    fi

    if ! id "$usuario" >/dev/null 2>&1; then
        echo "  ERROR: el usuario '$usuario' no existe." >&2
        return 1
    fi

    case "$accion" in
        agregar)
            sudo usermod -aG "$grupo" "$usuario"
            echo "  El usuario '$usuario' ahora pertenece al grupo '$grupo'."
            ;;
        quitar)
            sudo gpasswd -d "$usuario" "$grupo"
            echo "  El usuario '$usuario' ya no pertenece al grupo '$grupo'."
            ;;
        *)
            echo "  Accion desconocida: $accion"
            return 1
            ;;
    esac
}

# ----------------------------------------------------------------------
# 8. Origenes de datos
# ----------------------------------------------------------------------
op_origenes() {
    titulo "Origenes de datos"

    echo "  Bases de datos del servidor MySQL"
    echo "  ---------------------------------"
    tgs_mysql_global "SHOW DATABASES;" | sed 's/^/    /'
    echo

    echo "  Tablas de la base $DB_NAME"
    echo "  ---------------------------------"
    tgs_mysql "SHOW TABLES;" | sed 's/^/    /'
    echo

    echo "  Vistas (vistas SQL para reportes)"
    echo "  --------------------------------"
    tgs_mysql_global "SELECT table_name FROM information_schema.views WHERE table_schema = '$DB_NAME';" | sed 's/^/    /'
    echo

    echo "  Acciones:"
    echo "    bash scripts/operador.sh origenes crear <base> <usuario> <clave>"
    echo "    bash scripts/operador.sh origenes copiar <base_origen> <base_destino>"
}

# Crear una base de datos y su usuario (origen de datos nuevo).
op_origenes_crear() {
    local base="${1:-}"
    local usuario="${2:-}"
    local clave="${3:-}"

    if [ -z "$base" ] || [ -z "$usuario" ] || [ -z "$clave" ]; then
        echo "  Uso: bash scripts/operador.sh origenes crear <base> <usuario> <clave>"
        return 1
    fi

    if ! docker compose ps --status running --services 2>/dev/null | grep -q '^db$'; then
        echo "  ERROR: el contenedor de la base de datos no esta corriendo." >&2
        return 1
    fi

    echo "  Creando el origen de datos '$base' con el usuario '$usuario'..."
    tgs_mysql_global "CREATE DATABASE IF NOT EXISTS \`$base\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    tgs_mysql_global "CREATE USER IF NOT EXISTS '$usuario'@'%' IDENTIFIED BY '$clave';"
    tgs_mysql_global "GRANT ALL PRIVILEGES ON \`$base\`.* TO '$usuario'@'%';"
    tgs_mysql_global "FLUSH PRIVILEGES;"

    echo "  [OK]   Base '$base' creada y permisos asignados a '$usuario'."
}

# Copiar una base completa (origen de datos a partir de otra).
op_origenes_copiar() {
    local origen="${1:-}"
    local destino="${2:-}"

    if [ -z "$origen" ] || [ -z "$destino" ]; then
        echo "  Uso: bash scripts/operador.sh origenes copiar <base_origen> <base_destino>"
        return 1
    fi

    echo "  Copiando '$origen' en '$destino'..."
    docker compose exec -T db mysqldump -uroot -p"$DB_ROOT_PASS" \
        --single-transaction --routines --triggers --add-drop-table \
        "$origen" | docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" "$destino" 2>/dev/null

    if [ ${PIPESTATUS[0]} -eq 0 ]; then
        echo "  [OK]   Copia terminada: '$origen' -> '$destino'."
    else
        echo "  ERROR: la copia fallo. Revise que la base origen exista." >&2
        return 1
    fi
}

# ----------------------------------------------------------------------
# 9. Registros (logs y auditoria)
# ----------------------------------------------------------------------
op_registros() {
    titulo "Registros del sistema"

    echo "  Ultimas lineas del log del servidor web (contenedor app)"
    echo "  ------------------------------------------------------------"
    docker compose logs --tail 12 app 2>/dev/null | sed 's/^/    /'
    echo

    echo "  Ultimas lineas del log de la base de datos (contenedor db)"
    echo "  -----------------------------------------------------------"
    docker compose logs --tail 8 db 2>/dev/null | sed 's/^/    /'
    echo

    echo "  Registro de la tarea programada de respaldo"
    echo "  ------------------------------------------"
    if [ -f "$TGS_RAIZ/backups/cron.log" ]; then
        tail -12 "$TGS_RAIZ/backups/cron.log" | sed 's/^/    /'
    else
        echo "    (todavia no se ejecuto la tarea programada)"
    fi
    echo

    echo "  Auditoria de cambios en las reservas (tabla reserva_historial)"
    echo "  -------------------------------------------------------------"
    tgs_mysql "SELECT id_reserva, campo, valor_anterior, valor_nuevo, fecha_cambio, usuario
               FROM reserva_historial ORDER BY id_historial DESC LIMIT 10;" | sed 's/^/    /'
    echo

    echo "  Avisos enviados al cliente (tabla notificacion)"
    echo "  ----------------------------------------------"
    tgs_mysql "SELECT id_notificacion, tipo, estado, email_destino, fecha_envio
               FROM notificacion ORDER BY id_notificacion DESC LIMIT 10;" | sed 's/^/    /'
    echo

    echo "  Accion:  bash scripts/operador.sh registros [web|db|cron|auditoria]"
}

op_registros_accion() {
    local origen="${1:-todos}"

    case "$origen" in
        web)
            docker compose logs --tail 30 app
            ;;
        db)
            docker compose logs --tail 30 db
            ;;
        cron)
            tail -30 "$TGS_RAIZ/backups/cron.log" 2>/dev/null || echo "(todavia no hay log de cron)"
            ;;
        auditoria)
            tgs_mysql "SELECT * FROM reserva_historial ORDER BY id_historial DESC LIMIT 30;"
            ;;
        todos)
            op_registros
            ;;
        *)
            echo "  Origen desconocido: $origen (use web, db, cron, auditoria o todos)"
            ;;
    esac
}

# ----------------------------------------------------------------------
# 10. Estado general
# ----------------------------------------------------------------------
op_estado() {
    titulo "Resumen del servidor"

    echo "  Host:      $(hostname)"
    echo "  Sistema:   $(. /etc/os-release 2>/dev/null && echo "$PRETTY_NAME")"
    echo "  Kernel:    $(uname -r)"
    echo "  Usuario:   $(whoami)"
    echo "  Docker:    $(docker --version 2>/dev/null | awk '{print $3}')"
    echo "  Uptime:    $(uptime -p 2>/dev/null)"
    echo

    echo "  Contenedores"
    docker compose ps --format '    {{.Name}} | {{.Status}} | {{.Ports}}' 2>/dev/null
    echo

    echo "  Sitio web"
    CODIGO="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${APP_PUERTO}/" 2>/dev/null || echo '000')"
    echo "    http://localhost:${APP_PUERTO}  (HTTP $CODIGO)"
    echo

    echo "  Base de datos"
    echo "    $DB_NAME  en el contenedor tgs_db"
    echo "    usuarios: $(tgs_mysql 'SELECT COUNT(*) FROM usuario;' | tr -d '[:space:]')"
    echo "    reservas: $(tgs_mysql 'SELECT COUNT(*) FROM reserva;' | tr -d '[:space:]')"
    echo

    echo "  Ultimo respaldo: $(ls -1t "$TGS_RAIZ"/backups/tgs_*.sql 2>/dev/null | head -1 | xargs -r basename)"
}

# ----------------------------------------------------------------------
# Menu interactivo
# ----------------------------------------------------------------------
menu() {
    while true; do
        titulo "TGS - Operador del centro de computos"
        echo "  1) Servicios (Docker, SSH, cron)"
        echo "  2) Red (interfaces, puertos, rutas)"
        echo "  3) Procesos (carga, memoria, disco)"
        echo "  4) Respaldos (generar y listar)"
        echo "  5) Restauracion (recuperar un respaldo)"
        echo "  6) Usuarios (del sistema y de la base)"
        echo "  7) Grupos (del sistema)"
        echo "  8) Origenes de datos (bases y usuarios MySQL)"
        echo "  9) Registros (logs del web, base, cron y auditoria)"
        echo " 10) Estado general del servidor"
        echo "  0) Salir"
        echo
        read -r -p "  Opcion: " opcion

        case "$opcion" in
            1) op_servicios ;;
            2) op_red ;;
            3) op_procesos ;;
            4) op_respaldos ;;
            5) op_restauracion ;;
            6) op_usuarios ;;
            7) op_grupos ;;
            8) op_origenes ;;
            9) op_registros ;;
            10) op_estado ;;
            0|"") echo "  Hasta luego."; exit 0 ;;
            *) echo "  Opcion no valida." ;;
        esac

        echo
        read -r -p "  Presione Enter para volver al menu..." _continuar
    done
}

ayuda() {
    echo "  TGS - Script operador de centro de computos"
    echo
    echo "  Uso:"
    echo "    bash scripts/operador.sh              abre el menu interactivo"
    echo "    bash scripts/operador.sh <tarea>      ejecuta una tarea directa"
    echo
    echo "  Tareas disponibles:"
    echo "    servicios [arrancar|parar|reiniciar] [app|db]"
    echo "    red                configuracion de red y puertos"
    echo "    procesos           carga, memoria, disco y procesos"
    echo "    respaldos          lista los respaldos de la base"
    echo "    restauracion <archivo>  restaura un respaldo"
    echo "    usuarios [crear <nombre> [grupo]]"
    echo "    grupos [agregar|quitar <usuario> <grupo>]"
    echo "    origenes [crear <base> <usuario> <clave> | copiar <origen> <destino>]"
    echo "    registros [web|db|cron|auditoria|todos]"
    echo "    estado             resumen general del servidor"
    echo "    ayuda              esta ayuda"
}

# ----------------------------------------------------------------------
# Despacho
# ----------------------------------------------------------------------
TAREA="${1:-menu}"
case "$TAREA" in
    menu) menu ;;
    servicios) op_servicios_accion "${2:-estado}" "${3:-app}" ;;
    red) op_red ;;
    procesos) op_procesos ;;
    respaldos) op_respaldos ;;
    restauracion) op_restauracion "${2:-}" ;;
    usuarios)
        if [ "${2:-}" = "crear" ]; then
            op_usuarios_crear "${3:-}" "${4:-taller}"
        else
            op_usuarios
        fi
        ;;
    grupos)
        if [ -n "${2:-}" ]; then
            op_grupos_accion "$2" "${3:-}" "${4:-}"
        else
            op_grupos
        fi
        ;;
    origenes)
        if [ "${2:-}" = "crear" ]; then
            op_origenes_crear "${3:-}" "${4:-}" "${5:-}"
        elif [ "${2:-}" = "copiar" ]; then
            op_origenes_copiar "${3:-}" "${4:-}"
        else
            op_origenes
        fi
        ;;
    registros) op_registros_accion "${2:-todos}" ;;
    estado) op_estado ;;
    ayuda|help|-h|--help) ayuda ;;
    *)
        echo "  Tarea desconocida: $TAREA"
        ayuda
        exit 1
        ;;
esac
