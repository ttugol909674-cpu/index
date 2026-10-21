#!/bin/bash
# Pruebas de los gaps G1 a G5.
BASE="http://192.168.1.21:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
APASS="$TGS_PASS_ADMIN"
CLI="carlos.gomez@gmail.com"
CPASS="$TGS_PASS_CLIENTE"
JAR=/tmp/j_gaps.txt
fallos=0

echo "== 0. Migracion =="
docker exec -i tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base < /home/taller/Desktop/tgs/db/migracion.sql 2>&1 | grep -v Warning
docker exec tgs_db $DB "SHOW TABLES LIKE 'factura'; SHOW TABLES LIKE 'taller_marca'; SHOW TABLES LIKE 'taller_servicio'; SHOW COLUMNS FROM usuario LIKE 'saldo_prepago';"

echo
echo "== G1. Factura electronica =="
tgs_login $BASE $JAR "$ADMIN" "$APASS"
docker exec tgs_db $DB "DELETE FROM factura WHERE numero LIKE 'F-TEST%';" > /dev/null
# Turno de prueba con saldo pendiente (para reportar y aprobar un cobro)
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva=95;" > /dev/null
docker exec tgs_db $DB "INSERT INTO reserva (id_reserva,cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES (95,'11111111',1,1,1,'2026-12-10','09:00:00','Pendiente',2500.00,'pendiente');" > /dev/null
IDRES=95
# El cliente reporta un pago y el taller lo aprueba
tgs_login $BASE /tmp/j_gaps_cli.txt "$CLI" "$CPASS"
TOKEN=$(tgs_token $BASE /tmp/j_gaps_cli.txt "pagos_cliente.php")
curl -s -c /tmp/j_gaps_cli.txt -b /tmp/j_gaps_cli.txt -o /dev/null -d "csrf_token=$TOKEN&accion=reportar_pago&id_reserva=$IDRES&monto=500&metodo=transferencia&referencia=GAP-01" $BASE/pagos_cliente.php
PID=$(docker exec tgs_db $DB "SELECT id_pago FROM pago WHERE referencia='GAP-01';" 2>/dev/null)
echo "  pago reportado: id=$PID"
tgs_login $BASE $JAR "$ADMIN" "$APASS"
TOKEN=$(tgs_token $BASE $JAR "administrar_pagos.php")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=aprobar_pago&id_pago=$PID" $BASE/administrar_pagos.php)
echo "$R" | grep -q "aprobado" && echo "  G1 aprobar OK" || { echo "  G1 aprobar FALLO"; fallos=$((fallos+1)); }
echo "$R" | grep -q "Factura emitida" && echo "  G1 factura emitida OK" || { echo "  G1 factura emitida FALLO"; fallos=$((fallos+1)); }
FID=$(docker exec tgs_db $DB "SELECT id_factura FROM factura WHERE id_reserva=$IDRES;" 2>/dev/null)
NUM=$(docker exec tgs_db $DB "SELECT numero FROM factura WHERE id_factura=$FID;" 2>/dev/null)
echo "  factura: id=$FID numero=$NUM"
CODE=$(curl -s -c $JAR -b $JAR -o /tmp/fact.html -w "%{http_code}" "$BASE/factura.php?id=$FID")
echo "  factura.php -> HTTP $CODE"
grep -q "F-2026" /tmp/fact.html && echo "  G1 contenido OK" || { echo "  G1 contenido FALLO"; fallos=$((fallos+1)); }
grep -q "Descargable\|Descargar" /tmp/fact.html && echo "  G1 boton descargar OK" || { echo "  G1 boton FALLO"; fallos=$((fallos+1)); }

echo
echo "== G2. Historial de servicios =="
tgs_login $BASE /tmp/j_gaps_cli.txt "$CLI" "$CPASS"
CODE=$(curl -s -c /tmp/j_gaps_cli.txt -b /tmp/j_gaps_cli.txt -o /tmp/hist.html -w "%{http_code}" "$BASE/historial_cliente.php")
echo "  historial_cliente.php -> HTTP $CODE"
grep -q "Historial de servicios" /tmp/hist.html && echo "  G2 titulo OK" || { echo "  G2 titulo FALLO"; fallos=$((fallos+1)); }
grep -q "TGS Centro" /tmp/hist.html && echo "  G2 taller del servicio OK" || { echo "  G2 taller FALLO (sin datos)"; }
grep -q "Servicio realizado\|servicio" /tmp/hist.html && echo "  G2 detalle OK" || echo "  G2 sin detalle"
grep -q "Factura" /tmp/hist.html && echo "  G2 enlace a factura OK" || echo "  G2 sin enlace a factura"

echo
echo "== G3. Vista detallada de un taller =="
CODE=$(curl -s -o /tmp/tal.html -w "%{http_code}" "$BASE/taller.php?id=1")
echo "  taller.php?id=1 -> HTTP $CODE"
grep -q "TGS Centro" /tmp/tal.html && echo "  G3 datos del taller OK" || { echo "  G3 FALLO"; fallos=$((fallos+1)); }
grep -q "Marcas que atendemos\|Toyota" /tmp/tal.html && echo "  G3 marcas OK" || { echo "  G3 marcas FALLO"; fallos=$((fallos+1)); }
grep -q "Servicios que ofrecemos\|Mantenimiento" /tmp/tal.html && echo "  G3 servicios OK" || { echo "  G3 servicios FALLO"; fallos=$((fallos+1)); }
grep -q "Reservar un turno" /tmp/tal.html && echo "  G3 boton reservar OK" || { echo "  G3 boton FALLO"; fallos=$((fallos+1)); }
grep -q "Lugares libres" /tmp/tal.html && echo "  G3 disponibilidad OK" || { echo "  G3 disponibilidad FALLO"; fallos=$((fallos+1)); }

echo
echo "== G4. Filtros por marca y tipo de servicio =="
H=$(curl -s "$BASE/index.php")
echo "$H" | grep -q 'name="marca"' && echo "  G4 filtro marca en portada OK" || { echo "  G4 filtro marca FALLO"; fallos=$((fallos+1)); }
echo "$H" | grep -q 'name="servicio"' && echo "  G4 filtro servicio en portada OK" || { echo "  G4 filtro servicio FALLO"; fallos=$((fallos+1)); }
# Filtrar por Toyota: debe devolver la sede que la atiende
H2=$(curl -s "$BASE/index.php?marca=Toyota")
N=$(echo "$H2" | grep -c "taller.php?id=")
echo "  filtrando por Toyota -> $N sede(s)"
[ "$N" -ge 1 ] && echo "  G4 filtrado OK" || { echo "  G4 filtrado FALLO"; fallos=$((fallos+1)); }
# Filtrar por una marca que nadie atiende
H3=$(curl -s "$BASE/index.php?marca=Lamborghini")
N3=$(echo "$H3" | grep -c "taller.php?id=")
[ "$N3" = "0" ] && echo "  G4 filtro sin resultados OK" || { echo "  G4 filtro sin resultados FALLO ($N3)"; fallos=$((fallos+1)); }
# En el panel de talleres
tgs_login $BASE $JAR "$ADMIN" "$APASS"
H4=$(curl -s -c $JAR -b $JAR "$BASE/administrar_talleres.php")
echo "$H4" | grep -q 'name="marca"' && echo "  G4 filtro en panel OK" || { echo "  G4 filtro en panel FALLO"; fallos=$((fallos+1)); }
echo "$H4" | grep -q "Lugares libres hoy" && echo "  G4 ocupacion OK" || { echo "  G4 ocupacion FALLO"; fallos=$((fallos+1)); }

echo
echo "== G5. Pago directo con cuenta prepaga =="
docker exec tgs_db $DB "UPDATE usuario SET saldo_prepago=1000 WHERE cedula='11111111';" > /dev/null
SALDO=$(docker exec tgs_db $DB "SELECT saldo_prepago FROM usuario WHERE cedula='11111111';")
echo "  saldo prepago cargado: $SALDO"
tgs_login $BASE /tmp/j_gaps_cli.txt "$CLI" "$CPASS"
TOKEN=$(tgs_token $BASE /tmp/j_gaps_cli.txt "pagos_cliente.php")
# Se busca un turno con saldo pendiente
IDPEND=$(docker exec tgs_db $DB "SELECT r.id_reserva FROM reserva r WHERE r.cedula_usuario='11111111' AND r.estado<>'Cancelada' AND COALESCE(r.precio_total,0) > COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva=r.id_reserva AND p.estado='pagado'),0) LIMIT 1;" 2>/dev/null)
echo "  turno con saldo pendiente: $IDPEND"
if [ -n "$IDPEND" ]; then
  R=$(curl -s -c /tmp/j_gaps_cli.txt -b /tmp/j_gaps_cli.txt -d "csrf_token=$TOKEN&accion=pagar_prepago&id_reserva=$IDPEND&monto=100" $BASE/pagos_cliente.php)
  echo "$R" | grep -q "saldo prepago" && echo "  G5 pago prepago OK" || { echo "  G5 pago prepago FALLO"; fallos=$((fallos+1)); }
  SALDO2=$(docker exec tgs_db $DB "SELECT saldo_prepago FROM usuario WHERE cedula='11111111';")
  echo "  saldo despues: $SALDO2 (esperado 900)"
  [ "$SALDO2" = "900.00" ] && echo "  G5 descuento OK" || { echo "  G5 descuento FALLO"; fallos=$((fallos+1)); }
  # El metodo prepago debe existir entre los metodos de pago
  docker exec tgs_db $DB "SELECT metodo FROM pago WHERE id_reserva=$IDPEND ORDER BY id_pago DESC LIMIT 1;" | sed 's/^/  metodo: /'
  # Y no se puede pagar mas que el saldo
  R2=$(curl -s -c /tmp/j_gaps_cli.txt -b /tmp/j_gaps_cli.txt -d "csrf_token=$TOKEN&accion=pagar_prepago&id_reserva=$IDPEND&monto=99999" $BASE/pagos_cliente.php)
  echo "$R2" | grep -q "saldo pendiente\|No tenés saldo" && echo "  G5 limite de saldo OK" || { echo "  G5 limite FALLO"; fallos=$((fallos+1)); }
fi

echo
echo "== Limpieza =="
docker exec tgs_db $DB "DELETE FROM pago WHERE referencia IN ('GAP-01');" > /dev/null
docker exec tgs_db $DB "DELETE FROM factura WHERE numero LIKE 'F-%' AND id_reserva NOT IN (SELECT id_reserva FROM reserva WHERE id_reserva=2);" > /dev/null
docker exec tgs_db $DB "UPDATE usuario SET saldo_prepago=1500 WHERE cedula='11111111';" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva WHERE id_reserva=95;" > /dev/null
echo "listo"
echo "FALLOS_TOTALES=$fallos"
