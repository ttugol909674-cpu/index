#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh

login() {
  local jar=$1 email=$2 pass=$3
  rm -f $jar
  local token
  token=$(curl -s -c "$jar" -b "$jar" "$BASE/login.php" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
  curl -s -c "$jar" -b "$jar" -o /dev/null -d "csrf_token=$token&email=$email&password=$pass" "$BASE/login.php"
}

code() {
  curl -s -c $2 -b $2 -o /dev/null -w "%{http_code}" "$BASE/$1"
}

echo "== Accesos por rol (se espera 200 = permitido, 302 = bloqueado) =="
login /tmp/p_admin.txt "admin@bluetech.com" "$TGS_PASS_ADMIN"
login /tmp/p_gestor.txt "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR"
login /tmp/p_cliente.txt "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"

declare -A esperado
esperado[dashboard_admin.php]="200 302 302"
esperado[dashboard_gestor.php]="200 200 302"
esperado[panel_cliente.php]="302 302 200"
esperado[administrar_usuarios.php]="200 302 302"
esperado[administrar_talleres.php]="200 200 302"
esperado[administrar_servicios.php]="200 200 302"
esperado[administrar_reservas.php]="200 200 302"
esperado[administrar_pagos.php]="200 200 302"
esperado[administrar_presupuestos.php]="200 200 302"
esperado[agregar_taller.php]="200 200 302"
esperado[editar_taller.php]="302 302 302"
esperado[reservas.php]="200 200 200"
esperado[perfil.php]="200 200 200"
esperado[presupuestos.php]="200 200 200"
esperado[permisos.php]="200 302 302"
esperado[cancelar_reserva.php]="302 302 302"

fallos=0
for pagina in "${!esperado[@]}"; do
  a=$(code "$pagina" /tmp/p_admin.txt)
  g=$(code "$pagina" /tmp/p_gestor.txt)
  c=$(code "$pagina" /tmp/p_cliente.txt)
  esperado_pagina="${esperado[$pagina]}"
  echo "$pagina -> admin=$a gestor=$g cliente=$c (esperado: $esperado_pagina)"
  [ "$a $g $c" = "$esperado_pagina" ] || { echo "  DESAJUSTE en $pagina"; fallos=$((fallos+1)); }
done
echo "desajustes=$fallos"

echo "== editar_taller.php con id valido (sin id redirige siempre) =="
a=$(code "editar_taller.php?id=1" /tmp/p_admin.txt)
g=$(code "editar_taller.php?id=1" /tmp/p_gestor.txt)
c=$(code "editar_taller.php?id=1" /tmp/p_cliente.txt)
echo "editar_taller?id=1 -> admin=$a gestor=$g cliente=$c (esperado: 200 200 302)"
[ "$a $g $c" = "200 200 302" ] || { echo "  DESAJUSTE"; fallos=$((fallos+1)); }

echo "== Accesos sin sesion (todas deben redirigir: 302) =="
for pagina in dashboard_admin.php administrar_usuarios.php perfil.php presupuestos.php permisos.php administrar_pagos.php; do
  rm -f /tmp/p_anon.txt
  echo "$pagina -> $(code $pagina /tmp/p_anon.txt)"
done

echo "== Portada: botones segun rol =="
H=$(curl -s -c /tmp/p_admin.txt -b /tmp/p_admin.txt $BASE/index.php)
echo "$H" | grep -c "dashboard_admin.php" | sed 's/^/admin_boton_admin=/'
H=$(curl -s -c /tmp/p_gestor.txt -b /tmp/p_gestor.txt $BASE/index.php)
echo "$H" | grep -c "dashboard_admin.php" | sed 's/^/gestor_boton_admin=/'
echo "$H" | grep -c "dashboard_gestor.php" | sed 's/^/gestor_boton_gestor=/'
H=$(curl -s -c /tmp/p_cliente.txt -b /tmp/p_cliente.txt $BASE/index.php)
echo "$H" | grep -c "dashboard_gestor.php" | sed 's/^/cliente_boton_gestor=/'

echo "== Matriz de permisos visible para el admin =="
H=$(curl -s -c /tmp/p_admin.txt -b /tmp/p_admin.txt $BASE/permisos.php)
echo "$H" | grep -c "administrar_pagos" | sed 's/^/filas_matriz=/'
echo "$H" | grep -c "&#10003;" | sed 's/^/marcas_si=/'

echo "== El cliente no puede ver la matriz =="
echo "cliente_permisos=$(code permisos.php /tmp/p_cliente.txt)"

rm -f /tmp/p_admin.txt /tmp/p_gestor.txt /tmp/p_cliente.txt /tmp/p_anon.txt
echo "FIN"
