#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_detalle.txt
rm -f $JAR
tgs_login $BASE $JAR "$ADMIN" "$PASS"

IDRES=1
MARTES=$(date -d "+20 days" +%Y-%m-%d)
while [ $(date -d "$MARTES" +%u) -ne 2 ]; do MARTES=$(date -d "$MARTES + 1 day" +%Y-%m-%d); done

echo "== 0. Datos de prueba en la reserva 1 =="
docker exec tgs_db $DB "UPDATE reserva SET fecha_reserva='$MARTES', hora_reserva='10:00:00', estado='Pendiente', precio_total=2500.00, estado_pago='pagado' WHERE id_reserva=1;" > /dev/null
docker exec tgs_db $DB "DELETE FROM notificacion;" > /dev/null
docker exec tgs_db $DB "INSERT INTO notificacion (id_notificacion,id_reserva,cedula_usuario,email_destino,asunto,cuerpo,tipo,estado) VALUES (95,1,'11111111','carlos.gomez@gmail.com','Tu vehiculo esta listo','Cuerpo','finalizacion','pendiente');" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva_historial WHERE id_reserva=1;" > /dev/null
docker exec tgs_db $DB "INSERT INTO reserva_historial (id_reserva,campo,valor_anterior,valor_nuevo,usuario) VALUES (1,'estado_reserva','Pendiente','En curso','Gestor');" > /dev/null

echo "== 1. El detalle carga =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" "$BASE/detalle_reserva.php?id=$IDRES")
echo "detalle_get=$CODE (esperado 200)"

echo "== 2. Muestra los datos del turno, cliente, vehiculo y sede =="
H=$(curl -s -c $JAR -b $JAR "$BASE/detalle_reserva.php?id=$IDRES")
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
[ -n "$TOKEN" ] && echo "TOKEN_OK" || echo "TOKEN_FALLO"
echo "$H" | grep -c "Carlos Gomez" | sed 's/^/cliente=/'
echo "$H" | grep -c "Toyota Corolla" | sed 's/^/vehiculo=/'
echo "$H" | grep -c "TGS Centro" | sed 's/^/sede=/'
echo "$H" | grep -c "Mantenimiento preventivo" | sed 's/^/servicio=/'

echo "== 3. Muestra cobros, presupuestos, avisos e historial =="
echo "$H" | grep -c "1.500,00" | sed 's/^/cobros=/'
echo "$H" | grep -c "Tu vehiculo esta listo" | sed 's/^/avisos=/'
echo "$H" | grep -c "estado_reserva" | sed 's/^/historial=/'
echo "$H" | grep -c "2.500,00" | sed 's/^/total=/'

echo "== 4. Registrar un cobro desde el detalle =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=registrar_pago&id_reserva=$IDRES&monto=300&metodo=efectivo&referencia=DET-01&notas=Desde el detalle" "$BASE/detalle_reserva.php?id=$IDRES")
echo "$R" | grep -o "Cobro registrado correctamente" | head -1
docker exec tgs_db $DB "SELECT id_pago,monto,metodo,referencia,estado FROM pago WHERE id_reserva=$IDRES ORDER BY id_pago DESC LIMIT 2;"

echo "== 5. El estado de cobro se recalcula =="
docker exec tgs_db $DB "SELECT estado_pago FROM reserva WHERE id_reserva=$IDRES;"

echo "== 6. Anular el cobro =="
PID=$(docker exec tgs_db $DB "SELECT MAX(id_pago) FROM pago WHERE id_reserva=$IDRES;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=anular_pago&id_reserva=$IDRES&id_pago=$PID" "$BASE/detalle_reserva.php?id=$IDRES")
echo "$R" | grep -o "Cobro anulado correctamente" | head -1
docker exec tgs_db $DB "SELECT id_pago,estado FROM pago WHERE id_pago=$PID;"

echo "== 7. Cambiar el estado del turno =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=cambiar_estado&id_reserva=$IDRES&estado_reserva=En curso" "$BASE/detalle_reserva.php?id=$IDRES")
echo "$R" | grep -o "Estado actualizado correctamente" | head -1
docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;"

echo "== 8. Marcar Finalizada genera aviso al cliente =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=cambiar_estado&id_reserva=$IDRES&estado_reserva=Finalizada" "$BASE/detalle_reserva.php?id=$IDRES")
echo "$R" | grep -o "Estado actualizado correctamente[^<]*" | head -1
echo "avisos=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion WHERE id_reserva=$IDRES;")"

echo "== 9. Reprogramar con disponibilidad invalida (domingo) =="
D2=$(date -d "+20 days" +%Y-%m-%d); while [ $(date -d "$D2" +%u) -ne 7 ]; do D2=$(date -d "$D2 + 1 day" +%Y-%m-%d); done
echo "domingo=$D2"
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=reprogramar&id_reserva=$IDRES&nueva_fecha=$D2&nueva_hora=10:00&nueva_sede=1" "$BASE/detalle_reserva.php?id=$IDRES")
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1
echo "fecha_sin_cambio=$(docker exec tgs_db $DB "SELECT fecha_reserva FROM reserva WHERE id_reserva=$IDRES;")"

echo "== 10. Reprogramar valido =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=reprogramar&id_reserva=$IDRES&nueva_fecha=$MARTES&nueva_hora=15:30&nueva_sede=2" "$BASE/detalle_reserva.php?id=$IDRES")
echo "$R" | grep -o "Turno reprogramado correctamente" | head -1
docker exec tgs_db $DB "SELECT fecha_reserva,hora_reserva,id_taller FROM reserva WHERE id_reserva=$IDRES;"

echo "== 11. Sin CSRF no hace nada =="
R=$(curl -s -c $JAR -b $JAR -d "accion=registrar_pago&id_reserva=$IDRES&monto=100&metodo=efectivo" "$BASE/detalle_reserva.php?id=$IDRES")
echo "$R" | grep -o "La sesión expiró[^<]*" | head -1

echo "== 12. Reserva inexistente =="
R=$(curl -s -c $JAR -b $JAR "$BASE/detalle_reserva.php?id=99999")
echo "$R" | grep -o "El turno no existe" | head -1

echo "== 13. El cliente no puede ver el detalle =="
JAR2=/tmp/jar_detalle_cli.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
echo "cliente_detalle=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/detalle_reserva.php?id=1) (esperado 302)"

echo "== 14. Enlaces desde la agenda y el listado =="
H2=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=$MARTES")
echo "$H2" | grep -c "detalle_reserva.php" | sed 's/^/enlaces_agenda=/'
H3=$(curl -s -c $JAR -b $JAR $BASE/administrar_reservas.php)
echo "$H3" | grep -c "detalle_reserva.php" | sed 's/^/enlaces_listado=/'

echo "== 15. Restaurar datos de demo =="
docker exec tgs_db $DB "UPDATE reserva SET fecha_reserva='2026-10-05', hora_reserva='10:00:00', estado='Pendiente', precio_total=2500.00, estado_pago='pagado' WHERE id_reserva=1;" > /dev/null
docker exec tgs_db $DB "DELETE FROM pago WHERE id_reserva=1 AND referencia='DET-01';" > /dev/null
docker exec tgs_db $DB "DELETE FROM notificacion WHERE id_notificacion=95;" > /dev/null
docker exec tgs_db $DB "DELETE FROM pago WHERE id_pago > 90;" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva_historial WHERE id_reserva=1;" > /dev/null
docker exec tgs_db $DB "SELECT id_reserva,fecha_reserva,estado,precio_total,estado_pago FROM reserva; SELECT COUNT(*) FROM pago;"
rm -f $JAR $JAR2
echo "FIN"
