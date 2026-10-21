#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_notif.txt
rm -f $JAR

echo "== 1. Migracion (notificacion) =="
docker exec -i tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base < /home/taller/Desktop/tgs/db/migracion.sql 2>&1 | grep -v Warning
docker exec tgs_db $DB "SHOW TABLES LIKE 'notificacion';"

echo "== 2. Login admin =="
tgs_login $BASE $JAR "$ADMIN" "$PASS"
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/notificaciones.php)
echo "notificaciones_get=$CODE (esperado 200)"

IDRES=$(docker exec tgs_db $DB "SELECT id_reserva FROM reserva ORDER BY id_reserva LIMIT 1;")
echo "reserva=$IDRES"

echo "== 3. Marcar la reserva como Finalizada (debe generar aviso) =="
ANTES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&estado_reserva=Finalizada" $BASE/administrar_reservas.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
DESPUES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion;")
echo "notificaciones: antes=$ANTES despues=$DESPUES (esperado +1)"
docker exec tgs_db $DB "SELECT id_notificacion,id_reserva,email_destino,asunto,tipo,estado,respuesta_transporte,intentos FROM notificacion ORDER BY id_notificacion DESC LIMIT 3;"

echo "== 4. El cuerpo del aviso tiene los datos del turno =="
docker exec tgs_db $DB "SELECT cuerpo FROM notificacion ORDER BY id_notificacion DESC LIMIT 1;"

echo "== 5. Marcar de nuevo (otro aviso) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&estado_reserva=Finalizada" $BASE/administrar_reservas.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
echo "notificaciones=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion;")"

echo "== 6. Panel del gestor tambien avisa =="
JAR2=/tmp/jar_gestor_notif.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR"
ANTES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion;")
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$(tgs_token $BASE $JAR2 'dashboard_gestor.php')&id_reserva=$IDRES&estado_reserva=En curso" $BASE/dashboard_gestor.php)
DESPUES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion;")
echo "gestor cambios de estado: antes=$ANTES despues=$DESPUES (esperado igual, no es finalizacion)"

echo "== 7. Reenvio de un aviso pendiente =="
H=$(curl -s -c $JAR -b $JAR $BASE/notificaciones.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
PID=$(docker exec tgs_db $DB "SELECT id_notificacion FROM notificacion ORDER BY id_notificacion DESC LIMIT 1;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=reenviar&id_notificacion=$PID" $BASE/notificaciones.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
docker exec tgs_db $DB "SELECT id_notificacion,estado,intentos,respuesta_transporte FROM notificacion WHERE id_notificacion=$PID;"

echo "== 8. Filtro por estado =="
H=$(curl -s -c $JAR -b $JAR "$BASE/notificaciones.php?estado=pendiente")
echo "$H" | grep -c "badge pendiente" | sed 's/^/filas_pendiente=/'
H=$(curl -s -c $JAR -b $JAR "$BASE/notificaciones.php?estado=enviado")
echo "$H" | grep -c "badge enviado" | sed 's/^/filas_enviado=/'

echo "== 9. Sin CSRF no reenvia =="
ANTES=$(docker exec tgs_db $DB "SELECT SUM(intentos) FROM notificacion;")
R=$(curl -s -c $JAR -b $JAR -d "accion=reenviar&id_notificacion=$PID" $BASE/notificaciones.php)
echo "$R" | grep -q "sesión expiró" && echo "CSRF_OK" || echo "CSRF_FALLO"
DESPUES=$(docker exec tgs_db $DB "SELECT SUM(intentos) FROM notificacion;")
echo "intentos antes=$ANTES despues=$DESPUES (esperado igual)"

echo "== 10. El cliente no entra a la pantalla de avisos =="
JAR3=/tmp/jar_cli_notif.txt
rm -f $JAR3
tgs_login $BASE $JAR3 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
CODE=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/notificaciones.php)
echo "cliente_notificaciones=$CODE (esperado 302)"

echo "== 11. Limpieza =="
docker exec tgs_db $DB "DELETE FROM notificacion;" > /dev/null
docker exec tgs_db $DB "UPDATE reserva SET estado='Pendiente' WHERE id_reserva=$IDRES;" > /dev/null
echo "notificaciones=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion;") estado=$(docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;")"

rm -f $JAR $JAR2 $JAR3
echo "FIN"
