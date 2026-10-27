#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_alta_taller.txt
rm -f $JAR
tgs_login $BASE $JAR "$ADMIN" "$PASS"

echo "== 1. Crear un taller con capacidad y horario =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'agregar_taller.php')&nombre=Taller de Prueba&telefono=099555444&email=prueba@tgs.com&direccion=Calle Falsa 123&mapa=&capacidad=4&horario_activo[]=1&horario_activo[]=3&horario_activo[]=5&horario_desde[1]=10:00&horario_hasta[1]=16:00&horario_desde[3]=10:00&horario_hasta[3]=16:00&horario_desde[5]=10:00&horario_hasta[5]=16:00" $BASE/agregar_taller.php)
echo "$R" | grep -o "Taller añadido con éxito" | head -1
docker exec tgs_db $DB "SELECT id_taller,nombre,capacidad FROM taller WHERE nombre='Taller de Prueba';"
NID=$(docker exec tgs_db $DB "SELECT id_taller FROM taller WHERE nombre='Taller de Prueba';")
echo "horarios:"
docker exec tgs_db $DB "SELECT dia_semana,hora_inicio,hora_fin FROM taller_horario WHERE id_taller=$NID ORDER BY dia_semana;"

echo "== 2. El taller nuevo aparece en el selector de sede =="
JAR2=/tmp/jar_alta_cli.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
H=$(curl -s -c $JAR2 -b $JAR2 $BASE/reservas.php)
echo "$H" | grep -c "Taller de Prueba" | sed 's/^/en_selector=/'

echo "== 3. Se puede reservar en el taller nuevo un dia que abre =="
# Dia 1 = lunes. Buscamos un lunes futuro.
LUNES=$(date -d "+15 days" +%Y-%m-%d)
while [ $(date -d "$LUNES" +%u) -ne 1 ]; do LUNES=$(date -d "$LUNES + 1 day" +%Y-%m-%d); done
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$(tgs_token $BASE $JAR2 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=$NID&fecha=$LUNES&hora=11:00" $BASE/reservas.php)
echo "$R" | grep -o "Turno reservado con éxito" | head -1
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1

echo "== 4. Un dia que no abre (martes) se rechaza =="
MARTES=$(date -d "+15 days" +%Y-%m-%d)
while [ $(date -d "$MARTES" +%u) -ne 2 ]; do MARTES=$(date -d "$MARTES + 1 day" +%Y-%m-%d); done
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$(tgs_token $BASE $JAR2 'reservas.php')&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=$NID&fecha=$MARTES&hora=11:00" $BASE/reservas.php)
echo "$R" | grep -o "Ese horario no está disponible[^<]*" | head -1

echo "== 5. Horario invalido (cierre antes de apertura) =="
R=$(curl -s -c $JAR -b $JAR -d "nombre=Taller Horario Malo&telefono=1&email=x@tgs.com&direccion=x&mapa=&capacidad=1&horario_activo[]=1&horario_desde[1]=18:00&horario_hasta[1]=09:00" $BASE/agregar_taller.php)
echo "$R" | grep -o "La hora de cierre debe ser posterior a la de apertura." | head -1
echo "talleres_creados=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM taller WHERE nombre='Taller Horario Malo';") (esperado 0)"

echo "== 6. Limpieza =="
docker exec tgs_db $DB "DELETE FROM taller WHERE nombre IN ('Taller de Prueba','Taller Horario Malo');" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_taller=$NID;" > /dev/null
docker exec tgs_db $DB "SELECT id_taller,nombre,capacidad FROM taller;"

rm -f $JAR $JAR2
echo "FIN"
