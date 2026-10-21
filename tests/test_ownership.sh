#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"

echo "== Preparacion: reserva + presupuesto de Mariana (otra cliente) =="
docker exec tgs_db $DB "INSERT INTO reserva (cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES ('22222222',2,1,1,'2026-10-21','10:00:00','Pendiente',2500.00,'pendiente');" > /dev/null
RID=$(docker exec tgs_db $DB "SELECT id_reserva FROM reserva WHERE cedula_usuario='22222222' ORDER BY id_reserva DESC LIMIT 1;")
docker exec tgs_db $DB "INSERT INTO presupuesto (id_reserva,cedula_usuario,fecha_presupuesto,total,estado,observaciones,creado_por) VALUES ($RID,'22222222','2026-10-19',3300.00,'pendiente','Presupuesto de Mariana','00000000');" > /dev/null
PID=$(docker exec tgs_db $DB "SELECT id_presupuesto FROM presupuesto WHERE cedula_usuario='22222222' ORDER BY id_presupuesto DESC LIMIT 1;")
echo "reserva_mariana=$RID presupuesto_mariana=$PID"

echo "== Carlos (otro cliente) intenta ACEPTAR el presupuesto de Mariana =="
JAR=/tmp/jc.txt
rm -f $JAR
tgs_login $BASE $JAR "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
# Carlos necesita un presupuesto propio para que su pagina tenga token CSRF
JAR2=/tmp/ja.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "admin@bluetech.com" "$TGS_PASS_ADMIN"
H=$(curl -s -c $JAR2 -b $JAR2 $BASE/administrar_presupuestos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
RID2=$(docker exec tgs_db $DB "SELECT id_reserva FROM reserva WHERE cedula_usuario='11111111' ORDER BY id_reserva DESC LIMIT 1;")
curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$TOKEN&accion=guardar_presupuesto&id_presupuesto=0&id_reserva=$RID2&fecha_presupuesto=2026-10-19&descripcion[]=Item de Carlos&cantidad[]=1&precio_unitario[]=900" $BASE/administrar_presupuestos.php > /dev/null
PIDC=$(docker exec tgs_db $DB "SELECT id_presupuesto FROM presupuesto WHERE cedula_usuario='11111111' ORDER BY id_presupuesto DESC LIMIT 1;")
echo "presupuesto_carlos=$PIDC presupuesto_mariana=$PID"

H=$(curl -s -c $JAR -b $JAR $BASE/presupuestos.php)
TOKENC=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
echo "token_carlos_obtenido=${TOKENC:0:12}..."
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKENC&accion=aceptar&id_presupuesto=$PID" $BASE/presupuestos.php)
echo "respuesta: $(echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1)"
echo "estado_presupuesto_mariana=$(docker exec tgs_db $DB "SELECT estado FROM presupuesto WHERE id_presupuesto=$PID;")"
echo "total_reserva_mariana=$(docker exec tgs_db $DB "SELECT precio_total FROM reserva WHERE id_reserva=$RID;")"

echo "== Limpieza =="
docker exec tgs_db $DB "DELETE FROM presupuesto WHERE id_presupuesto IN ($PID,$PIDC);" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva=$RID;" > /dev/null
docker exec tgs_db $DB "SELECT COUNT(*) FROM presupuesto; SELECT COUNT(*) FROM reserva;"
rm -f $JAR $JAR2
echo FIN
