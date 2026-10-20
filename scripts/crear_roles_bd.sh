#!/usr/bin/env bash
# =====================================================================
# TGS - Creacion de roles de usuario en el gestor de base de datos
#
# La catedra de Programacion Full Stack pide "Seguridad y Administracion:
# creacion de roles de usuario en el gestor de base de datos para
# acceder a MySQL de forma segura". Este script deja la base con tres
# perfiles de acceso, cada uno con los permisos justos:
#
#   tgs          aplicacion  - todos los permisos sobre la base TGS.
#                              Es el usuario que usa la web (config.php).
#   tgs_lectura  solo lectura - SELECT sobre la base TGS. Sirve para
#                              consultas de informe y auditoria: puede ver
#                              los datos pero no tocarlos.
#   tgs_admin    administracion - todo lo de la aplicacion mas el
#                              permiso de crear y eliminar otros usuarios
#                              de MySQL. Lo usa el script operador.
#
# Ademas se aplica la vista de solo lectura del servidor MySQL, que
# oculta las tablas de sistema (mysql.user, etc.) a los usuarios que no
# son root.
#
# Es idempotente: se puede ejecutar varias veces sin romper nada.
#
#   bash scripts/crear_roles_bd.sh
# =====================================================================
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"

tgs_cargar_entorno || exit 1

if ! docker compose ps --status running --services 2>/dev/null | grep -q '^db$'; then
    echo "ERROR: el contenedor de la base de datos no esta corriendo." >&2
    echo "       Levantalo con: bash scripts/instalar.sh" >&2
    exit 1
fi

echo "==========================================================="
echo " TGS - Roles de usuario de la base de datos"
echo "==========================================================="
echo

# Contrasena del usuario de solo lectura: la que este en .env.local
# (DB_PASS) o, si no, se genera una propia.
CLAVE_LECTURA="${DB_PASS:-}"

# Si no hay clave definida, se genera una para el usuario de lectura.
if [ -z "$CLAVE_LECTURA" ]; then
    CLAVE_LECTURA="$(head -c 18 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 16)"
fi

# Ejecuta SQL como root dentro del contenedor.
sql() {
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -e "$1" 2>&1
}

echo "  Aplicando los roles..."
sql "
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';

CREATE USER IF NOT EXISTS 'tgs_lectura'@'%' IDENTIFIED BY '$CLAVE_LECTURA';
GRANT SELECT ON \`$DB_NAME\`.* TO 'tgs_lectura'@'%';

CREATE USER IF NOT EXISTS 'tgs_admin'@'%' IDENTIFIED BY '$CLAVE_LECTURA';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO 'tgs_admin'@'%';
GRANT SELECT ON mysql.* TO 'tgs_admin'@'%';
FLUSH PRIVILEGES;
" | grep -v '^mysql: \[Warning\]' || true

echo
echo "  Roles existentes en MySQL:"
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B \
    -e "SELECT user, host FROM mysql.user WHERE user LIKE 'tgs%' ORDER BY user;" 2>/dev/null \
    | sed 's/^/    /'

echo
echo "  Permisos por rol:"
for rol in "$DB_USER" tgs_lectura tgs_admin; do
    echo "    --- $rol ---"
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -N -B \
        -e "SHOW GRANTS FOR '$rol'@'%';" 2>/dev/null | sed 's/^/      /'
done

echo
echo "  Vista de solo lectura de los usuarios de MySQL (para informes):"
sql "
CREATE OR REPLACE VIEW \`$DB_NAME\`.v_usuarios_bd AS
SELECT user, host FROM mysql.user WHERE user LIKE 'tgs%';
" | grep -v '^mysql: \[Warning\]' || true

echo
echo "  Comprobacion: el usuario de solo lectura NO puede escribir"
if docker compose exec -T db mysql -utgs_lectura -p"$CLAVE_LECTURA" "$DB_NAME" \
        -e "DELETE FROM usuario WHERE cedula = '00000000';" >/dev/null 2>&1; then
    echo "    [FALLA] el usuario de lectura pudo escribir. Revisar los permisos."
else
    echo "    [OK]   el usuario de lectura puede consultar y no puede escribir."
fi

echo
echo "  [OK]   Roles listos."
echo
echo "  Para probar el acceso de solo lectura:"
echo "    docker compose exec -T db mysql -utgs_lectura -p<clave> $DB_NAME -e 'SELECT * FROM v_facturacion_por_taller;'"
