#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
APASS="$TGS_PASS_ADMIN"
CLI="carlos.gomez@gmail.com"
CPASS="$TGS_PASS_CLIENTE"
JAR=/tmp/j_pago_cli.txt
JAR2=/tmp/j_pago_adm.txt

echo "== 1. Migracion (datos_pago) =="
docker exec -i tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base < /home/taller/Desktop/tgs/db/migracion.sql 2>&1 | grep -v Warning
docker exec tgs_db $DB "SHOW TABLES LIKE 'datos_pago';"
docker exec tgs_db $DB "SELECT id_datos_pago,nombre,alias,activo FROM datos_pago;"

echo "== 2. Login del cliente =="
tgs_login $BASE $JAR "$CLI" "$CPASS"
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/pagos_cliente.php)
echo "pagos_cliente=$CODE (esperado 200)"

echo "== 3. El cliente ve los datos bancarios y el QR =="
H=$(curl -s -c $JAR -b $JAR $BASE/pagos_cliente.php)
echo "$H" | grep -c "TGS.DEMO.ALIAS" | sed 's/^/alias_visible=/'
echo "$H" | grep -c "0000000000000000000000" | sed 's/^/cbu_visible=/'
echo "$H" | grep -c "<img src=\"https://api.qrserver.com" | sed 's/^/qr_visible=/'
echo "$H" | grep -c "copiarTexto" | sed 's/^/boton_copiar=/'

echo "== 4. El cliente ve su saldo pendiente =="
# La reserva demo esta totalmente pagada, asi que se crea una con saldo
# pendiente para poder probar el reporte de pago.
docker exec tgs_db $DB "DELETE FROM pago WHERE id_pago > 90;" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva > 90;" > /dev/null
docker exec tgs_db $DB "INSERT INTO reserva (id_reserva,cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES (95,'11111111',1,1,1,'2026-11-20','10:00:00','Pendiente',1800.00,'pendiente');" > /dev/null
H=$(curl -s -c $JAR -b $JAR $BASE/pagos_cliente.php)
echo "$H" | grep -o "1.800,00 USD" | head -1 | sed 's/^/saldo=/'
echo "$H" | grep -c 'accion" value="reportar_pago"' | sed 's/^/formularios_reporte=/'

echo "== 5. Reportar un pago (queda 'reportado', NO cuenta como pagado) =="
TOKEN=$(tgs_token $BASE $JAR "pagos_cliente.php")
curl -s -c $JAR -b $JAR -o /dev/null -d "csrf_token=$TOKEN&accion=reportar_pago&id_reserva=95&monto=500&metodo=transferencia&referencia=REC-DEMO-01&notas=Transferencia de prueba" $BASE/pagos_cliente.php
docker exec tgs_db $DB "SELECT id_pago,id_reserva,monto,metodo,estado,referencia FROM pago WHERE referencia='REC-DEMO-01';"
echo "estado_reserva=$(docker exec tgs_db $DB "SELECT estado_pago FROM reserva WHERE id_reserva=95;") (esperado pendiente: el cobro reportado no cuenta hasta que se aprueba)"

echo "== 6. Monto mayor al saldo (debe rechazarse) =="
TOKEN=$(tgs_token $BASE $JAR "pagos_cliente.php")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=reportar_pago&id_reserva=95&monto=999999&metodo=efectivo" $BASE/pagos_cliente.php)
echo "$R" | grep -o "mayor que el saldo pendiente" | head -1
echo "pagos_creados=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM pago WHERE referencia IS NULL AND notas='Transferencia de prueba';") (esperado 1)"

echo "== 7. Reportar sobre una reserva ajena (debe rechazarse) =="
TOKEN=$(tgs_token $BASE $JAR "pagos_cliente.php")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=reportar_pago&id_reserva=999&monto=100&metodo=efectivo" $BASE/pagos_cliente.php)
echo "$R" | grep -o "Ese turno no es tuyo" | head -1

echo "== 8. El taller ve la cola de pagos por validar =="
tgs_login $BASE $JAR2 "$ADMIN" "$APASS"
H2=$(curl -s -c $JAR2 -b $JAR2 $BASE/administrar_pagos.php)
echo "$H2" | grep -c "Cobros reportados por los clientes" | sed 's/^/seccion_validacion=/'
echo "$H2" | grep -c "REC-DEMO-01" | sed 's/^/pago_reportado_visible=/'
echo "$H2" | grep -c 'accion" value="aprobar_pago"' | sed 's/^/boton_aprobar=/'
echo "$H2" | grep -c 'accion" value="rechazar_pago"' | sed 's/^/boton_rechazar=/'

echo "== 9. Aprobar el pago (pasa a pagado y recalcula) =="
PID=$(docker exec tgs_db $DB "SELECT id_pago FROM pago WHERE referencia='REC-DEMO-01';")
TOKEN=$(tgs_token $BASE $JAR2 "administrar_pagos.php")
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$TOKEN&accion=aprobar_pago&id_pago=$PID" $BASE/administrar_pagos.php)
echo "$R" | grep -o "Cobro reportado aprobado" | head -1
docker exec tgs_db $DB "SELECT id_pago,estado FROM pago WHERE id_pago=$PID;"

echo "  saldo_tras_aprobacion=$(docker exec tgs_db $DB "SELECT estado_pago FROM reserva WHERE id_reserva=95;") (esperado parcial: 500 de 1800)"
echo "== 10. Rechazar un pago reportado =="
TOKEN=$(tgs_token $BASE $JAR2 "administrar_pagos.php")
curl -s -c $JAR2 -b $JAR2 -o /dev/null -d "csrf_token=$TOKEN&accion=reportar_pago&id_reserva=95&monto=100&metodo=efectivo&referencia=REC-RECH-01" $BASE/pagos_cliente.php 2>/dev/null
# El cliente reporta de nuevo
TOKEN=$(tgs_token $BASE $JAR "pagos_cliente.php")
curl -s -c $JAR -b $JAR -o /dev/null -d "csrf_token=$TOKEN&accion=reportar_pago&id_reserva=95&monto=100&metodo=efectivo&referencia=REC-RECH-01" $BASE/pagos_cliente.php
PID2=$(docker exec tgs_db $DB "SELECT id_pago FROM pago WHERE referencia='REC-RECH-01';")
TOKEN=$(tgs_token $BASE $JAR2 "administrar_pagos.php")
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$TOKEN&accion=rechazar_pago&id_pago=$PID2" $BASE/administrar_pagos.php)
echo "$R" | grep -o "Cobro reportado rechazado" | head -1
docker exec tgs_db $DB "SELECT id_pago,estado FROM pago WHERE id_pago=$PID2;"

echo "== 11. El cliente ve el estado de sus reportes =="
H3=$(curl -s -c $JAR -b $JAR $BASE/pagos_cliente.php)
echo "$H3" | grep -c "Reportado" | sed 's/^/badge_reportado=/'
echo "$H3" | grep -c "Rechazado" | sed 's/^/badge_rechazado=/'

echo "== 12. El admin gestiona los datos bancarios =="
JAR3=/tmp/j_pago_dp.txt
tgs_login $BASE $JAR3 "$ADMIN" "$APASS"
CODE=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/datos_pago.php)
echo "datos_pago=$CODE (esperado 200)"
TOKEN=$(tgs_token $BASE $JAR3 "datos_pago.php")
curl -s -c $JAR3 -b $JAR3 -o /dev/null -d "csrf_token=$TOKEN&accion=guardar&nombre=Cuenta de prueba&alias=PRUEBA.ALIAS&cbu=1111111111111111111111&qr=https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=PRUEBA&notas=Prueba&activo=1" $BASE/datos_pago.php
echo "cuentas=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM datos_pago;") (esperado 2)"
echo "$H3" | grep -c "PRUEBA.ALIAS" | sed 's/^/cuenta_nueva_en_listado=/'

echo "== 13. El cliente ve la cuenta nueva activa =="
H4=$(curl -s -c $JAR -b $JAR $BASE/pagos_cliente.php)
echo "$H4" | grep -c "PRUEBA.ALIAS" | sed 's/^/cuenta_nueva_visible_para_cliente=/'

echo "== 14. El gestor NO puede administrar los datos bancarios =="
JAR4=/tmp/j_pago_ges.txt
tgs_login $BASE $JAR4 "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR"
echo "gestor_datos_pago=$(curl -s -c $JAR4 -b $JAR4 -o /dev/null -w "%{http_code}" $BASE/datos_pago.php) (esperado 302)"

echo "== 15. Limpieza =="
docker exec tgs_db $DB "DELETE FROM pago WHERE referencia IN ('REC-DEMO-01','REC-RECH-01');" > /dev/null
docker exec tgs_db $DB "DELETE FROM datos_pago WHERE alias='PRUEBA.ALIAS';" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva=95;" > /dev/null
docker exec tgs_db $DB "SELECT COUNT(*) FROM pago; SELECT COUNT(*) FROM datos_pago;"

rm -f $JAR $JAR2 $JAR3 $JAR4
echo "FIN"
