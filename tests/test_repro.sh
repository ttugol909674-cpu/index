#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_repro.txt
rm -f $JAR
tgs_login $BASE $JAR "$ADMIN" "$PASS"

# Un martes futuro
MARTES=$(date -d "+20 days" +%Y-%m-%d)
while [ $(date -d "$MARTES" +%u) -ne 2 ]; do MARTES=$(date -d "$MARTES + 1 day" +%Y-%m-%d); done
DOMINGO=$(date -d "+20 days" +%Y-%m-%d)
while [ $(date -d "$DOMINGO" +%u) -ne 7 ]; do DOMINGO=$(date -d "$DOMINGO + 1 day" +%Y-%m-%d); done
echo "martes=$MARTES domingo=$DOMINGO"

# Reserva de prueba en el taller 1, martes 10:00
IDRES=$(docker exec tgs_db $DB "SELECT IFNULL(MAX(id_reserva),0)+1 FROM reserva;"); if [ $IDRES -lt 90 ]; then IDRES=90; fi
docker exec tgs_db $DB "INSERT INTO reserva (id_reserva,cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES ($IDRES,'11111111',1,1,1,'$MARTES','10:00:00','Pendiente',2500.00,'pendiente');" > /dev/null
echo "reserva_prueba=$IDRES"

echo "== 1. Reprogramar a un horario libre (14:30) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&accion=reprogramar_reserva&nueva_fecha_reserva=$MARTES&nueva_hora_reserva=14:30&nuevo_id_taller=1" $BASE/administrar_reservas.php)
echo "$R" | grep -o "reprogramada correctamente[^<]*" | head -1
docker exec tgs_db $DB "SELECT fecha_reserva,hora_reserva,id_taller FROM reserva WHERE id_reserva=$IDRES;"

echo "== 2. Reprogramar a un domingo (debe rechazarse) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&accion=reprogramar_reserva&nueva_fecha_reserva=$DOMINGO&nueva_hora_reserva=14:30&nuevo_id_taller=1" $BASE/administrar_reservas.php)
echo "$R" | grep -o "No se puede reprogramar[^<]*" | head -1
echo "fecha_sin_cambios=$(docker exec tgs_db $DB "SELECT CONCAT(fecha_reserva,' ',hora_reserva) FROM reserva WHERE id_reserva=$IDRES;")"

echo "== 3. Reprogramar fuera de horario (20:00) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&accion=reprogramar_reserva&nueva_fecha_reserva=$MARTES&nueva_hora_reserva=20:00&nuevo_id_taller=1" $BASE/administrar_reservas.php)
echo "$R" | grep -o "No se puede reprogramar[^<]*" | head -1

echo "== 4. Reprogramar a una franja con la capacidad llena (2 de 2) =="
docker exec tgs_db $DB "INSERT INTO reserva (cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES ('22222222',2,1,1,'$MARTES','12:00:00','Pendiente',2500.00,'pendiente');" > /dev/null
docker exec tgs_db $DB "INSERT INTO reserva (cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES ('57779741',3,1,1,'$MARTES','12:00:00','Pendiente',2500.00,'pendiente');" > /dev/null
echo "ocupadas_en_12:00=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva WHERE id_taller=1 AND fecha_reserva='$MARTES' AND hora_reserva='12:00:00';") (capacidad 2)"
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&accion=reprogramar_reserva&nueva_fecha_reserva=$MARTES&nueva_hora_reserva=12:00&nuevo_id_taller=1" $BASE/administrar_reservas.php)
echo "$R" | grep -o "No se puede reprogramar[^<]*" | head -1
echo "sin_cambio=$(docker exec tgs_db $DB "SELECT hora_reserva FROM reserva WHERE id_reserva=$IDRES;")"

echo "== 5. Mover de sede (sede 2, que tiene hueco) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&accion=reprogramar_reserva&nueva_fecha_reserva=$MARTES&nueva_hora_reserva=16:30&nuevo_id_taller=2" $BASE/administrar_reservas.php)
echo "$R" | grep -o "reprogramada correctamente[^<]*" | head -1
docker exec tgs_db $DB "SELECT fecha_reserva,hora_reserva,id_taller FROM reserva WHERE id_reserva=$IDRES;"

echo "== 6. La reprogramacion queda en el historial =="
docker exec tgs_db $DB "SELECT campo,valor_anterior,valor_nuevo FROM reserva_historial WHERE id_reserva=$IDRES;"

echo "== 7. El cliente reprograma su turno (disponible) =="
JAR2=/tmp/jar_repro_cli.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$(tgs_token $BASE $JAR2 'reservas.php')&accion_editar_reserva=1&id_reserva=$IDRES&fecha_editar=$MARTES&hora_editar=11:30&id_vehiculo_editar=1&id_servicio_editar=1&id_taller_editar=2" $BASE/reservas.php)
echo "$R" | grep -o "Turno actualizado con éxito" | head -1
docker exec tgs_db $DB "SELECT fecha_reserva,hora_reserva,id_taller FROM reserva WHERE id_reserva=$IDRES;"

echo "== 8. El cliente intenta un horario no disponible =="
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$(tgs_token $BASE $JAR2 'reservas.php')&accion_editar_reserva=1&id_reserva=$IDRES&fecha_editar=$DOMINGO&hora_editar=11:30&id_vehiculo_editar=1&id_servicio_editar=1&id_taller_editar=2" $BASE/reservas.php)
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1
echo "sin_cambios=$(docker exec tgs_db $DB "SELECT CONCAT(fecha_reserva,' ',hora_reserva) FROM reserva WHERE id_reserva=$IDRES;")"

echo "== 9. El cliente no puede tocar una reserva ajena =="
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$(tgs_token $BASE $JAR2 'reservas.php')&accion_editar_reserva=1&id_reserva=999999&fecha_editar=$MARTES&hora_editar=11:30&id_vehiculo_editar=1&id_servicio_editar=1&id_taller_editar=2" $BASE/reservas.php)
echo "$R" | grep -o "Solo puedes editar tus propios turnos[^<]*" | head -1

echo "== 10. El formulario de reprogramar muestra el selector de sede =="
H=$(curl -s -c $JAR -b $JAR $BASE/administrar_reservas.php)
echo "$H" | grep -c 'name="nuevo_id_taller"' | sed 's/^/selector_sede_admin=/'
H2=$(curl -s -c $JAR2 -b $JAR2 $BASE/reservas.php)
echo "$H2" | grep -c 'name="id_taller_editar"' | sed 's/^/selector_sede_cliente=/'

echo "== 11. Limpieza =="
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva=$IDRES OR (id_taller=1 AND fecha_reserva='$MARTES' AND hora_reserva='12:00:00');" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva WHERE cedula_usuario IN ('22222222','57779741');" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva_historial WHERE id_reserva=$IDRES;" > /dev/null
docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;"
rm -f $JAR $JAR2
echo "FIN"
