#!/bin/bash
DB="mysql -N -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e"

echo "== Restaurar los datos de demo del seed.sql =="
docker exec tgs_db $DB "DELETE FROM notificacion;" > /dev/null
docker exec tgs_db $DB "DELETE FROM factura;" > /dev/null
docker exec tgs_db $DB "DELETE FROM pago;" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva_historial;" > /dev/null
docker exec tgs_db $DB "DELETE FROM presupuesto_detalle;" > /dev/null
docker exec tgs_db $DB "DELETE FROM presupuesto;" > /dev/null
docker exec tgs_db $DB "DELETE FROM reserva;" > /dev/null

# Reserva 1: turno pendiente, ya pagado (para ver el saldo en cero)
docker exec tgs_db $DB "INSERT INTO reserva (id_reserva,cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES (1,'11111111',1,1,1,'2026-10-05','10:00:00','Pendiente',2500.00,'pagado');" > /dev/null
docker exec tgs_db $DB "INSERT INTO pago (id_pago,id_reserva,monto,metodo,estado,referencia,fecha_pago,registrado_por,notas) VALUES (1,1,1500.00,'efectivo','pagado','REC-0001','2026-10-05 10:05:00','00000000','Abono al momento de la entrega'),(2,1,1000.00,'tarjeta','pagado','TC-45871','2026-10-05 10:20:00','00000000','Saldo final con tarjeta');" > /dev/null

# Reserva 2: servicio ya realizado, con su factura (historial del cliente)
docker exec tgs_db $DB "INSERT INTO reserva (id_reserva,cedula_usuario,id_vehiculo,id_taller,id_servicio,fecha_reserva,hora_reserva,estado,precio_total,estado_pago) VALUES (2,'11111111',1,1,2,'2026-09-15','10:00:00','Finalizada',1800.00,'pagado');" > /dev/null
docker exec tgs_db $DB "INSERT INTO pago (id_pago,id_reserva,monto,metodo,estado,referencia,fecha_pago,registrado_por,notas) VALUES (3,2,1800.00,'efectivo','pagado','REC-0002','2026-09-15 11:00:00','00000000','Pago del servicio realizado');" > /dev/null
docker exec tgs_db $DB "INSERT INTO factura (id_factura,numero,id_reserva,cedula_usuario,fecha_emision,total,estado,forma_pago,notas) VALUES (1,'F-2026-0001',2,'11111111','2026-09-15',1800.00,'emitida','efectivo','Factura de demostracion');" > /dev/null

# Presupuesto de ejemplo
docker exec tgs_db $DB "INSERT INTO presupuesto (id_presupuesto,id_reserva,cedula_usuario,fecha_presupuesto,total,estado,observaciones,creado_por) VALUES (1,1,'11111111','2026-10-03',2500.00,'pendiente','Presupuesto enviado al cliente. Incluye mano de obra y repuestos originales.','00000000');" > /dev/null
docker exec tgs_db $DB "INSERT INTO presupuesto_detalle (id_detalle,id_presupuesto,descripcion,cantidad,precio_unitario,subtotal) VALUES (1,1,'Cambio de aceite y filtros (mano de obra)',1,1200.00,1200.00),(2,1,'Repuestos: filtro de aceite y filtro de aire',1,1300.00,1300.00);" > /dev/null

# Saldo prepago del cliente de demostracion
docker exec tgs_db $DB "UPDATE usuario SET nombre='Carlos', apellido='Gomez', saldo_prepago=1500.00 WHERE email='carlos.gomez@gmail.com';" > /dev/null

# Marcas y tipos de servicio por sede
docker exec tgs_db $DB "DELETE FROM taller_marca;" > /dev/null
docker exec tgs_db $DB "DELETE FROM taller_servicio;" > /dev/null
docker exec tgs_db $DB "INSERT INTO taller_marca (id_taller,marca) VALUES (1,'Toyota'),(1,'Volkswagen'),(1,'Chevrolet'),(1,'Ford'),(1,'Peugeot'),(2,'Volkswagen'),(2,'Chevrolet'),(2,'Peugeot');" > /dev/null
docker exec tgs_db $DB "INSERT INTO taller_servicio (id_taller,id_servicio) VALUES (1,1),(1,2),(1,3),(2,1),(2,2);" > /dev/null

echo "== Verificacion =="
docker exec tgs_db $DB "SELECT id_reserva,cedula_usuario,fecha_reserva,estado,precio_total,estado_pago FROM reserva;"
docker exec tgs_db $DB "SELECT id_pago,monto,metodo,estado FROM pago;"
docker exec tgs_db $DB "SELECT id_presupuesto,total,estado FROM presupuesto;"
docker exec tgs_db $DB "SELECT id_factura,numero,total FROM factura;"
docker exec tgs_db $DB "SELECT nombre,apellido,saldo_prepago FROM usuario WHERE email='carlos.gomez@gmail.com';"
docker exec tgs_db $DB "SELECT COUNT(*) FROM taller_marca; SELECT COUNT(*) FROM taller_servicio;"
