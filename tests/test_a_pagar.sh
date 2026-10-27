#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
JAR=/tmp/jar_panel2.txt
rm -f $JAR
tgs_login $BASE $JAR "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"

echo "== Caso 1: todo pagado (1500+1000 de 2500) =="
docker exec tgs_db $DB "SELECT precio_total, estado_pago FROM reserva WHERE id_reserva=1;"
H=$(curl -s -c $JAR -b $JAR $BASE/panel_cliente.php)
echo "$H" | grep -o '[0-9.,]* USD' | head -2

echo "== Caso 2: solo se cobra un abono de 1000 (saldo 1500) =="
docker exec tgs_db $DB "DELETE FROM pago WHERE id_pago=2;" > /dev/null
docker exec tgs_db $DB "UPDATE reserva SET estado_pago='parcial' WHERE id_reserva=1;" > /dev/null
H=$(curl -s -c $JAR -b $JAR $BASE/panel_cliente.php)
echo "$H" | grep -o '[0-9.,]* USD' | head -2
echo "$H" | grep -o 'badge parcial' | head -1

echo "== Caso 3: sin pagos (saldo 2500) =="
docker exec tgs_db $DB "DELETE FROM pago;" > /dev/null
docker exec tgs_db $DB "UPDATE reserva SET estado_pago='pendiente' WHERE id_reserva=1;" > /dev/null
H=$(curl -s -c $JAR -b $JAR $BASE/panel_cliente.php)
echo "$H" | grep -o '[0-9.,]* USD' | head -2
echo "$H" | grep -o 'badge pendiente' | head -1

echo "== Restaurar demo =="
docker exec tgs_db $DB "INSERT INTO pago (id_pago,id_reserva,monto,metodo,estado,referencia,fecha_pago,registrado_por,notas) VALUES (1,1,1500.00,'efectivo','pagado','REC-0001','2026-10-05 10:05:00','00000000','Abono al momento de la entrega'),(2,1,1000.00,'tarjeta','pagado','TC-45871','2026-10-05 10:20:00','00000000','Saldo final con tarjeta');" > /dev/null
docker exec tgs_db $DB "UPDATE reserva SET estado_pago='pagado' WHERE id_reserva=1;" > /dev/null
docker exec tgs_db $DB "SELECT precio_total, estado_pago FROM reserva WHERE id_reserva=1;"
rm -f $JAR
echo "FIN"
