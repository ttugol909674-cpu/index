#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_disp.txt
rm -f $JAR

echo "== 1. Migracion (taller_horario + capacidad) =="
docker exec -i tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base < /home/taller/Desktop/tgs/db/migracion.sql 2>&1 | grep -v Warning
docker exec tgs_db $DB "SHOW TABLES LIKE 'taller_horario';"
docker exec tgs_db $DB "SELECT id_taller,capacidad FROM taller;"
echo "horarios_por_taller:"
docker exec tgs_db $DB "SELECT id_taller,COUNT(*) FROM taller_horario GROUP BY id_taller;"

echo "== 2. Login cliente =="
tgs_login $BASE $JAR "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"

echo "== 3. Endpoint de disponibilidad =="
MARTES=$(date -d "+15 days" +%Y-%m-%d)
while [ $(date -d "$MARTES" +%u) -ne 2 ]; do MARTES=$(date -d "$MARTES + 1 day" +%Y-%m-%d); done
echo "martes=$MARTES"
R=$(curl -s -c $JAR -b $JAR "$BASE/disponibilidad.php?taller=1&fecha=$MARTES")
echo "respuesta: $R" | head -c 400; echo
echo "$R" | grep -q '"ok":true' && echo "JSON_OK" || echo "JSON_FALLO"

echo "== 4. Domingo (no atiende) =="
DOMINGO=$(date -d "+15 days" +%Y-%m-%d)
while [ $(date -d "$DOMINGO" +%u) -ne 7 ]; do DOMINGO=$(date -d "$DOMINGO + 1 day" +%Y-%m-%d); done
R=$(curl -s -c $JAR -b $JAR "$BASE/disponibilidad.php?taller=1&fecha=$DOMINGO")
echo "$R" | grep -o '"mensaje":"[^"]*"' | head -1
echo "$R" | grep -q '"ok":false' && echo "DOMINGO_CERRADO_OK" || echo "DOMINGO_CERRADO_FALLO"

echo "== 5. Fecha invalida =="
R=$(curl -s -c $JAR -b $JAR "$BASE/disponibilidad.php?taller=1&fecha=xx")
echo "$R" | grep -o '"mensaje":"[^"]*"' | head -1

echo "== 6. Sin sesion =="
R=$(curl -s "$BASE/disponibilidad.php?taller=1&fecha=$MARTES")
echo "$R" | grep -o '"mensaje":"[^"]*"' | head -1

echo "== 7. Reserva valida en un horario libre (sede 2) =="
ANTES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=2&fecha=$MARTES&hora=09:30" $BASE/reservas.php)
echo "$R" | grep -o "Turno reservado con éxito" | head -1
DESPUES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;")
echo "reservas antes=$ANTES despues=$DESPUES"
docker exec tgs_db $DB "SELECT id_reserva,id_taller,fecha_reserva,hora_reserva FROM reserva ORDER BY id_reserva DESC LIMIT 1;"

echo "== 8. Reserva en domingo (debe rechazarse) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=2&fecha=$DOMINGO&hora=10:00" $BASE/reservas.php)
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1

echo "== 9. Reserva fuera del horario (21:00) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=2&fecha=$MARTES&hora=21:00" $BASE/reservas.php)
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1

echo "== 10. Reserva antes de abrir (07:00) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=2&fecha=$MARTES&hora=07:00" $BASE/reservas.php)
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1

echo "== 11. Capacidad: la sede 2 tiene 2 lugares, el 3ro se rechaza =="
docker exec tgs_db $DB "INSERT INTO reserva (cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES ('22222222',2,2,1,'$MARTES','09:30:00','Pendiente',2500.00,'pendiente');" > /dev/null
R=$(curl -s -c $JAR -b $JAR "$BASE/disponibilidad.php?taller=2&fecha=$MARTES")
echo "$R" | grep -o '"ocupadas":\[[^]]*\]' | head -1
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=2&fecha=$MARTES&hora=09:30" $BASE/reservas.php)
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1
echo "reservas_en_la_franja=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva WHERE id_taller=2 AND fecha_reserva='$MARTES' AND hora_reserva='09:30:00';") (esperado 2)"

echo "== 12. Otra franja de la misma sede sigue libre =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=2&fecha=$MARTES&hora=14:30" $BASE/reservas.php)
echo "$R" | grep -o "Turno reservado con éxito" | head -1

echo "== 13. El formulario muestra el selector de sede =="
H=$(curl -s -c $JAR -b $JAR $BASE/reservas.php)
echo "$H" | grep -c 'name="id_taller"' | sed 's/^/selector_sede=/'
echo "$H" | grep -o 'TGS Centro' | head -1
echo "$H" | grep -o 'TGS Maldonado' | head -1

echo "== 14. Panel de talleres muestra horario y capacidad =="
JAR2=/tmp/jar_disp_admin.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "$ADMIN" "$PASS"
H=$(curl -s -c $JAR2 -b $JAR2 $BASE/administrar_talleres.php)
echo "$H" | grep -o "Capacidad simultánea" | head -1
echo "$H" | grep -o "Lun 08:30-18:30" | head -1

echo "== 15. Editar un taller cambia capacidad y horario =="
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$(tgs_token $BASE $JAR2 'editar_taller.php')&id_taller=2&nombre=TGS Maldonado&telefono=099654321&email=maldonado@tgs.com.uy&direccion=Peatonal Saravia 505&mapa=&capacidad=3&horario_activo[]=1&horario_activo[]=2&horario_desde[1]=09:00&horario_hasta[1]=17:00&horario_desde[2]=09:00&horario_hasta[2]=17:00" $BASE/editar_taller.php)
echo "$R" | grep -o "actualizados con éxito" | head -1
docker exec tgs_db $DB "SELECT id_taller,capacidad FROM taller WHERE id_taller=2;"
docker exec tgs_db $DB "SELECT dia_semana,hora_inicio,hora_fin FROM taller_horario WHERE id_taller=2 ORDER BY dia_semana;"

echo "== 16. Restaurar datos de demo =="
docker exec tgs_db $DB "UPDATE taller SET capacidad=2 WHERE id_taller IN (1,2);" > /dev/null
docker exec tgs_db $DB "DELETE FROM taller_horario;" > /dev/null
docker exec tgs_db $DB "INSERT INTO taller_horario (id_taller,dia_semana,hora_inicio,hora_fin,activo) SELECT t.id_taller, d.dia, '08:30:00','18:30:00',1 FROM taller t CROSS JOIN (SELECT 1 AS dia UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d;" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva > 1;" > /dev/null
docker exec tgs_db $DB "SELECT id_taller,capacidad FROM taller; SELECT COUNT(*) FROM reserva;"

rm -f $JAR $JAR2
echo "FIN"
