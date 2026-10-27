#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_modal.txt
rm -f $JAR
tgs_login $BASE $JAR "$ADMIN" "$PASS"

MARTES=$(date -d "+20 days" +%Y-%m-%d)
while [ $(date -d "$MARTES" +%u) -ne 2 ]; do MARTES=$(date -d "$MARTES + 1 day" +%Y-%m-%d); done
docker exec tgs_db $DB "UPDATE reserva SET fecha_reserva='$MARTES', hora_reserva='10:00:00' WHERE id_reserva=1;" > /dev/null

echo "== 1. La agenda tiene el modal =="
H=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=$MARTES")
echo "$H" | grep -c 'id="modal-detalle"' | sed 's/^/modal=/'
echo "$H" | grep -c 'id="iframe-detalle"' | sed 's/^/iframe=/'
echo "$H" | grep -c 'turno-clicable' | sed 's/^/cliquables=/'
echo "$H" | grep -c 'data-detalle="detalle_reserva.php?id=' | sed 's/^/enlaces_detalle=/'
echo "$H" | grep -c "tgs-actualizar" | sed 's/^/listener=/'

echo "== 2. El detalle embebido funciona (sin cabecera, con boton cerrar) =="
H2=$(curl -s -c $JAR -b $JAR "$BASE/detalle_reserva.php?id=1&embed=1")
echo "$H2" | grep -c 'class="modo-embed"' | sed 's/^/modo_embed=/'
echo "$H2" | grep -c 'embed-barra' | sed 's/^/barra_embed=/'
echo "$H2" | grep -c '<header>' | sed 's/^/header_visible=/'
echo "$H2" | grep -c 'cerrarDetalle' | sed 's/^/boton_cerrar=/'
echo "$H2" | grep -c 'tgs-cerrar' | sed 's/^/post_cerrar=/'

echo "== 3. El detalle normal sigue con cabecera =="
H3=$(curl -s -c $JAR -b $JAR "$BASE/detalle_reserva.php?id=1")
echo "$H3" | grep -c '<header>' | sed 's/^/header_normal=/'
echo "$H3" | grep -c 'modo-embed' | sed 's/^/modo_embed_normal=/'

echo "== 4. En modo embebido se puede cambiar el estado y avisa al padre =="
TOKEN=$(echo "$H2" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
H4=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=cambiar_estado&id_reserva=1&estado_reserva=En curso" "$BASE/detalle_reserva.php?id=1&embed=1")
echo "$H4" | grep -o "Estado actualizado correctamente" | head -1
echo "$H4" | grep -c "tgs-actualizar" | sed 's/^/aviso_al_padre=/'
docker exec tgs_db $DB "SELECT estado FROM reserva WHERE id_reserva=1;"

echo "== 5. Y volver a la agenda muestra el estado nuevo =="
H5=$(curl -s -c $JAR -b $JAR "$BASE/agenda_gestor.php?fecha=$MARTES")
echo "$H5" | grep -c 'turno en-curso' | sed 's/^/turno_en_curso_en_grilla=/'

echo "== 6. Registrar un cobro desde el modal =="
TOKEN=$(echo "$H2" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
H6=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=registrar_pago&id_reserva=1&monto=150&metodo=efectivo&referencia=MODAL-1" "$BASE/detalle_reserva.php?id=1&embed=1")
echo "$H6" | grep -o "Cobro registrado correctamente" | head -1
docker exec tgs_db $DB "SELECT id_pago,monto,referencia,estado FROM pago WHERE referencia='MODAL-1';"

echo "== 7. Reprogramar desde el modal =="
H7=$(curl -s -c $JAR -b $JAR -d "csrf_token=$TOKEN&accion=reprogramar&id_reserva=1&nueva_fecha=$MARTES&nueva_hora=16:00&nueva_sede=2" "$BASE/detalle_reserva.php?id=1&embed=1")
echo "$H7" | grep -o "Turno reprogramado correctamente" | head -1
docker exec tgs_db $DB "SELECT hora_reserva,id_taller FROM reserva WHERE id_reserva=1;"

echo "== 8. Limpieza =="
docker exec tgs_db $DB "DELETE FROM pago WHERE referencia='MODAL-1';" > /dev/null
docker exec tgs_db $DB "UPDATE reserva SET fecha_reserva='2026-10-05', hora_reserva='10:00:00', estado='Pendiente' WHERE id_reserva=1;" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva_historial WHERE id_reserva=1;" > /dev/null
rm -f $JAR
echo "FIN"
