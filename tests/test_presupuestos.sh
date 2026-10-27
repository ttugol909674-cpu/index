#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/cookies_pres.txt
rm -f $JAR

echo "== 1. Migracion (presupuestos) =="
docker exec -i tgs_db mysql -uroot -p$TGS_DB_ROOT_PASS bluetech_base < /home/taller/Desktop/tgs/db/migracion.sql 2>&1 | grep -v Warning
docker exec tgs_db $DB "SHOW TABLES LIKE 'presupuesto%';"

echo "== 2. Login admin =="
tgs_login $BASE $JAR "$ADMIN" "$PASS"

echo "== 3. Pagina de presupuestos carga =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/administrar_presupuestos.php)
echo "administrar_presupuestos_get=$CODE"
H=$(curl -s -c $JAR -b $JAR $BASE/administrar_presupuestos.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
[ -n "$TOKEN" ] && echo "TOKEN_OK" || echo "TOKEN_FALLO"

IDRES=$(docker exec tgs_db $DB "SELECT id_reserva FROM reserva ORDER BY id_reserva LIMIT 1;")

echo "== 4. Crear presupuesto con 2 items =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_presupuesto&id_presupuesto=0&id_reserva=$IDRES&fecha_presupuesto=2026-10-19&fecha_vencimiento=2026-10-26&observaciones=Prueba automatica&descripcion[]=Cambio de aceite&descripcion[]=Filtro de aire&cantidad[]=1&cantidad[]=2&precio_unitario[]=1000&precio_unitario[]=500" $BASE/administrar_presupuestos.php)
echo "$R" | grep -q "Presupuesto guardado correctamente" && echo "GUARDAR_OK" || echo "GUARDAR_FALLO"
docker exec tgs_db $DB "SELECT id_presupuesto,id_reserva,cedula_usuario,total,estado FROM presupuesto ORDER BY id_presupuesto DESC LIMIT 1;"
docker exec tgs_db $DB "SELECT descripcion,cantidad,precio_unitario,subtotal FROM presupuesto_detalle WHERE id_presupuesto=(SELECT MAX(id_presupuesto) FROM presupuesto);"

echo "== 5. Sin items (debe rechazar) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_presupuesto&id_presupuesto=0&id_reserva=$IDRES&fecha_presupuesto=2026-10-19&descripcion[]=&cantidad[]=1&precio_unitario[]=100" $BASE/administrar_presupuestos.php)
echo "$R" | grep -q "al menos un item" && echo "SIN_ITEMS_OK" || echo "SIN_ITEMS_FALLO"

echo "== 6. Cantidad invalida =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_presupuesto&id_presupuesto=0&id_reserva=$IDRES&fecha_presupuesto=2026-10-19&descripcion[]=Item&cantidad[]=0&precio_unitario[]=100" $BASE/administrar_presupuestos.php)
echo "$R" | grep -q "mayores a cero" && echo "CANTIDAD_OK" || echo "CANTIDAD_FALLO"

echo "== 7. Precio negativo =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_presupuesto&id_presupuesto=0&id_reserva=$IDRES&fecha_presupuesto=2026-10-19&descripcion[]=Item&cantidad[]=1&precio_unitario[]=-50" $BASE/administrar_presupuestos.php)
echo "$R" | grep -q "no pueden ser negativos" && echo "PRECIO_OK" || echo "PRECIO_FALLO"

echo "== 8. Sin CSRF =="
R=$(curl -s -c $JAR -b $JAR -d "accion=guardar_presupuesto&id_presupuesto=0&id_reserva=$IDRES&fecha_presupuesto=2026-10-19&descripcion[]=Hack&cantidad[]=1&precio_unitario[]=1" $BASE/administrar_presupuestos.php)
echo "$R" | grep -q "sesión expiró" && echo "CSRF_OK" || echo "CSRF_FALLO"

echo "== 9. Editar el presupuesto (reemplaza los items) =="
PID=$(docker exec tgs_db $DB "SELECT MAX(id_presupuesto) FROM presupuesto;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_presupuesto&id_presupuesto=$PID&id_reserva=$IDRES&fecha_presupuesto=2026-10-19&observaciones=Editado&descripcion[]=Solo un item&cantidad[]=3&precio_unitario[]=700" $BASE/administrar_presupuestos.php)
echo "$R" | grep -q "Presupuesto guardado correctamente" && echo "EDITAR_OK" || echo "EDITAR_FALLO"
docker exec tgs_db $DB "SELECT total,observaciones FROM presupuesto WHERE id_presupuesto=$PID;"
docker exec tgs_db $DB "SELECT COUNT(*),SUM(subtotal) FROM presupuesto_detalle WHERE id_presupuesto=$PID;"

echo "== 10. El cliente ve el presupuesto =="
JAR2=/tmp/cookies_cli3.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/presupuestos.php)
echo "presupuestos_cliente=$CODE"
H=$(curl -s -c $JAR2 -b $JAR2 $BASE/presupuestos.php)
echo "$H" | grep -q "Solo un item" && echo "ITEM_VISIBLE_OK" || echo "ITEM_VISIBLE_FALLO"
TOKEN2=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')

echo "== 11. El cliente acepta el presupuesto =="
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$TOKEN2&accion=aceptar&id_presupuesto=$PID" $BASE/presupuestos.php)
echo "$R" | grep -q "Presupuesto aceptado" && echo "ACEPTAR_OK" || echo "ACEPTAR_FALLO"
docker exec tgs_db $DB "SELECT estado,fecha_respuesta,respondido_por FROM presupuesto WHERE id_presupuesto=$PID;"
docker exec tgs_db $DB "SELECT precio_total,estado_pago FROM reserva WHERE id_reserva=$IDRES;"

echo "== 12. Aceptar de nuevo (debe rechazar) =="
R=$(curl -s -c $JAR2 -b $JAR2 -d "csrf_token=$TOKEN2&accion=aceptar&id_presupuesto=$PID" $BASE/presupuestos.php)
echo "$R" | grep -q "ya tiene una respuesta" && echo "DOBLE_RESPUESTA_OK" || echo "DOBLE_RESPUESTA_FALLO"

echo "== 13. Editar un presupuesto ya aceptado (debe rechazar) =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=guardar_presupuesto&id_presupuesto=$PID&id_reserva=$IDRES&fecha_presupuesto=2026-10-19&descripcion[]=Cambio&cantidad[]=1&precio_unitario[]=1" $BASE/administrar_presupuestos.php)
echo "DEBUG_alert: $(echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1)"
echo "DEBUG_longitud_respuesta: ${#R}"
echo "DEBUG_estado_presupuesto: $(docker exec tgs_db $DB "SELECT estado FROM presupuesto WHERE id_presupuesto=$PID;")"
echo "DEBUG_items: $(docker exec tgs_db $DB "SELECT GROUP_CONCAT(descripcion) FROM presupuesto_detalle WHERE id_presupuesto=$PID;")"
echo "$R" | grep -q "presupuestos pendientes" && echo "EDITAR_ACEPTADO_OK" || echo "EDITAR_ACEPTADO_FALLO"

echo "== 14. El cliente no puede editar el presupuesto (redirige a 403) =="
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/administrar_presupuestos.php)
echo "cliente_en_administrar_presupuestos=$CODE"
[ "$CODE" = "302" ] && echo "CLIENTE_BLOQUEADO_OK" || echo "CLIENTE_BLOQUEADO_FALLO"

echo "== 15. El cliente no puede ver el presupuesto de otro =="
JAR3=/tmp/cookies_cli4.txt
rm -f $JAR3
tgs_login $BASE $JAR3 "mariana.silva@gmail.com" "$TGS_PASS_CLIENTE"
H=$(curl -s -c $JAR3 -b $JAR3 $BASE/presupuestos.php)
echo "$H" | grep -q "Solo un item" && echo "OTRO_CLIENTE_VE_FALLO" || echo "OTRO_CLIENTE_NO_VE_OK"
TOKEN3=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
R=$(curl -s -c $JAR3 -b $JAR3 -d "csrf_token=$TOKEN3&accion=aceptar&id_presupuesto=$PID" $BASE/presupuestos.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
ESTADO=$(docker exec tgs_db $DB "SELECT estado FROM presupuesto WHERE id_presupuesto=$PID;")
echo "estado_tras_intento_de_otro_cliente=$ESTADO"

echo "== 16. Limpieza =="
docker exec tgs_db $DB "DELETE FROM presupuesto WHERE id_presupuesto=$PID;" > /dev/null
docker exec tgs_db $DB "SELECT COUNT(*) FROM presupuesto;"

rm -f $JAR $JAR2 $JAR3
echo "FIN"
