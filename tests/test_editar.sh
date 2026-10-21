#!/bin/bash
EMAIL="carlos.gomez@gmail.com"
PASS="$TGS_PASS_CLIENTE"
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"

echo "== Vehiculo del cliente =="
VEH=$(docker exec tgs_db $DB "SELECT v.id_vehiculo, v.patente, v.marca, v.modelo, v.anio FROM vehiculo v JOIN usuario u ON v.cedula_usuario=u.cedula WHERE u.email='$EMAIL' LIMIT 1;")
echo "$VEH"
ID=$(echo "$VEH" | awk '{print $1}')
if [ -z "$ID" ]; then echo "SIN_VEHICULOS"; exit 1; fi

OTRO=$(docker exec tgs_db $DB "SELECT v.id_vehiculo FROM vehiculo v JOIN usuario u ON v.cedula_usuario=u.cedula WHERE u.email<>'$EMAIL' LIMIT 1;")

BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
JAR=/tmp/cookies_tgs.txt
rm -f $JAR

tgs_login $BASE $JAR "$EMAIL" "$PASS"
curl -s -c $JAR -b $JAR -o /dev/null -w "reservas_get=%{http_code}\n" $BASE/reservas.php

echo "== Edicion legitima (id=$ID) =="
RESP=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_editar_vehiculo=1&id_vehiculo_editar=$ID&edit_marca=TestMarca&edit_modelo=TestModelo&edit_anio=2020&edit_patente=TEST123" $BASE/reservas.php)
echo "$RESP" | grep -q "Vehículo actualizado con éxito" && echo "MENSAJE_EXITO_OK" || echo "SIN_MENSAJE_EXITO"
docker exec tgs_db $DB "SELECT id_vehiculo,patente,marca,modelo,anio FROM vehiculo WHERE id_vehiculo=$ID;"

echo "== Edicion de vehiculo ajeno (id=$OTRO) =="
if [ -n "$OTRO" ]; then
  ANTES=$(docker exec tgs_db $DB "SELECT CONCAT(marca,'|',modelo,'|',patente) FROM vehiculo WHERE id_vehiculo=$OTRO;")
  RESP2=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'reservas.php')&accion_editar_vehiculo=1&id_vehiculo_editar=$OTRO&edit_marca=Hack&edit_modelo=Hack&edit_anio=1999&edit_patente=HACK" $BASE/reservas.php)
  echo "$RESP2" | grep -q "Solo puedes editar tus propios vehículos" && echo "BLOQUEO_PROPIEDAD_OK" || echo "SIN_BLOQUEO_PROPIEDAD"
  DESPUES=$(docker exec tgs_db $DB "SELECT CONCAT(marca,'|',modelo,'|',patente) FROM vehiculo WHERE id_vehiculo=$OTRO;")
  echo "antes=$ANTES despues=$DESPUES"
  [ "$ANTES" = "$DESPUES" ] && echo "SIN_CAMBIOS_OK" || echo "ALERTA_CAMBIO_AJENO"
fi

echo "== Restaurar vehiculo de prueba =="
docker exec tgs_db $DB "UPDATE vehiculo SET marca='Toyota', modelo='Corolla', anio=2019, patente='AB123CD' WHERE id_vehiculo=$ID;" >/dev/null
echo "RESTAURADO"
rm -f $JAR
