#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
JAR=/tmp/jar_panel.txt
rm -f $JAR

echo "== Datos de prueba para el cliente =="
CED="11111111"
docker exec tgs_db $DB "DELETE FROM notificacion WHERE cedula_usuario='$CED';" > /dev/null
docker exec tgs_db $DB "INSERT INTO notificacion (id_notificacion,id_reserva,cedula_usuario,email_destino,asunto,cuerpo,tipo,estado) VALUES (95,1,'$CED','carlos.gomez@gmail.com','Tu vehiculo esta listo','Cuerpo de prueba','finalizacion','pendiente');" > /dev/null
docker exec tgs_db $DB "INSERT INTO presupuesto (id_presupuesto,id_reserva,cedula_usuario,fecha_presupuesto,total,estado,creado_por) VALUES (95,1,'$CED','2026-10-25',2750.00,'pendiente','00000000');" > /dev/null

echo "== 1. Login cliente =="
curl -s -c $JAR -b $JAR -o /dev/null $BASE/login.php
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" -d "csrf_token=$(tgs_token $BASE $JAR 'login.php')&email=carlos.gomez@gmail.com&password=$TGS_PASS_CLIENTE" $BASE/login.php)
echo "login=$CODE"

echo "== 2. El panel carga con los datos del cliente =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/panel_cliente.php)
echo "panel_cliente=$CODE (esperado 200)"
H=$(curl -s -c $JAR -b $JAR $BASE/panel_cliente.php)
echo "tarjetas_resumen=$(echo "$H" | grep -c 'tarjeta-resumen')"
echo "tabla_turnos=$(echo "$H" | grep -c 'Mantenimiento preventivo')"
echo "vehiculo_lista=$(echo "$H" | grep -c 'Toyota Corolla')"
echo "presupuesto_lista=$(echo "$H" | grep -c '2.750,00')"
echo "aviso_lista=$(echo "$H" | grep -c 'Tu vehiculo esta listo')"

echo "== 3. La columna de pago y el importe a pagar =="
echo "$H" | grep -o 'badge pendiente' | head -2
echo "$H" | grep -o '[0-9.,]* USD' | head -3

echo "== 4. Enlaces del panel =="
echo "$H" | grep -c 'href="reservas.php"' | sed 's/^/enlaces_reservas=/'
echo "$H" | grep -c 'href="presupuestos.php"' | sed 's/^/enlaces_presupuestos=/'
echo "$H" | grep -c 'href="perfil.php"' | sed 's/^/enlaces_perfil=/'
echo "$H" | grep -c 'reservas.php#mis-vehiculos' | sed 's/^/enlaces_ancla_vehiculos=/'

echo "== 5. El ancla existe en reservas.php =="
H2=$(curl -s -c $JAR -b $JAR $BASE/reservas.php)
echo "$H2" | grep -c 'id="mis-vehiculos"' | sed 's/^/ancla_vehiculos=/'

echo "== 6. El panel aparece en la portada y en reservas =="
H3=$(curl -s -c $JAR -b $JAR $BASE/index.php)
echo "$H3" | grep -c 'panel_cliente.php' | sed 's/^/portada_enlace_panel=/'
echo "$H2" | grep -c 'panel_cliente.php' | sed 's/^/reservas_enlace_panel=/'

echo "== 7. El admin y el gestor NO ven el panel del cliente =="
JAR2=/tmp/jar_panel_adm.txt
rm -f $JAR2
tgs_login $BASE $JAR2 "admin@bluetech.com" "$TGS_PASS_ADMIN"
echo "admin_panel_cliente=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" $BASE/panel_cliente.php) (esperado 302)"
JAR3=/tmp/jar_panel_ges.txt
rm -f $JAR3
tgs_login $BASE $JAR3 "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR"
echo "gestor_panel_cliente=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/panel_cliente.php) (esperado 302)"

echo "== 8. Sin sesion =="
echo "anonimo_panel_cliente=$(curl -s -o /dev/null -w "%{http_code}" $BASE/panel_cliente.php) (esperado 302)"

echo "== 9. El panel NO muestra datos de otros clientes =="
H4=$(curl -s -c $JAR -b $JAR $BASE/panel_cliente.php)
echo "$H4" | grep -c 'mariana.silva\|Mariana' | sed 's/^/datos_de_otro_cliente=/'

echo "== 10. Limpieza de datos de prueba =="
docker exec tgs_db $DB "DELETE FROM notificacion WHERE id_notificacion=95;" > /dev/null
docker exec tgs_db $DB "DELETE FROM presupuesto WHERE id_presupuesto=95;" > /dev/null
echo "limpio=ok"

rm -f $JAR $JAR2 $JAR3
echo "FIN"
