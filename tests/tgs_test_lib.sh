# Las credenciales de las cuentas de demostracion se leen del archivo local
# tests/credenciales.local.sh, que NO se versiona. Copiar
# tests/credenciales.ejemplo.sh y completarlo con las claves reales.
TGS_PASS_ADMIN=""
TGS_PASS_GESTOR=""
TGS_PASS_CLIENTE=""

if [ -f "$(dirname "${BASH_SOURCE[0]}")/credenciales.local.sh" ]; then
    # shellcheck disable=SC1091
    . "$(dirname "${BASH_SOURCE[0]}")/credenciales.local.sh"
fi

# Clave de root de MySQL: se lee del .env.local del proyecto para no
# dejarla escrita en los tests y para que la suite funcione en cualquier
# maquina (la clave se genera al instalar).
TGS_PROYECTO="${TGS_PROYECTO:-$HOME/Desktop/tgs}"
TGS_DB_ROOT_PASS=""
for archivo_env in "$TGS_PROYECTO/.env.local" "$TGS_PROYECTO/.env"; do
    if [ -f "$archivo_env" ]; then
        TGS_DB_ROOT_PASS="$(grep -E '^DB_ROOT_PASS=' "$archivo_env" | head -1 | cut -d= -f2-)"
        [ -n "$TGS_DB_ROOT_PASS" ] && break
    fi
done

#!/bin/bash
# Libreria compartida por los scripts de prueba del proyecto TGS.
# Desde que todos los formularios POST exigen token CSRF, los scripts tienen
# que pedirlo antes de enviar cada peticion.
#
# IMPORTANTE: el archivo de cookies SIEMPRE tiene que estar en /tmp. Si por
# error se pasa otra ruta, curl escribiria el cookie jar encima de un
# archivo del proyecto (una vez se rompio login.php por eso). Por eso las
# funciones abortan si la ruta no es absoluta.

# Aborta si la ruta del archivo de cookies no es absoluta.
_tgs_check_jar() {
    case "$1" in
        /*) return 0 ;;
        *) echo "ERROR: el archivo de cookies '$1' debe ser una ruta absoluta (ej: /tmp/jar.txt)" >&2
           return 1 ;;
    esac
}

# Inicia sesion en el sitio: pide el formulario de login (que trae el token)
# y despues envia las credenciales con ese token.
tgs_login() {
    local base=$1 jar=$2 email=$3 pass=$4
    _tgs_check_jar "$jar" || return 1
    rm -f "$jar"
    local token
    token=$(curl -s -c "$jar" -b "$jar" "$base/login.php" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
    curl -s -c "$jar" -b "$jar" -o /dev/null -w "login=%{http_code}\n" \
        -d "csrf_token=$token&email=$email&password=$pass" "$base/login.php"
}

# Devuelve el token CSRF de una pagina (el primero que encuentre).
tgs_token() {
    local base=$1 jar=$2 url=$3
    if [ -z "$url" ]; then
        echo "ERROR: tgs_token necesita la base, el archivo de cookies y la pagina" >&2
        return 1
    fi
    _tgs_check_jar "$jar" || return 1
    curl -s -c "$jar" -b "$jar" "$base/$url" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//'
}
