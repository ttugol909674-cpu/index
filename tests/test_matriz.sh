#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_matriz.txt
rm -f $JAR

tgs_login $BASE $JAR "$ADMIN" "$PASS"
H=$(curl -s -c $JAR -b $JAR $BASE/permisos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
echo "checkboxes=$(echo "$H" | grep -c 'type="checkbox"') ocultos=$(echo "$H" | grep -c 'type="hidden" name="permisos' )"

# Extrae SOLO los checkboxes marcados (como hace el navegador).
marcados() {
  echo "$1" | grep -o 'name="permisos\[\]" value="[^"]*:1" checked' | sed 's/.*value="//; s/:1" checked//'
}
# Extrae todos los pares rol|permiso (checkbox + oculto).
todos() {
  echo "$1" | grep -o 'name="permisos\[\]" value="[^"]*"' | sed 's/.*value="//; s/"//' | sed 's/:0$//; s/:1$//' | sort -u
}

echo "== 1. Desactivar SOLO gestor|administrar_pagos =="
LISTA=$(marcados "$H" | grep -v "^gestor|administrar_pagos$")
PARAM=""
for item in $LISTA; do PARAM="$PARAM&permisos[]=$item:1"; done
# se agrega el 0 explicito de la casilla desmarcada
PARAM="$PARAM&permisos[]=gestor|administrar_pagos:0"
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_matriz$PARAM" $BASE/permisos.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
echo "filas=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM permiso_rol;")"
docker exec tgs_db $DB "SELECT rol,permiso,activo FROM permiso_rol;"

echo "== 2. El gestor ya no entra a la pantalla de pagos =="
JAR2=/tmp/jar_gestor.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR"
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/administrar_pagos.php)
echo "gestor_administrar_pagos=$CODE (esperado 302)"
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/administrar_presupuestos.php)
echo "gestor_administrar_presupuestos=$CODE (esperado 200, sin cambios)"

echo "== 3. El admin sigue entrando =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/administrar_pagos.php)
echo "admin_administrar_pagos=$CODE (esperado 200)"
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/permisos.php)
echo "admin_permisos=$CODE (esperado 200)"

echo "== 4. El cliente sigue con sus permisos =="
JAR3=/tmp/jar_cliente.txt
rm -f $JAR3
tgs_login $BASE $JAR3 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
CODE=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/reservas.php)
echo "cliente_reservas=$CODE (esperado 200)"
CODE=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/administrar_pagos.php)
echo "cliente_administrar_pagos=$CODE (esperado 302)"

echo "== 5. Reactivar marcando la casilla (vuelve al valor por defecto) =="
H=$(curl -s -c $JAR -b $JAR $BASE/permisos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
LISTA=$(marcados "$H")
PARAM=""
for item in $LISTA; do PARAM="$PARAM&permisos[]=$item:1"; done
# el admin marca la casilla deshabilitada -> se envia :1
PARAM="$PARAM&permisos[]=gestor|administrar_pagos:1"
curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_matriz$PARAM" $BASE/permisos.php > /dev/null
echo "filas=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM permiso_rol;") (esperado 0 = vuelve al valor por defecto)"
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/administrar_pagos.php)
echo "gestor_administrar_pagos=$CODE (esperado 200)"

echo "== 6. Guardar la matriz sin cambios debe dejar 0 filas (todo por defecto) =="
echo "filas=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM permiso_rol;")"
docker exec tgs_db $DB "SELECT rol,permiso,activo FROM permiso_rol;"

echo "== 7. Restaurar todo =="
H=$(curl -s -c $JAR -b $JAR $BASE/permisos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=restaurar_todo" $BASE/permisos.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
echo "filas=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM permiso_rol;")"

echo "== 8. Conceder un permiso extra (cliente|administrar_talleres) =="
H=$(curl -s -c $JAR -b $JAR $BASE/permisos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
LISTA=$(marcados "$H")
PARAM=""
for item in $LISTA; do PARAM="$PARAM&permisos[]=$item:1"; done
PARAM="$PARAM&permisos[]=cliente|administrar_talleres:1"
curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_matriz$PARAM" $BASE/permisos.php > /dev/null
docker exec tgs_db $DB "SELECT rol,permiso,activo FROM permiso_rol;"
CODE=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/administrar_talleres.php)
echo "cliente_administrar_talleres=$CODE (esperado 200 por el permiso concedido)"

echo "== 9. Restaurar y limpiar =="
H=$(curl -s -c $JAR -b $JAR $BASE/permisos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=restaurar_todo" $BASE/permisos.php > /dev/null
echo "filas_finales=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM permiso_rol;")"

rm -f $JAR $JAR2 $JAR3
echo "FIN"
