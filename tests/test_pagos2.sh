#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"

echo "== A. Migracion idempotente (se ejecuta 2 veces mas) =="
for i in 1 2; do
  docker exec -i tgs_db mysql -uroot -p$TGS_DB_ROOT_PASS bluetech_base < /home/taller/Desktop/tgs/db/migracion.sql 2>&1 | grep -v "Warning" | grep -v "^$" | sed "s/^/ejecucion$i: /"
done
echo "(sin salida = sin errores)"
docker exec tgs_db $DB "SHOW COLUMNS FROM reserva LIKE 'precio_total';"
docker exec tgs_db $DB "SELECT COUNT(*) FROM pago;"

echo "== B. Limpieza de datos de prueba =="
docker exec tgs_db $DB "DELETE FROM pago WHERE referencia='TEST-001';" > /dev/null
docker exec tgs_db $DB "UPDATE reserva SET precio_total=2500.00, estado_pago='pendiente' WHERE id_reserva=1;" > /dev/null
docker exec tgs_db $DB "SELECT id_reserva,precio_total,estado_pago FROM reserva WHERE id_reserva=1;"

echo "== C. Reserva nueva desde el cliente (congela precio) =="
JAR=/tmp/cookies_cli2.txt
rm -f $JAR
tgs_login $BASE $JAR "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
# Vehiculo 1 (Toyota Corolla) y servicio 1 (Mantenimiento preventivo, 2500)
FECHA=$(date -d "+10 days" +%Y-%m-%d)
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&fecha=$FECHA&hora=11:00" $BASE/reservas.php)
echo "$R" | grep -q "Turno reservado con éxito" && echo "RESERVA_NUEVA_OK" || echo "RESERVA_NUEVA_FALLO"
docker exec tgs_db $DB "SELECT id_reserva,precio_total,estado_pago,estado FROM reserva ORDER BY id_reserva DESC LIMIT 1;"

echo "== D. La nueva reserva aparece como pendiente de pago =="
H=$(curl -s -c $JAR -b $JAR $BASE/reservas.php)
echo "$H" | grep -c "Pendiente" | sed 's/^/pendientes_en_vista=/'

echo "== E. El admin ve la reserva nueva con el total congelado =="
JAR2=/tmp/cookies_adm2.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "admin@bluetech.com" "$TGS_PASS_ADMIN"
H=$(curl -s -c $JAR2 -b $JAR2 "$BASE/administrar_pagos.php")
echo "$H" | grep -o "2.500,00" | head -1 | sed 's/^/total_en_vista=/'
echo "$H" | grep -c "badge pendiente" | sed 's/^/badges_pendiente=/'

echo "== F. Limpieza de la reserva de prueba =="
NUEVA=$(docker exec tgs_db $DB "SELECT id_reserva FROM reserva ORDER BY id_reserva DESC LIMIT 1;")
NUEVA=$(docker exec tgs_db $DB "SELECT MAX(id_reserva) FROM reserva;"); docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva=$NUEVA;" > /dev/null
docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;"

rm -f $JAR $JAR2
echo "FIN"
