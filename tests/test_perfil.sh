#!/bin/bash
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
EMAIL="carlos.gomez@gmail.com"
PASS="$TGS_PASS_CLIENTE"
JAR=/tmp/cookies_perfil.txt
rm -f $JAR

echo "== Login cliente =="
tgs_login $BASE $JAR "$EMAIL" "$PASS"
curl -s -c $JAR -b $JAR -o /dev/null -w "perfil_get=%{http_code}\n" $BASE/perfil.php

echo "== 0. Sin sesion no puede entrar al perfil =="
JAR0=/tmp/cookies_anon.txt
rm -f $JAR0
CODE0=$(curl -s -c $JAR0 -b $JAR0 -o /dev/null -w "%{http_code}" $BASE/perfil.php)
echo "perfil_anonimo=$CODE0"

echo "== 1. Token CSRF presente en el formulario =="
HTML=$(curl -s -c $JAR -b $JAR $BASE/perfil.php)
TOKEN=$(echo "$HTML" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | sed 's/.*value="//; s/"//')
if [ -n "$TOKEN" ]; then echo "TOKEN_PRESENTE_OK ($TOKEN)"; else echo "TOKEN_AUSENTE_FALLO"; fi

echo "== 2. Actualizar datos sin cambiar clave =="
R2=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&csrf_token=$TOKEN&nombre=Carlos Gomez Editado&email=carlos.gomez@gmail.com&telefono=+598 99 777 888" $BASE/perfil.php)
echo "$R2" | grep -q "Perfil actualizado exitosamente" && echo "PERFIL_OK" || echo "PERFIL_FALLO"
docker exec tgs_db $DB "SELECT nombre,email,telefono FROM usuario WHERE email='carlos.gomez@gmail.com';"

echo "== 3. POST sin token CSRF (debe rechazarse) =="
R3=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&nombre=Hacker Sin Token&email=carlos.gomez@gmail.com&telefono=+598 99 777 888" $BASE/perfil.php)
echo "$R3" | grep -q "sesión expiró" && echo "CSRF_RECHAZADO_OK" || echo "CSRF_RECHAZADO_FALLO"
docker exec tgs_db $DB "SELECT nombre FROM usuario WHERE email='carlos.gomez@gmail.com';"

echo "== 4. Token invalido (de otro usuario) =="
R4=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&csrf_token=0000000000000000000000000000000000000000000000000000000000000000&nombre=Hacker Token&email=carlos.gomez@gmail.com&telefono=+598 99 777 888" $BASE/perfil.php)
echo "$R4" | grep -q "sesión expiró" && echo "CSRF_TOKEN_INVALIDO_OK" || echo "CSRF_TOKEN_INVALIDO_FALLO"

echo "== 5. Email duplicado de otro usuario =="
R5=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&csrf_token=$TOKEN&nombre=Carlos Gomez Editado&email=admin@bluetech.com&telefono=+598 99 777 888" $BASE/perfil.php)
echo "$R5" | grep -q "ya está en uso por otro usuario" && echo "EMAIL_DUPLICADO_OK" || echo "EMAIL_DUPLICADO_FALLO"

echo "== 6. Telefono invalido =="
R6=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&csrf_token=$TOKEN&nombre=Carlos Gomez Editado&email=carlos.gomez@gmail.com&telefono=abc" $BASE/perfil.php)
echo "$R6" | grep -q "número de celular válido" && echo "TELEFONO_INVALIDO_OK" || echo "TELEFONO_INVALIDO_FALLO"

echo "== 7. Cambio de clave con clave actual incorrecta =="
R7=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&csrf_token=$TOKEN&nombre=Carlos Gomez Editado&email=carlos.gomez@gmail.com&telefono=+598 99 777 888&clave_actual=ClaveMala123&clave_nueva=$TGS_PASS_CLIENTE&clave_confirmar=$TGS_PASS_CLIENTE" $BASE/perfil.php)
echo "$R7" | grep -q "contraseña actual no es correcta" && echo "CLAVE_ACTUAL_OK" || echo "CLAVE_ACTUAL_FALLO"

echo "== 8. Confirmacion distinta =="
R8=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&csrf_token=$TOKEN&nombre=Carlos Gomez Editado&email=carlos.gomez@gmail.com&telefono=+598 99 777 888&clave_actual=$PASS&clave_nueva=$TGS_PASS_CLIENTE&clave_confirmar=OtraClave999" $BASE/perfil.php)
echo "$R8" | grep -q "no coinciden" && echo "CONFIRMACION_OK" || echo "CONFIRMACION_FALLO"

echo "== 9. Cambio de clave valido =="
HASH_ORIG=$(docker exec tgs_db $DB "SELECT password FROM usuario WHERE email='carlos.gomez@gmail.com';")
R9=$(curl -s -c $JAR -b $JAR -d "actualizar_perfil=1&csrf_token=$TOKEN&nombre=Carlos Gomez Editado&email=carlos.gomez@gmail.com&telefono=+598 99 777 888&clave_actual=$PASS&clave_nueva=$TGS_PASS_CLIENTE&clave_confirmar=$TGS_PASS_CLIENTE" $BASE/perfil.php)
echo "$R9" | grep -q "Contraseña actualizada exitosamente" && echo "CAMBIO_CLAVE_OK" || echo "CAMBIO_CLAVE_FALLO"
docker exec tgs_db $DB "SELECT LEFT(password,7) FROM usuario WHERE email='carlos.gomez@gmail.com';"

echo "== 10. Login con la nueva clave y con la anterior =="
JAR2=/tmp/cookies_nuevaclave.txt
rm -f $JAR2
curl -s -c $JAR2 -b $JAR2 -o /dev/null $BASE/login.php
C1=$(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" -d "csrf_token=$(tgs_token $BASE $JAR2 'login.php')&email=$EMAIL&password=$TGS_PASS_CLIENTE" $BASE/login.php)
echo "login_clave_nueva=$C1"
JAR3=/tmp/cookies_viejaclave.txt
rm -f $JAR3
curl -s -c $JAR3 -b $JAR3 -o /dev/null $BASE/login.php
C2=$(curl -s -c $JAR3 -b $JAR3 -o /dev/null -w "%{http_code}" -d "csrf_token=$(tgs_token $BASE $JAR3 'login.php')&email=$EMAIL&password=$PASS" $BASE/login.php)
echo "login_clave_vieja=$C2"

echo "== 11. Restaurar datos de prueba =="
docker exec tgs_db $DB "UPDATE usuario SET nombre='Carlos Gomez', telefono='+598 99 123 456', password='$HASH_ORIG' WHERE email='$EMAIL';" >/dev/null
docker exec tgs_db $DB "SELECT nombre,telefono,LEFT(password,7) FROM usuario WHERE email='$EMAIL';"
rm -f $JAR $JAR0 $JAR2 $JAR3
echo "FIN"
