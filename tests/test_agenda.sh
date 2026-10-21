#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_agenda.txt
rm -f $JAR
tgs_login $BASE $JAR "$ADMIN" "$PASS"

# Un martes futuro con varios turnos
MARTES=$(date -d "+20 days" +%Y-%m-%d)
while [ $(date -d "$MARTES" +%u) -ne 2 ]; do MARTES=$(date -d "$MARTES + 1 day" +%Y-%m-%d); done
echo "fecha_agenda=$MARTES"

echo "== 0. Turnos de prueba =="
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva >= 90;" > /dev/null
docker exec tgs_db $DB "INSERT INTO reserva (id_reserva,cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES (90,'11111111',1,1,1,'$MARTES','09:30:00','Pendiente',2500.00,'pendiente'),(91,'22222222',2,1,1,'$MARTES','12:00:00','En curso',1800.00,'parcial'),(92,'11111111',1,2,2,'$MARTES','10:30:00','Finalizada',3500.00,'pagado'),(93,'57779741',3,1,3,'$MARTES','09:30:00','Pendiente',3500.00,'pendiente');" > /dev/null
docker exec tgs_db $DB "SELECT id_reserva,id_taller,hora_reserva,estado FROM reserva ORDER BY id_reserva;"

echo "== 1. La agenda carga =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" "$BASE/agenda_gestor.php?fecha=$MARTES")
echo "agenda_get=$CODE (esperado 200)"

echo "== 2. La grilla muestra las sedes y las franjas =="
H=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=$MARTES")
echo "$H" | grep -c 'class="agenda"' | sed 's/^/tabla_agenda=/'
echo "$H" | grep -o 'TGS Centro' | head -1
echo "$H" | grep -o 'TGS Maldonado' | head -1
echo "franjas_encabezado=$(echo "$H" | grep -o '08:30' | head -1)"
echo "$H" | grep -o '17:30' | head -1 | sed 's/^/ultima_franja=/'

echo "== 3. Los turnos aparecen en su franja =="
echo "$H" | grep -c 'Carlos Gomez' | sed 's/^/turnos_carlos=/'
echo "$H" | grep -c 'Mariana Silva' | sed 's/^/turnos_mariana=/'
echo "$H" | grep -c 'Santiago Figueroa' | sed 's/^/turnos_santiago=/'
echo "$H" | grep -o 'Turnos totales' | head -1

echo "== 4. El detalle lista los turnos del dia =="
echo "$H" | grep -c '<tr>' | sed 's/^/filas_detalle=/'
echo "$H" | grep -o 'Mantenimiento preventivo' | head -1

echo "== 5. El resumen del dia =="
echo "$H" | grep -o 'Ocupación' | head -1
echo "$H" | grep -o 'Pendiente de cobro' | head -1

echo "== 6. Navegacion entre dias =="
ANTERIOR=$(date -d "$MARTES -1 day" +%Y-%m-%d)
H2=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=$ANTERIOR")
echo "$H2" | grep -o 'No hay turnos para este día' | head -1
H3=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=$MARTES")
echo "$H3" | grep -c "$MARTES" | sed 's/^/fecha_en_url=/'

echo "== 7. Un domingo (sedes cerradas) =="
DOMINGO=$(date -d "+20 days" +%Y-%m-%d)
while [ $(date -d "$DOMINGO" +%u) -ne 7 ]; do DOMINGO=$(date -d "$DOMINGO + 1 day" +%Y-%m-%d); done
H4=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=$DOMINGO")
echo "$H4" | grep -c 'Cerrado' | sed 's/^/cerrado=/'

echo "== 8. Fecha invalida -> hoy =="
H5=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=xx")
echo "$H5" | grep -o "value=\"$(date +%Y-%m-%d)\"" | head -1

echo "== 9. El cliente no puede ver la agenda =="
JAR2=/tmp/jar_agenda_cli.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
echo "cliente_agenda=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/agenda_gestor.php) (esperado 302)"

echo "== 10. Enlace desde el panel del gestor =="
H6=$(curl -s -c $JAR -b $JAR $BASE/dashboard_gestor.php)
echo "$H6" | grep -c 'agenda_gestor.php' | sed 's/^/enlace_agenda=/'

echo "== 11. Limpieza =="
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva >= 90;" > /dev/null
docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;"
rm -f $JAR $JAR2
echo "FIN"
