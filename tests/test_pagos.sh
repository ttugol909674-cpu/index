#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/cookies_pagos.txt
rm -f $JAR

echo "== 1. Login admin =="
tgs_login $BASE $JAR "$ADMIN" "$PASS"

echo "== 2. Migracion aplicada (esquema) =="
docker exec tgs_db $DB "SHOW TABLES LIKE 'pago';"
docker exec tgs_db $DB "SHOW COLUMNS FROM reserva LIKE 'estado_pago';"
docker exec tgs_db $DB "SHOW COLUMNS FROM reserva LIKE 'precio_total';"

echo "== 3. La pagina de pagos carga =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/administrar_pagos.php)
echo "administrar_pagos_get=$CODE"

echo "== 4. Token CSRF presente =="
HTML=$(curl -s -c $JAR -b $JAR $BASE/administrar_pagos.php)
TOKEN=$(echo "$HTML" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
[ -n "$TOKEN" ] && echo "TOKEN_OK" || echo "TOKEN_FALLO"

echo "== 5. Estado de las reservas tras la migracion =="
docker exec tgs_db $DB "SELECT r.id_reserva, r.precio_total, r.estado_pago, COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva=r.id_reserva AND p.estado='pagado'),0) FROM reserva r;"

echo "== 6. Registrar un cobro (500) =="
ID=$(docker exec tgs_db $DB "SELECT id_reserva FROM reserva ORDER BY id_reserva LIMIT 1;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=registrar_pago&id_reserva=$ID&monto=500&metodo=efectivo&referencia=TEST-001&notas=Prueba automatica" $BASE/administrar_pagos.php)
echo "$R" | grep -q "Cobro registrado correctamente" && echo "COBRO_OK" || echo "COBRO_FALLO"
docker exec tgs_db $DB "SELECT id_pago,id_reserva,monto,metodo,estado,referencia,registrado_por FROM pago ORDER BY id_pago DESC LIMIT 3;"
docker exec tgs_db $DB "SELECT id_reserva,precio_total,estado_pago FROM reserva WHERE id_reserva=$ID;"

echo "== 7. Cobro con monto invalido =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=registrar_pago&id_reserva=$ID&monto=-5&metodo=efectivo" $BASE/administrar_pagos.php)
echo "$R" | grep -q "mayor a cero" && echo "MONTO_INVALIDO_OK" || echo "MONTO_INVALIDO_FALLO"

echo "== 8. Cobro sin metodo valido =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=registrar_pago&id_reserva=$ID&monto=100&metodo=delfin" $BASE/administrar_pagos.php)
echo "$R" | grep -q "método de pago válido" && echo "METODO_INVALIDO_OK" || echo "METODO_INVALIDO_FALLO"

echo "== 9. Cobro sin token CSRF =="
R=$(curl -s -c $JAR -b $JAR -d "accion=registrar_pago&id_reserva=$ID&monto=100&metodo=efectivo" $BASE/administrar_pagos.php)
echo "$R" | grep -q "sesión expiró" && echo "CSRF_OK" || echo "CSRF_FALLO"

echo "== 10. Anular el cobro -> estado vuelve a parcial/pendiente =="
PID=$(docker exec tgs_db $DB "SELECT id_pago FROM pago ORDER BY id_pago DESC LIMIT 1;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=anular_pago&id_pago=$PID" $BASE/administrar_pagos.php)
echo "$R" | grep -q "Cobro anulado correctamente" && echo "ANULAR_OK" || echo "ANULAR_FALLO"
docker exec tgs_db $DB "SELECT id_pago,estado FROM pago WHERE id_pago=$PID;"
docker exec tgs_db $DB "SELECT id_reserva,precio_total,estado_pago FROM reserva WHERE id_reserva=$ID;"

echo "== 11. Ajustar el total de la reserva =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=actualizar_total&id_reserva=$ID&precio_total=3000" $BASE/administrar_pagos.php)
echo "$R" | grep -q "Total actualizado correctamente" && echo "TOTAL_OK" || echo "TOTAL_FALLO"
docker exec tgs_db $DB "SELECT id_reserva,precio_total,estado_pago FROM reserva WHERE id_reserva=$ID;"

echo "== 12. Filtro por estado de cobro =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_pagos.php?estado_pago=pendiente")
echo "$H" | grep -c 'accion" value="registrar_pago"' | sed 's/^/filas_pendiente=/'
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_pagos.php?estado_pago=pagado")
echo "$H" | grep -c 'accion" value="registrar_pago"' | sed 's/^/filas_pagado=/'

echo "== 13. Busqueda por cliente =="
H=$(curl -s -c $JAR -b $JAR --get --data-urlencode "q=Carlos" "$BASE/administrar_pagos.php")
echo "$H" | grep -c 'accion" value="registrar_pago"' | sed 's/^/filas_carlos=/'

echo "== 14. Reserva inexistente =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=registrar_pago&id_reserva=99999&monto=100&metodo=efectivo" $BASE/administrar_pagos.php)
echo "$R" | grep -q "No se encontró la reserva" && echo "RESERVA_INEXISTENTE_OK" || echo "RESERVA_INEXISTENTE_FALLO"

echo "== 15. El cliente no puede entrar a la pantalla de pagos =="
JAR2=/tmp/cookies_cli.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/administrar_pagos.php)
echo "cliente_en_pagos=$CODE"

echo "== 16. El cliente ve el estado de pago de sus turnos =="
H=$(curl -s -c $JAR2 -b $JAR2 $BASE/reservas.php)
echo "$H" | grep -c "Pago" | sed 's/^/columna_pago=/'

rm -f $JAR $JAR2
echo "FIN"
