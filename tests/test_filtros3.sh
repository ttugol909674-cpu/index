#!/bin/bash
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/cookies_csr.txt
rm -f $JAR
tgs_login $BASE $JAR "$ADMIN" "$PASS"

echo "== Cambio de rol con filtro aplicado =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?q=bluetech&rol_filtro=admin")
echo "$H" | grep -o 'name="id_usuario" value="[^"]*"' | head -1
CED=$(echo "$H" | grep -o 'name="id_usuario" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"//')
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&id_usuario=$CED&nuevo_rol=gestor" "$BASE/administrar_usuarios.php?rol_filtro=admin")
echo "$R" | grep -q "Rol actualizado" && echo "CAMBIO_ROL_OK" || echo "CAMBIO_ROL_FALLO"
docker exec tgs_db $DB "SELECT rol FROM usuario WHERE cedula='$CED';"
echo "$R" | grep -o 'value="bluetech"' | head -1 | sed 's/^/filtro_preservado=/'

echo "== Restaurar rol =="
curl -s -c $JAR -b $JAR -o /dev/null -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&id_usuario=$CED&nuevo_rol=admin" "$BASE/administrar_usuarios.php"
docker exec tgs_db $DB "SELECT rol FROM usuario WHERE cedula='$CED';"
rm -f $JAR
echo FIN
