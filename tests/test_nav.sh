#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh

check_nav() {
    local email=$1 pass=$2 pagina=$3 etiqueta=$4
    local jar=/tmp/jar_nav_$etiqueta.txt
    rm -f $jar
    curl -s -c $jar -b $jar -o /dev/null $BASE/login.php
    curl -s -c $jar -b $jar -o /dev/null -d "csrf_token=$(tgs_token $BASE $jar 'login.php')&email=$email&password=$pass" $BASE/login.php
    local code=$(curl -s -c $jar -b $jar -o /dev/null -w "%{http_code}" $BASE/$pagina)
    local html=$(curl -s -c $jar -b $jar $BASE/$pagina)
    local con=$(echo "$html" | grep -c 'perfil.php')
    echo "$etiqueta $pagina http=$code enlaces_perfil=$con"
    rm -f $jar
}

check_nav "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE" "reservas.php" "cliente"
check_nav "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR" "dashboard_gestor.php" "gestor"
check_nav "admin@bluetech.com" "$TGS_PASS_ADMIN" "dashboard_admin.php" "admin"
check_nav "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR" "perfil.php" "perfil_gestor"
check_nav "admin@bluetech.com" "$TGS_PASS_ADMIN" "perfil.php" "perfil_admin"
check_nav "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE" "perfil.php" "perfil_cliente"
check_nav "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE" "perfil.php?lang=en" "perfil_en"
echo "FIN"
