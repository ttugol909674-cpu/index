#!/bin/bash
# Verifica que el token CSRF se exija en todos los formularios POST y que
# las acciones legitimas sigan funcionando cuando el token es valido.
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
source /tmp/tgs_test_lib.sh

ADMIN="admin@bluetech.com"
APASS="$TGS_PASS_ADMIN"
CLI="carlos.gomez@gmail.com"
CPASS="$TGS_PASS_CLIENTE"

echo "== 1. Login sin token (debe fallar) =="
JAR=/tmp/j_c1.txt; rm -f $JAR
curl -s -c $JAR -b $JAR -o /dev/null $BASE/login.php
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" -d "email=$ADMIN&password=$APASS" $BASE/login.php)
echo "login_sin_token=$CODE (esperado 200 = la pagina de login se vuelve a mostrar, sin iniciar sesion)"

echo "== 2. Login con token (debe funcionar) =="
JAR=/tmp/j_c2.txt
tgs_login $BASE $JAR "$ADMIN" "$APASS"
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" $BASE/dashboard_admin.php)
echo "panel_admin=$CODE (esperado 200)"

echo "== 3. Registro sin token (debe rechazarse) =="
JAR3=/tmp/j_c3.txt; rm -f $JAR3
curl -s -c $JAR3 -b $JAR3 -o /dev/null $BASE/registro.php
curl -s -c $JAR3 -b $JAR3 -o /dev/null -d "pais=UY&cedula=55500011&nombre=Sin Token&email=sin.token@tgs.com&telefono=099111000&password=Prueba1234" $BASE/registro.php
echo "usuarios_creados=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE email='sin.token@tgs.com';") (esperado 0)"

echo "== 4. Registro con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR3 'login.php')
curl -s -c $JAR3 -b $JAR3 -o /dev/null -d "csrf_token=$TOKEN&pais=UY&cedula=55500011&nombre=Con Token&email=con.token@tgs.com&telefono=099111000&password=Prueba1234" $BASE/registro.php
echo "usuarios_creados=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE email='con.token@tgs.com';") (esperado 1)"
docker exec tgs_db $DB "DELETE FROM usuario WHERE email='con.token@tgs.com';" > /dev/null

echo "== 5. Reservar sin token (debe rechazarse) =="
JAR5=/tmp/j_c5.txt
tgs_login $BASE $JAR5 "$CLI" "$CPASS"
ANTES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;")
FECHA=$(date -d "+25 days" +%Y-%m-%d)
while [ $(date -d "$FECHA" +%u) -eq 7 ]; do FECHA=$(date -d "$FECHA + 1 day" +%Y-%m-%d); done
curl -s -c $JAR5 -b $JAR5 -o /dev/null -d "accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=1&fecha=$FECHA&hora=13:30" $BASE/reservas.php
DESPUES=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;")
echo "reservas antes=$ANTES despues=$DESPUES (esperado igual)"

echo "== 6. Reservar con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR5 'login.php')
curl -s -c $JAR5 -b $JAR5 -o /dev/null -d "csrf_token=$TOKEN&accion_reserva=1&id_servicio=1&id_vehiculo=1&id_taller=1&fecha=$FECHA&hora=13:30" $BASE/reservas.php
DESPUES2=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva;")
echo "reservas despues=$DESPUES2 (esperado +1)"
# (el id real se toma despues de crear la reserva)
echo "nueva_reserva=$NUEVA"
NUEVA=$(docker exec tgs_db $DB "SELECT MAX(id_reserva) FROM reserva;"); docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva=$NUEVA;" > /dev/null

echo "== 7. Editar vehiculo sin token (debe rechazarse) =="
ANTESV=$(docker exec tgs_db $DB "SELECT marca FROM vehiculo WHERE id_vehiculo=1;")
curl -s -c $JAR5 -b $JAR5 -o /dev/null -d "accion_editar_vehiculo=1&id_vehiculo_editar=1&edit_marca=HackSinToken&edit_modelo=X&edit_patente=Z" $BASE/reservas.php
DESPUESV=$(docker exec tgs_db $DB "SELECT marca FROM vehiculo WHERE id_vehiculo=1;")
echo "marca antes=$ANTESV despues=$DESPUESV (esperado igual)"

echo "== 8. Editar vehiculo con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR5 'login.php')
curl -s -c $JAR5 -b $JAR5 -o /dev/null -d "csrf_token=$TOKEN&accion_editar_vehiculo=1&id_vehiculo_editar=1&edit_marca=MarcaConToken&edit_modelo=ModeloToken&edit_patente=AA111" $BASE/reservas.php
echo "marca=$(docker exec tgs_db $DB "SELECT marca FROM vehiculo WHERE id_vehiculo=1;") (esperado MarcaConToken)"
docker exec tgs_db $DB "UPDATE vehiculo SET marca='Toyota', modelo='Corolla', patente='ABC1234' WHERE id_vehiculo=1;" > /dev/null

echo "== 9. Cancelar turno sin token (debe rechazarse) =="
IDRES=1
ANTESR=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva WHERE id_reserva=$IDRES;")
curl -s -c $JAR5 -b $JAR5 -o /dev/null -d "id_reserva=$IDRES" $BASE/cancelar_reserva.php
DESPUESR=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva WHERE id_reserva=$IDRES;")
echo "reserva antes=$ANTESR despues=$DESPUESR (esperado igual)"

echo "== 10. Alta de usuario sin token (debe rechazarse) =="
curl -s -c $JAR -b $JAR -o /dev/null -d "crear_usuario=1&nueva_cedula=77788899&nuevo_nombre=Sin Token&nuevo_email=sin.token.admin@tgs.com&nuevo_telefono=099222333&nuevo_rol=gestor&nueva_clave=Prueba1234" $BASE/administrar_usuarios.php
echo "usuarios=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE email='sin.token.admin@tgs.com';") (esperado 0)"

echo "== 11. Alta de usuario con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR 'login.php')
curl -s -c $JAR -b $JAR -o /dev/null -d "csrf_token=$TOKEN&crear_usuario=1&nueva_cedula=77788899&nuevo_nombre=Con Token&nuevo_email=con.token.admin@tgs.com&nuevo_telefono=099222333&nuevo_rol=gestor&nueva_clave=Prueba1234" $BASE/administrar_usuarios.php
echo "usuarios=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE email='con.token.admin@tgs.com';") (esperado 1)"
docker exec tgs_db $DB "DELETE FROM usuario WHERE email='con.token.admin@tgs.com';" > /dev/null

echo "== 12. Cambiar estado de reserva sin token (debe rechazarse) =="
ANTESEST=$(docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;")
curl -s -c $JAR -b $JAR -o /dev/null -d "id_reserva=$IDRES&estado_reserva=Finalizada" $BASE/administrar_reservas.php
DESPUESEST=$(docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;")
echo "estado antes=$ANTESEST despues=$DESPUESEST (esperado igual)"

echo "== 13. Cambiar estado con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR 'login.php')
curl -s -c $JAR -b $JAR -o /dev/null -d "csrf_token=$TOKEN&id_reserva=$IDRES&estado_reserva=En curso" $BASE/administrar_reservas.php
echo "estado=$(docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;") (esperado En curso)"
docker exec tgs_db $DB "UPDATE reserva SET estado='Pendiente' WHERE id_reserva=$IDRES;" > /dev/null

echo "== 14. Activar/desactivar servicio sin token (debe rechazarse) =="
ACT=$(docker exec tgs_db $DB "SELECT activo FROM servicio WHERE id_servicio=1;")
curl -s -c $JAR -b $JAR -o /dev/null -d "accion=estado&id_servicio=1" $BASE/administrar_servicios.php
echo "activo antes=$ACT despues=$(docker exec tgs_db $DB "SELECT activo FROM servicio WHERE id_servicio=1;") (esperado igual)"

echo "== 15. Estado de servicio con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR 'login.php')
curl -s -c $JAR -b $JAR -o /dev/null -d "csrf_token=$TOKEN&accion=estado&id_servicio=1" $BASE/administrar_servicios.php
echo "activo=$(docker exec tgs_db $DB "SELECT activo FROM servicio WHERE id_servicio=1;") (esperado invertido)"
docker exec tgs_db $DB "UPDATE servicio SET activo=1 WHERE id_servicio=1;" > /dev/null

echo "== 16. Crear taller sin token (debe rechazarse) =="
curl -s -c $JAR -b $JAR -o /dev/null -d "nombre=Taller Sin Token&telefono=099333444&email=sin.token.taller@tgs.com&direccion=X&capacidad=1" $BASE/agregar_taller.php
echo "talleres=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM taller WHERE email='sin.token.taller@tgs.com';") (esperado 0)"

echo "== 17. Crear taller con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR 'login.php')
curl -s -c $JAR -b $JAR -o /dev/null -d "csrf_token=$TOKEN&nombre=Taller Con Token&telefono=099333444&email=con.token.taller@tgs.com&direccion=X&capacidad=1&horario_activo[]=1&horario_desde[1]=09:00&horario_hasta[1]=17:00" $BASE/agregar_taller.php
echo "talleres=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM taller WHERE email='con.token.taller@tgs.com';") (esperado 1)"
docker exec tgs_db $DB "DELETE FROM taller WHERE email='con.token.taller@tgs.com';" > /dev/null

echo "== 18. Estado de reserva desde el panel del gestor sin token (debe rechazarse) =="
JAR18=/tmp/j_c18.txt
tgs_login $BASE $JAR18 "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR"
ANTESG=$(docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;")
curl -s -c $JAR18 -b $JAR18 -o /dev/null -d "id_reserva=$IDRES&estado_reserva=Finalizada" $BASE/dashboard_gestor.php
echo "estado antes=$ANTESG despues=$(docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;") (esperado igual)"

echo "== 19. Estado desde el panel del gestor con token (debe funcionar) =="
TOKEN=$(tgs_token $BASE $JAR18 'login.php')
curl -s -c $JAR18 -b $JAR18 -o /dev/null -d "csrf_token=$TOKEN&id_reserva=$IDRES&estado_reserva=En curso" $BASE/dashboard_gestor.php
echo "estado=$(docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=$IDRES;") (esperado En curso)"
docker exec tgs_db $DB "UPDATE reserva SET estado='Pendiente' WHERE id_reserva=$IDRES;" > /dev/null

echo "== 20. Limpieza =="
docker exec tgs_db $DB "SELECT COUNT(*) FROM reserva; SELECT COUNT(*) FROM usuario;"
rm -f /tmp/j_c*.txt
echo "FIN"
