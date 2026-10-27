#!/bin/bash
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_fallback.txt
rm -f $JAR

echo "== 1. Estado actual de la matriz =="
docker exec tgs_db $DB "SELECT rol,permiso,activo FROM permiso_rol;"

echo "== 2. Formulario con formato viejo (sin :0/:1) NO debe tocar nada =="
tgs_login $BASE $JAR "$ADMIN" "$PASS"
H=$(curl -s -c $JAR -b $JAR $BASE/permisos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
ANTES=$(docker exec tgs_db $DB "SELECT GROUP_CONCAT(CONCAT(rol,'|',permiso,'=',activo)) FROM permiso_rol;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_matriz&permisos[]=cliente|ver_talleres&permisos[]=cliente|editar_perfil" $BASE/permisos.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
DESPUES=$(docker exec tgs_db $DB "SELECT GROUP_CONCAT(CONCAT(rol,'|',permiso,'=',activo)) FROM permiso_rol;")
echo "antes=$ANTES"
echo "despues=$DESPUES"
[ "$ANTES" = "$DESPUES" ] && echo "SIN_CAMBIOS_OK" || echo "CAMBIO_INESPERADO_FALLO"

echo "== 3. Formulario vacio tampoco toca nada =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_matriz" $BASE/permisos.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
DESPUES2=$(docker exec tgs_db $DB "SELECT GROUP_CONCAT(CONCAT(rol,'|',permiso,'=',activo)) FROM permiso_rol;")
[ "$ANTES" = "$DESPUES2" ] && echo "SIN_CAMBIOS_OK" || echo "CAMBIO_INESPERADO_FALLO"

echo "== 4. Restaurar la matriz de demostracion a los valores por defecto =="
H=$(curl -s -c $JAR -b $JAR $BASE/permisos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=restaurar_todo" $BASE/permisos.php > /dev/null
echo "filas=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM permiso_rol;") (esperado 0)"

rm -f $JAR
echo "FIN"
