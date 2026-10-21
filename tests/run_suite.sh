#!/bin/bash
# Corre toda la suite restaurando los datos de demostracion antes de cada
# test: asi los tests no se pisan entre si ni borran los datos de ejemplo.
#
# Los tests se ejecutan desde /tmp (nunca desde la carpeta del proyecto):
# asi ningun archivo temporal puede pisar un archivo del codigo.
source /tmp/tgs_test_lib.sh
cd /tmp || exit 1
fallos=0
for t in test_csrf test_editar test_alta test_perfil test_permisos test_disp test_repro test_alta_taller test_smtp test_pagos test_presupuestos test_notificaciones test_matriz test_fallback test_baja test_panel test_agenda test_detalle test_modal test_nav test_filtros test_filtros2 test_filtros3 test_pagos2 test_ownership test_a_pagar test_pago_cliente test_idiomas; do
  bash /tmp/restore_demo_full.sh > /dev/null 2>&1
  out=$(bash /tmp/$t.sh 2>&1)
  if echo "$out" | grep "FALLO\|DESAJUSTE" | grep -qv "TOTAL_FALLOS=0\|desajustes=0"; then
    echo "$t -> REVISAR"
    echo "$out" | grep "FALLO\|DESAJUSTE" | head -3 | sed 's/^/     /'
    fallos=$((fallos+1))
  else
    echo "$t -> OK"
  fi
done
echo "SUITE_FALLOS=$fallos"
