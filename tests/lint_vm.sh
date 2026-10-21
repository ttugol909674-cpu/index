#!/bin/bash
fallos=0
for f in auth index reservas cancelar_reserva administrar_usuarios administrar_talleres administrar_reservas administrar_servicios administrar_pagos administrar_presupuestos agregar_taller editar_taller dashboard_admin dashboard_gestor panel_cliente perfil presupuestos permisos; do
  salida=$(docker exec tgs_app php -l /var/www/html/$f.php 2>&1)
  if ! echo "$salida" | grep -q "No syntax errors"; then
    echo "FALLO $f.php: $salida"
    fallos=$((fallos+1))
  fi
done
echo "LINT_FALLOS=$fallos"
