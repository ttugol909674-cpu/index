#!/bin/bash
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/cookies_admin.txt
rm -f $JAR

echo "== Login admin =="
tgs_login $BASE $JAR "$ADMIN" "$PASS"
curl -s -c $JAR -b $JAR -o /dev/null -w "administrar_usuarios_get=%{http_code}\n" $BASE/administrar_usuarios.php

echo "== 1. Alta valida (rol gestor) =="
R1=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&crear_usuario=1&nueva_cedula=99911122&nuevo_nombre=Usuario Prueba Admin&nuevo_email=prueba.admin@tgs.com&nuevo_telefono=+598 99 555 111&nuevo_rol=gestor&nueva_clave=Prueba1234" $BASE/administrar_usuarios.php)
echo "$R1" | grep -q "Usuario creado exitosamente" && echo "ALTA_OK" || echo "ALTA_FALLO"
docker exec tgs_db $DB "SELECT cedula,nombre,email,telefono,rol,LEFT(password,7) FROM usuario WHERE cedula='99911122';"

echo "== 2. Alta duplicada (misma cedula) =="
R2=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&crear_usuario=1&nueva_cedula=99911122&nuevo_nombre=Dup&nuevo_email=otro@tgs.com&nuevo_telefono=+598 99 555 222&nuevo_rol=cliente&nueva_clave=Prueba1234" $BASE/administrar_usuarios.php)
echo "$R2" | grep -q "Ya existe un usuario con esa cédula o correo electrónico" && echo "DUPLICADO_OK" || echo "DUPLICADO_FALLO"

echo "== 3. Email invalido =="
R3=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&crear_usuario=1&nueva_cedula=99933333&nuevo_nombre=Mail Malo&nuevo_email=correo-malo&nuevo_telefono=+598 99 555 333&nuevo_rol=cliente&nueva_clave=Prueba1234" $BASE/administrar_usuarios.php)
echo "$R3" | grep -q "correo electrónico no es válido" && echo "EMAIL_INVALIDO_OK" || echo "EMAIL_INVALIDO_FALLO"

echo "== 4. Telefono invalido =="
R4=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&crear_usuario=1&nueva_cedula=99944444&nuevo_nombre=Tel Malo&nuevo_email=tel@tgs.com&nuevo_telefono=abc&nuevo_rol=cliente&nueva_clave=Prueba1234" $BASE/administrar_usuarios.php)
echo "$R4" | grep -q "número de celular válido" && echo "TELEFONO_INVALIDO_OK" || echo "TELEFONO_INVALIDO_FALLO"

echo "== 5. Rol invalido =="
R5=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&crear_usuario=1&nueva_cedula=99955555&nuevo_nombre=Rol Malo&nuevo_email=rol@tgs.com&nuevo_telefono=+598 99 555 444&nuevo_rol=superusuario&nueva_clave=Prueba1234" $BASE/administrar_usuarios.php)
echo "$R5" | grep -q "rol seleccionado no es válido" && echo "ROL_INVALIDO_OK" || echo "ROL_INVALIDO_FALLO"

echo "== 6. Clave corta =="
R6=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&crear_usuario=1&nueva_cedula=99966666&nuevo_nombre=Clave Corta&nuevo_email=corta@tgs.com&nuevo_telefono=+598 99 555 555&nuevo_rol=cliente&nueva_clave=abc" $BASE/administrar_usuarios.php)
echo "$R6" | grep -q "al menos 8 caracteres" && echo "CLAVE_CORTA_OK" || echo "CLAVE_CORTA_FALLO"

echo "== 7. Alta con clave automatica =="
R7=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_usuarios.php')&crear_usuario=1&nueva_cedula=99977777&nuevo_nombre=Clave Auto&nuevo_email=auto@tgs.com&nuevo_telefono=+598 99 555 666&nuevo_rol=cliente&clave_automatica=1" $BASE/administrar_usuarios.php)
echo "$R7" | grep -q "Clave temporal" && echo "CLAVE_AUTO_MENSAJE_OK" || echo "CLAVE_AUTO_MENSAJE_FALLO"
CLAVE=$(echo "$R7" | grep -o 'Clave temporal: [^<]*' | sed 's/Clave temporal: //')
echo "clave_generada=$CLAVE"

echo "== 8. Login con la cuenta creada (clave automatica) =="
JAR2=/tmp/cookies_nuevo.txt
rm -f $JAR2
curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "login_get=%{http_code}\n" $BASE/login.php
CODE=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" -d "csrf_token=$(tgs_token $BASE $JAR2 'login.php')&email=auto@tgs.com&password=$CLAVE" $BASE/login.php)
echo "login_nuevo=$CODE"
curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "panel_cliente_get=%{http_code}\n" $BASE/panel_cliente.php

echo "== 9. Usuario no admin no puede entrar al panel de usuarios =="
JAR3=/tmp/cookies_gestor.txt
rm -f $JAR3
tgs_login $BASE $JAR3 "prueba.admin@tgs.com" "Prueba1234"
CODE3=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" $BASE/administrar_usuarios.php)
echo "acceso_no_admin=$CODE3"

echo "== Limpieza =="
docker exec tgs_db $DB "DELETE FROM usuario WHERE cedula IN ('99911122','99977777');" >/dev/null
docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario WHERE cedula IN ('99911122','99977777');"
rm -f $JAR $JAR2 $JAR3
echo "FIN"
