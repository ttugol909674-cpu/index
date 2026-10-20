#!/usr/bin/env bash
# =====================================================================
# TGS - Libreria compartida por los scripts de instalacion
#
# Este archivo se carga con "source", no se ejecuta directo.
# Su unico trabajo es poner las variables de .env y .env.local en el
# entorno, aplicando la precedencia del proyecto:
#
#     .env        (global, versionada)   se carga primero
#     .env.local  (local, no versionada)  se carga despues y pisa
#
# Asi Docker Compose ve exactamente los mismos valores que leeria
# config.php dentro del contenedor.
#
# NOTA: al usar "source", el contenido de los .env se interpreta como
# shell. Por eso los valores con espacios deben ir entre comillas.
# =====================================================================

# Raiz del proyecto (un nivel arriba de la carpeta scripts/)
TGS_RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Carga .env y luego .env.local, exportando todo al entorno.
tgs_cargar_entorno() {
    if [ ! -f "$TGS_RAIZ/.env" ]; then
        echo "ERROR: falta el archivo de variables globales $TGS_RAIZ/.env" >&2
        echo "       se genera solo con:  bash scripts/instalar.sh" >&2
        return 1
    fi

    set -a
    # shellcheck disable=SC1091
    . "$TGS_RAIZ/.env"

    if [ -f "$TGS_RAIZ/.env.local" ]; then
        # shellcheck disable=SC1091
        . "$TGS_RAIZ/.env.local"
    fi
    set +a

    export TGS_RAIZ
    return 0
}

# Crea .env a partir de .env.example si todavia no existe.
# El .env global no se versiona (lleva claves), asi que un clone nuevo
# no lo trae: se genera desde la plantilla para que instalar.sh funcione
# recien clonado, sin pasos manuales.
tgs_crear_env_global() {
    if [ -f "$TGS_RAIZ/.env" ]; then
        echo "[OK]   .env ya existe, se respeta"
        return 0
    fi

    if [ ! -f "$TGS_RAIZ/.env.example" ]; then
        echo "ERROR: no existe .env.example, no se puede generar .env" >&2
        return 1
    fi

    cp "$TGS_RAIZ/.env.example" "$TGS_RAIZ/.env"
    echo "[OK]   .env creado a partir de .env.example"
    return 0
}

# Crea .env.local a partir de .env.example si todavia no existe.
# Esto es lo que genera las VARIABLES LOCALES de la maquina.
tgs_crear_env_local() {
    if [ -f "$TGS_RAIZ/.env.local" ]; then
        echo "[OK]   .env.local ya existe, se respeta"
        return 0
    fi

    if [ ! -f "$TGS_RAIZ/.env.example" ]; then
        echo "ERROR: no existe .env.example, no se puede generar .env.local" >&2
        return 1
    fi

    cp "$TGS_RAIZ/.env.example" "$TGS_RAIZ/.env.local"

    # La contrasena de la plantilla no sirve: se genera una propia.
    local senha
    senha="$(head -c 18 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 16)"

    # sed -i con unaexpression segura, el separador | evita conflictos
    sed -i "s|^DB_PASS=.*|DB_PASS=Tgs${senha}|"        "$TGS_RAIZ/.env.local"
    sed -i "s|^DB_ROOT_PASS=.*|DB_ROOT_PASS=Root${senha}|" "$TGS_RAIZ/.env.local"

    # Los permisos se ajustan en tgs_ajustar_permisos_env_local(), que
    # tambien se ejecuta cuando el archivo ya existia.
    echo "[OK]   .env.local creado con contrasenas generadas para esta maquina"
    return 0
}

# El servidor web corre como www-data (uid 33) dentro del contenedor y
# tiene que poder LEER .env y .env.local. Un 600 o 640 deja la aplicacion
# sin credenciales y MySQL responde "Access denied".
#
# La solucion es 644: el archivo se lee desde el contenedor, pero sigue
# fuera de git gracias a la regla .env.local del .gitignore.
tgs_ajustar_permisos_env_local() {
    local objetivo

    for objetivo in "$TGS_RAIZ/.env.local" "$TGS_RAIZ/.env"; do
        if [ -f "$objetivo" ]; then
            chmod 644 "$objetivo"
        fi
    done
}

# Verifica que Docker este instalado y el demonio este encendido.
tgs_verificar_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        echo "ERROR: Docker no esta instalado." >&2
        echo "       En Ubuntu: sudo apt install -y docker.io docker-compose-v2" >&2
        return 1
    fi

    if ! docker info >/dev/null 2>&1; then
        echo "ERROR: el demonio de Docker no responde." >&2
        echo "       Prueba con: sudo systemctl start docker" >&2
        return 1
    fi

    if ! docker compose version >/dev/null 2>&1; then
        echo "ERROR: falta el plugin docker compose (v2)." >&2
        echo "       En Ubuntu: sudo apt install -y docker-compose-v2" >&2
        return 1
    fi

    echo "[OK]   Docker $(docker --version | awk '{print $3}') / Compose $(docker compose version --short)"
    return 0
}

# Espera a que el contenedor de base de datos responda.
tgs_esperar_db() {
    local intentos=${1:-40}

    echo "[....] esperando a que MySQL acepte conexiones"

    for _ in $(seq 1 "$intentos"); do
        if docker compose -f "$TGS_RAIZ/docker-compose.yml" exec -T db \
            mysqladmin ping -h 127.0.0.1 -uroot -p"$DB_ROOT_PASS" --silent >/dev/null 2>&1; then
            echo "[OK]   MySQL responde"
            return 0
        fi
        sleep 2
    done

    echo "ERROR: MySQL no respondio a tiempo. Revisa: docker compose logs db" >&2
    return 1
}
