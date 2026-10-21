#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
JAR=/tmp/j_lang.txt
JAR2=/tmp/j_lang2.txt
fallos=0

# pagina | rol | texto ingles | texto espanol
PAGINAS=(
"index.php|anon|Book Appointment Now|Agendar Turno Ahora"
"login.php|anon|Sign In|Iniciar Sesión"
"registro.php|anon|Create Account|Crear Cuenta"
"reservas.php|cliente|Book Appointment|Reservar Turno"
"panel_cliente.php|cliente|Customer Panel|Panel de Cliente"
"perfil.php|cliente|My Profile|Mi Perfil"
"presupuestos.php|cliente|My Budgets|Mis Presupuestos"
"pagos_cliente.php|cliente|My payments|Mis pagos"
"eliminar_cuenta.php|cliente|Delete my account|Baja de mi cuenta"
"dashboard_admin.php|admin|Manage Payments|Administrar Pagos"
"dashboard_gestor.php|gestor|Workshop Agenda|Agenda de Talleres"
"agenda_gestor.php|gestor|Daily agenda|Agenda del día"
"detalle_reserva.php?id=1|admin|Appointment detail|Detalle del turno"
"administrar_reservas.php|admin|Reschedule|Reprogramar"
"administrar_pagos.php|admin|Register payment|Registrar cobro"
"administrar_presupuestos.php|admin|New budget|Nuevo presupuesto"
"administrar_usuarios.php|admin|Create User|Crear Usuario"
"administrar_talleres.php|admin|Simultaneous capacity|Capacidad simultánea"
"agregar_taller.php|admin|Add New Workshop|Añadir Nuevo Taller"
"editar_taller.php?id=1|admin|Edit Workshop|Editar Información"
"permisos.php|admin|Permissions by role|Permisos por rol"
"notificaciones.php|admin|Customer Notifications|Avisos al Cliente"
"datos_pago.php|admin|Bank details for payments|Datos bancarios para pagos"
)

login_para() {
  case $1 in
    admin)  tgs_login $BASE $JAR  "admin@bluetech.com" "$TGS_PASS_ADMIN" > /dev/null ;;
    gestor) tgs_login $BASE $JAR  "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR" > /dev/null ;;
    cliente) tgs_login $BASE $JAR "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE" > /dev/null ;;
  esac
}

# Construye la URL con el parametro lang bien puesto (? o &).
con_lang() {
  local pagina=$1 lang=$2
  if [[ "$pagina" == *"?"* ]]; then
    echo "$BASE/$pagina&lang=$lang"
  else
    echo "$BASE/$pagina?lang=$lang"
  fi
}

echo "== 1. Cada pagina muestra el texto del idioma pedido =="
for fila in "${PAGINAS[@]}"; do
  IFS='|' read -r pagina rol en es <<< "$fila"
  login_para "$rol"

  H_EN=$(curl -s -c $JAR -b $JAR "$(con_lang "$pagina" en)")
  H_ES=$(curl -s -c $JAR -b $JAR "$(con_lang "$pagina" es)")

  ok_en=0; ok_es=0; fuga_es=0; fuga_en=0
  echo "$H_EN" | grep -qF "$en" && ok_en=1
  echo "$H_ES" | grep -qF "$es" && ok_es=1
  echo "$H_EN" | grep -qF "$es" && fuga_es=1
  echo "$H_ES" | grep -qF "$en" && fuga_en=1

  # El atributo lang del html debe coincidir
  lang_attr=$(echo "$H_EN" | grep -o '<html lang="[a-z]*"' | head -1 | grep -o '"[a-z]*"' | tr -d '"')
  [ "$lang_attr" = "en" ] || { ok_en=0; }

  estado="OK"
  [ $ok_en -eq 0 -o $ok_es -eq 0 -o $fuga_es -eq 1 -o $fuga_en -eq 1 ] && { estado="REVISAR"; fallos=$((fallos+1)); }
  printf "%-30s en:%s es:%s fuga_es:%s fuga_en:%s lang=%s  %s\n" "$pagina" $ok_en $ok_es $fuga_es $fuga_en "$lang_attr" "$estado"
done

echo
echo "== 2. El idioma se mantiene al navegar =="
tgs_login $BASE $JAR "admin@bluetech.com" "$TGS_PASS_ADMIN" > /dev/null
curl -s -c $JAR -b $JAR -o /dev/null "$BASE/administrar_pagos.php?lang=en"
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_talleres.php")
echo "$H" | grep -qF "Simultaneous capacity" && echo "PERSISTENCIA_EN_OK" || { echo "PERSISTENCIA_EN_FALLO"; fallos=$((fallos+1)); }
curl -s -c $JAR -b $JAR -o /dev/null "$BASE/administrar_pagos.php?lang=es"
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_talleres.php")
echo "$H" | grep -qF "Capacidad" && echo "PERSISTENCIA_ES_OK" || { echo "PERSISTENCIA_ES_FALLO"; fallos=$((fallos+1)); }

echo
echo "== 3. El selector ES/EN esta en todas las paginas =="
for fila in "${PAGINAS[@]}"; do
  IFS='|' read -r pagina rol en es <<< "$fila"
  case $rol in
    admin)  tgs_login $BASE $JAR2 "admin@bluetech.com" "$TGS_PASS_ADMIN" > /dev/null ;;
    gestor) tgs_login $BASE $JAR2 "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR" > /dev/null ;;
    cliente) tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE" > /dev/null ;;
  esac
  H=$(curl -s -c $JAR2 -b $JAR2 "$BASE/$pagina")
  echo "$H" | grep -q "lang=en" || { echo "  SIN SELECTOR EN: $pagina"; fallos=$((fallos+1)); }
done
echo "  (sin lineas = todas tienen selector)"

echo
echo "== 4. El idioma sobrevive al login (no vuelve a espanol) =="
JAR3=/tmp/j_lang3.txt
rm -f $JAR3
curl -s -c $JAR3 -b $JAR3 -o /dev/null "$BASE/login.php?lang=en"
TOKEN=$(tgs_token $BASE $JAR3 "login.php")
curl -s -c $JAR3 -b $JAR3 -o /dev/null -d "csrf_token=$TOKEN&email=admin@bluetech.com&password=$TGS_PASS_ADMIN" $BASE/login.php
H=$(curl -s -c $JAR3 -b $JAR3 "$BASE/administrar_pagos.php")
echo "$H" | grep -qF "Register payment" && echo "IDIOMA_POST_LOGIN_OK" || { echo "IDIOMA_POST_LOGIN_FALLO"; fallos=$((fallos+1)); }
rm -f $JAR3

echo
echo "== 5. Un idioma invalido cae en espanol =="
tgs_login $BASE $JAR "admin@bluetech.com" "$TGS_PASS_ADMIN" > /dev/null
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_talleres.php?lang=xx")
echo "$H" | grep -qF "Capacidad" && echo "IDIOMA_INVALIDO_OK" || { echo "IDIOMA_INVALIDO_FALLO"; fallos=$((fallos+1)); }

echo
echo "TOTAL_FALLOS=$fallos"
rm -f $JAR $JAR2
