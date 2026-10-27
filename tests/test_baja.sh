#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_baja.txt
rm -f $JAR

echo "== 0. Crear un cliente de prueba con datos asociados =="
CED="99887766"
# Se borra la cuenta de prueba de la corrida anterior: si queda, el
# INSERT de abajo falla por cedula repetida y el test usa una clave vieja.
docker exec tgs_db $DB "DELETE FROM usuario WHERE cedula='$CED';" > /dev/null 2>&1
# El hash se genera en el momento con la clave de la cuenta de prueba: asi
# el test no depende de un hash fijo que queda viejo cuando se rotan las
# contrasenas de demostracion.
HASH="$(docker exec -e TGS_PASS="$TGS_PASS_CLIENTE" tgs_app php -r 'echo password_hash(getenv("TGS_PASS"), PASSWORD_DEFAULT);')"
docker exec tgs_db $DB "INSERT INTO usuario (cedula,nombre,apellido,email,telefono,rol,password) VALUES ('$CED','Prueba','Baja','baja.prueba@tgs.com','099111222','cliente','$HASH');" > /dev/null
docker exec tgs_db $DB "INSERT INTO vehiculo (id_vehiculo,cedula_usuario,patente,marca,modelo,anio) VALUES (90,'$CED','TEST99','Test','Baja',2020);" > /dev/null
docker exec tgs_db $DB "INSERT INTO reserva (id_reserva,cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES (90,'$CED',90,1,1,'2026-10-30','10:00:00','Pendiente',2500.00,'pendiente');" > /dev/null
docker exec tgs_db $DB "INSERT INTO pago (id_pago,id_reserva,monto,metodo,estado,referencia,registrado_por) VALUES (90,90,500.00,'efectivo','pagado','TEST90','00000000');" > /dev/null
docker exec tgs_db $DB "INSERT INTO presupuesto (id_presupuesto,id_reserva,cedula_usuario,fecha_presupuesto,total,estado,creado_por) VALUES (90,90,'$CED','2026-10-25',3000.00,'pendiente','00000000');" > /dev/null
docker exec tgs_db $DB "INSERT INTO notificacion (id_notificacion,id_reserva,cedula_usuario,email_destino,asunto,cuerpo,tipo,estado) VALUES (90,90,'$CED','baja.prueba@tgs.com','Aviso de prueba','Cuerpo de prueba','finalizacion','pendiente');" > /dev/null
echo "datos: vehiculo=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM vehiculo WHERE cedula_usuario='$CED';") reserva=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva WHERE cedula_usuario='$CED';") pago=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM pago WHERE id_reserva=90;") presupuesto=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM presupuesto_detalle WHERE id_presupuesto=90;") aviso=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion WHERE id_notificacion=90;")"

echo "== 1. Login del cliente de prueba =="
curl -s -c $JAR -b $JAR -o /dev/null $BASE/login.php
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" -d "csrf_token=$(tgs_token $BASE $JAR 'login.php')&email=baja.prueba@tgs.com&password=$TGS_PASS_CLIENTE" $BASE/login.php)
echo "login=$CODE (esperado 302)"

echo "== 2. La pantalla de baja muestra el resumen de datos =="
H=$(curl -s -c $JAR -b $JAR $BASE/eliminar_cuenta.php)
TOKEN=$(echo "$H" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
echo "$H" | grep -o 'class="lista-borrado"' | head -1
echo "resumen_vehiculo=$(echo "$H" | grep -A2 'Vehículo' | grep -o '<span>[0-9]*</span>' | head -1)"
[ -n "$TOKEN" ] && echo "TOKEN_OK" || echo "TOKEN_FALLO"

echo "== 3. Clave incorrecta =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=eliminar_cuenta&password=ClaveMala&confirmacion=ELIMINAR" $BASE/eliminar_cuenta.php)
echo "$R" | grep -o 'class="alert">[^<]*' | head -1
echo "usuario_vivo=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE cedula='$CED';") (esperado 1)"

echo "== 4. Confirmacion escrita mal =="
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=eliminar_cuenta&password=$TGS_PASS_CLIENTE&confirmacion=borrar" $BASE/eliminar_cuenta.php)
echo "$R" | grep -o 'class="alert">[^<]*' | head -1
echo "usuario_vivo=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE cedula='$CED';") (esperado 1)"

echo "== 5. Sin CSRF =="
R=$(curl -s -c $JAR -b $JAR -d "accion=eliminar_cuenta&password=$TGS_PASS_CLIENTE&confirmacion=ELIMINAR" $BASE/eliminar_cuenta.php)
echo "$R" | grep -o 'class="alert">[^<]*' | head -1
echo "usuario_vivo=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE cedula='$CED';") (esperado 1)"

echo "== 6. Baja valida =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" -d "csrf_token=$TOKEN&accion=eliminar_cuenta&password=$TGS_PASS_CLIENTE&confirmacion=ELIMINAR" $BASE/eliminar_cuenta.php)
echo "http=$CODE (esperado 302 hacia index.php)"

echo "== 7. Se borraron TODOS los datos =="
echo "usuario=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE cedula='$CED';") (esperado 0)"
echo "vehiculo=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM vehiculo WHERE cedula_usuario='$CED';") (esperado 0)"
echo "reserva=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva WHERE cedula_usuario='$CED';") (esperado 0)"
echo "pago=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM pago WHERE id_reserva=90;") (esperado 0)"
echo "presupuesto=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM presupuesto WHERE id_presupuesto=90;") (esperado 0)"
echo "detalle=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM presupuesto_detalle WHERE id_presupuesto=90;") (esperado 0)"
echo "notificacion=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM notificacion WHERE id_notificacion=90;") (esperado 0)"

echo "== 8. La sesion quedo cerrada =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/reservas.php)
echo "reservas_con_sesion_vieja=$CODE (esperado 302)"

echo "== 9. La portada muestra el aviso de cuenta eliminada =="
H=$(curl -s -c $JAR -b $JAR "$BASE/index.php?cuenta_eliminada=1")
echo "$H" | grep -q "aviso-cuenta" && echo "AVISO_OK" || echo "AVISO_FALLO"

echo "== 10. El admin NO puede usar la baja de cuenta =="
JAR2=/tmp/jar_baja_admin.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "$ADMIN" "$PASS"
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/eliminar_cuenta.php)
echo "admin_eliminar_cuenta=$CODE (esperado 302)"
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/administrar_usuarios.php)
echo "admin_administrar_usuarios=$CODE (esperado 200, sin cambios)"

echo "== 11. El gestor tampoco =="
JAR3=/tmp/jar_baja_gestor.txt
rm -f $JAR3
tgs_login $BASE $JAR3 "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR"
CODE=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/eliminar_cuenta.php)
echo "gestor_eliminar_cuenta=$CODE (esperado 302)"

echo "== 12. Datos de demo intactos =="
docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario;"
docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;"

rm -f $JAR $JAR2 $JAR3
echo "FIN"
