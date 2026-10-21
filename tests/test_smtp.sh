#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/jar_smtp.txt
rm -f $JAR

echo "== 0. Backup del .env.local =="
docker exec tgs_app cp /var/www/html/.env.local /var/www/html/.env.local.bak 2>/dev/null && echo "backup ok" || echo "sin .env.local (se creara)"

echo "== 1. Levantar el servidor SMTP de prueba =="
docker exec -d tgs_app php /var/www/html/smtp_test_server.php 2525 /tmp/correo_recibido.txt
sleep 2
docker exec tgs_app sh -c 'rm -f /tmp/correo_recibido.txt'

echo "== 2. Configurar SMTP temporal en el contenedor =="
docker exec tgs_app sh -c "printf '\nSMTP_HOST=127.0.0.1\nSMTP_PUERTO=2525\nSMTP_REMITENTE=notificaciones@tgs.local\n' >> /var/www/html/.env.local"
docker exec tgs_app sh -c 'grep SMTP /var/www/html/.env.local'

echo "== 3. Login admin y finalizar la reserva =="
tgs_login $BASE $JAR "$ADMIN" "$PASS"
IDRES=$(docker exec tgs_db $DB "SELECT id_reserva FROM reserva ORDER BY id_reserva LIMIT 1;")
R=$(curl -s -c $JAR -b $JAR -d "csrf_token=$(tgs_token $BASE $JAR 'administrar_reservas.php')&id_reserva=$IDRES&estado_reserva=Finalizada" $BASE/administrar_reservas.php)
echo "$R" | grep -o 'class="alert[^"]*">[^<]*' | head -1
docker exec tgs_db $DB "SELECT id_notificacion,email_destino,estado,respuesta_transporte FROM notificacion ORDER BY id_notificacion DESC LIMIT 1;"

echo "== 4. Lo que llego al servidor SMTP =="
docker exec tgs_app sh -c 'if [ -f /tmp/correo_recibido.txt ]; then echo "MENSAJE RECIBIDO:"; cat /tmp/correo_recibido.txt; else echo "NO LLEGO NINGUN MENSAJE"; fi'

echo "== 5. Restaurar configuracion y limpiar =="
docker exec tgs_app sh -c 'if [ -f /var/www/html/.env.local.bak ]; then mv /var/www/html/.env.local.bak /var/www/html/.env.local; else sed -i "/^SMTP_/d" /var/www/html/.env.local; fi'
docker exec tgs_app sh -c 'grep -c SMTP /var/www/html/.env.local' | sed 's/^/lineas_smtp_restantes=/'
docker exec tgs_db $DB "DELETE FROM notificacion;" > /dev/null
docker exec tgs_db $DB "UPDATE reserva SET estado='Pendiente' WHERE id_reserva=$IDRES;" > /dev/null
rm -f $JAR
echo "FIN"
