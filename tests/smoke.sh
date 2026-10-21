#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh

echo "== Login admin =="
JAR=/tmp/jar_smoke.txt
tgs_login $BASE $JAR "admin@bluetech.com" "$TGS_PASS_ADMIN"
for p in index.php dashboard_admin.php dashboard_gestor.php agenda_gestor.php administrar_reservas.php administrar_pagos.php administrar_presupuestos.php administrar_usuarios.php administrar_talleres.php agregar_taller.php permisos.php notificaciones.php "detalle_reserva.php?id=1"; do
  echo "$p -> $(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" "$BASE/$p")"
done
echo "== Cliente =="
JAR2=/tmp/jar_smoke2.txt
tgs_login $BASE $JAR2 "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE"
for p in index.php panel_cliente.php reservas.php perfil.php presupuestos.php; do
  echo "$p -> $(curl -s -c $JAR2 -b $JAR2 -o /dev/null -w "%{http_code}" "$BASE/$p")"
done
echo "== Disponibilidad (JSON) =="
curl -s -c $JAR2 -b $JAR2 "$BASE/disponibilidad.php?taller=1&fecha=2026-11-10" | head -c 130; echo
rm -f $JAR $JAR2
echo "FIN"
