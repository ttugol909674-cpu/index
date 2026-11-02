<?php
/**
 * Pipeline de video TGS - clon de la base y reparacion de los datos.
 *
 * Clona la base local (bluetech_base) a bluetech_capturas y deja el clon con
 * datos coherentes para las capturas: contrasenas conocidas, horarios, precios,
 * la segunda sede, turnos futuros en las franjas reales y un cobro reportado.
 *
 * LA BASE ORIGINAL NO SE TOCA.
 *
 * Uso:  php preparar-base.php
 */
$raizTrabajo = getenv('TGS_VIDEO_WORK') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgs-video';
$proyecto = getenv('TGS_PROYECTO') ?: 'C:\\xampp\\htdocs\\tgs';

$ORIGEN = 'bluetech_base';
$CLON   = 'bluetech_capturas';
$USUARIO = 'tgs_capturas';
$CLAVE   = 'TgsCapturas2026';

$mysql   = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
if (!is_file($mysql)) { fwrite(STDERR, "No se encuentra mysql.exe en $mysql\n"); exit(1); }

function sql(string $sql): void {
    global $mysql;
    $cmd = '"' . $mysql . '" --host=127.0.0.1 --port=3306 --user=root --protocol=tcp --default-character-set=utf8mb4 -e "' . $sql . '"';
    exec($cmd, $salida, $codigo);
    if ($codigo !== 0) { fwrite(STDERR, "SQL fallo:\n$sql\n" . implode("\n", $salida) . "\n"); exit(1); }
}

echo "== 1. Volcado de $ORIGEN ==\n";
$dump = $raizTrabajo . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'dump_base.sql';
if (!is_dir(dirname($dump))) { mkdir(dirname($dump), 0777, true); }
$cmd = '"' . $mysqldump . '" --host=127.0.0.1 --port=3306 --user=root --protocol=tcp --single-transaction '
     . '--routines --triggers --add-drop-table --default-character-set=utf8mb4 '
     . '--result-file="' . $dump . '" ' . $ORIGEN;
exec($cmd, $salida, $codigo);
if ($codigo !== 0 || !is_file($dump)) { fwrite(STDERR, "mysqldump fallo\n" . implode("\n", $salida) . "\n"); exit(1); }
echo '   dump: ' . number_format(filesize($dump)) . " bytes\n";

echo "== 2. Recreacion de $CLON ==\n";
sql("DROP DATABASE IF EXISTS `$CLON`; CREATE DATABASE `$CLON` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$lista = $raizTrabajo . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'restore.sql';
file_put_contents($lista, "source " . str_replace('\\', '/', $dump) . "\n");
exec('"' . $mysql . '" --host=127.0.0.1 --port=3306 --user=root --protocol=tcp ' . $CLON . ' < "' . $lista . '"', $salida, $codigo);
if ($codigo !== 0) { fwrite(STDERR, "restore fallo\n" . implode("\n", $salida) . "\n"); exit(1); }
@unlink($lista);
@unlink($dump);
echo "   clon creado\n";

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=' . $CLON . ';charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ok = function (string $s) use ($pdo) { $pdo->exec($s); };

echo "== 3. Alineacion con el schema del proyecto ==\n";
// El dump de MySQL local queda en utf8mb4_general_ci y el schema del proyecto
// usa utf8mb4_unicode_ci: sin convertir, los FK nuevos fallan con errno 150.
$tablas = [];
foreach ($pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='$CLON'") as $t) {
    $tablas[] = $t['table_name'];
}
foreach ($tablas as $tabla) {
    foreach ($pdo->query("SELECT constraint_name FROM information_schema.referential_constraints WHERE constraint_schema='$CLON' AND table_name=" . $pdo->quote($tabla)) as $fk) {
        $pdo->exec("ALTER TABLE `$tabla` DROP FOREIGN KEY `{$fk['constraint_name']}`");
    }
}
foreach ($tablas as $tabla) {
    if ($tabla === 'servicios') { continue; }
    $pdo->exec("ALTER TABLE `$tabla` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
$ok('DROP TABLE IF EXISTS `servicios`');
$ok("ALTER TABLE `usuario` ALTER COLUMN `rol` SET DEFAULT 'cliente'");

// Reponer las FKs tal como las define db/schema.sql
$esquema = file_get_contents($proyecto . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'schema.sql');
preg_match_all('/CREATE TABLE `(\w+)` \((.*?)\n\) ENGINE=/s', $esquema, $bloques, PREG_SET_ORDER);
$fks = 0;
foreach ($bloques as $b) {
    if (!in_array($b[1], $tablas, true) && $b[1] !== 'servicios') { continue; }
    if (preg_match_all('/CONSTRAINT\s+`\w+`\s+FOREIGN KEY\s*\([^)]*\)\s*REFERENCES\s*`\w+`\s*\([^)]*\)(?:\s*ON DELETE CASCADE)?,?\s*/', $b[2], $m)) {
        foreach ($m[0] as $fk) { $pdo->exec("ALTER TABLE `{$b[1]}` ADD " . rtrim(trim($fk), ',')); $fks++; }
    }
}
echo "   $fks claves foraneas del schema del proyecto\n";

echo "== 4. Tablas que faltan ==\n";
foreach (['presupuesto', 'presupuesto_detalle'] as $t) {
    if (preg_match('/CREATE TABLE `' . $t . '`.*?;/s', $esquema, $m)) { $ok($m[0]); echo "   + $t\n"; }
}

echo "== 5. Datos de demostracion ==\n";
// Sede
$ok("INSERT IGNORE INTO `taller` (`id_taller`,`nombre`,`direccion`,`telefono`,`email`,`mapa`,`capacidad`) VALUES
 (2,'TGS Maldonado','Peatonal Saravia 505, Maldonado','099 654 321','maldonado@tgs.com.uy',
  '<iframe src=\"https://www.google.com/maps?q=Peatonal+Saravia+505+Maldonado&output=embed\" width=\"100%\" height=\"220\" style=\"border:0\" allowfullscreen=\"\" loading=\"lazy\" referrerpolicy=\"no-referrer-when-downgrade\"></iframe>',2)");

// Horarios de lunes a sabado
$ok('DELETE FROM `taller_horario`');
for ($d = 1; $d <= 6; $d++) {
    foreach ([1, 2] as $t) { $ok("INSERT INTO `taller_horario` (`id_taller`,`dia_semana`,`hora_inicio`,`hora_fin`,`activo`) VALUES ($t,$d,'08:30:00','18:30:00',1)"); }
}

// Catalogo con precios
$st = $pdo->prepare('UPDATE `servicio` SET `precio`=?, `activo`=1 WHERE `id_servicio`=?');
foreach ([1 => 2500.00, 2 => 1800.00, 3 => 3500.00] as $id => $precio) { $st->execute([$precio, $id]); }

// Marcas y tipos de servicio por sede
$ok('DELETE FROM `taller_marca`');
foreach ([[1, 'Toyota'], [1, 'Volkswagen'], [1, 'Chevrolet'], [1, 'Ford'], [1, 'Peugeot'], [2, 'Volkswagen'], [2, 'Chevrolet'], [2, 'Peugeot']] as $m) {
    $ok("INSERT INTO `taller_marca` (`id_taller`,`marca`) VALUES ({$m[0]},'{$m[1]}')");
}
$ok('DELETE FROM `taller_servicio`');
foreach ([[1, 1], [1, 2], [1, 3], [2, 1], [2, 2]] as $m) { $ok("INSERT INTO `taller_servicio` (`id_taller`,`id_servicio`) VALUES ({$m[0]},{$m[1]})"); }

// Cuenta bancaria (QR para que el cliente pueda transferir)
$ok('DELETE FROM `datos_pago`');
$ok("INSERT INTO `datos_pago` (`id_datos_pago`,`nombre`,`alias`,`cbu`,`qr`,`notas`,`activo`) VALUES
 (1,'TGS - Cuenta de demostracion','TGS.DEMO.ALIAS','0000000000000000000000',
  'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=TGS.DEMO.ALIAS',
  'Cuenta de ejemplo. Reemplazar alias, CBU y QR por los datos reales.',1)");

// Usuarios: rol normalizado y contrasenas bcrypt conocidas
$claves = [
    'admin@bluetech.com'     => ['Admin123!',   'admin',   0.00],
    'admin2@gmail.com'       => ['Admin123!',   'admin',   0.00],
    'santiiuyy12@gmail.com'  => ['Gestor123!',  'gestor',  0.00],
    'gomez@gmail.com'        => ['Gestor123!',  'gestor',  0.00],
    'carlos.gomez@gmail.com' => ['Cliente123!', 'cliente', 1500.00],
    'mariana.silva@gmail.com'=> ['Cliente123!', 'cliente', 0.00],
    'perez12@gmail.com'      => ['Cliente123!', 'cliente', 0.00],
    'anabeatriz@gmail.com'   => ['Cliente123!', 'cliente', 0.00],
    'luisortega@gmail.com'   => ['Cliente123!', 'cliente', 0.00],
    'paulaacosta@gmail.com'  => ['Cliente123!', 'cliente', 0.00],
];
$st = $pdo->prepare('UPDATE `usuario` SET `password`=?, `rol`=?, `saldo_prepago`=? WHERE `email`=?');
foreach ($claves as $email => [$clave, $rol, $saldo]) { $st->execute([password_hash($clave, PASSWORD_DEFAULT), $rol, $saldo, $email]); }

// Clientes extra para que la agenda tenga movimiento.
// Van ANTES que los vehiculos: el vehiculo tiene FK al usuario (cedula).
$st = $pdo->prepare('INSERT INTO `usuario` (`cedula`,`nombre`,`apellido`,`email`,`password`,`telefono`,`rol`,`saldo_prepago`) VALUES (?,?,?,?,?,?,?,?)');
foreach ([
    ['33333333', 'Ana Beatriz', 'Fernandez', 'anabeatriz@gmail.com', '099 334 455'],
    ['44444444', 'Luis Alberto', 'Ortega', 'luisortega@gmail.com', '099 445 566'],
    ['55555555', 'Paula Nair', 'Acosta', 'paulaacosta@gmail.com', '099 556 677'],
] as $c) {
    $st->execute([$c[0], $c[1], $c[2], $c[3], password_hash('Cliente123!', PASSWORD_DEFAULT), $c[4], 'cliente', 0]);
}

// Vehiculos: borrar duplicados y repartir
$ok('DELETE FROM `vehiculo` WHERE `id_vehiculo` IN (10,11,13)');
$ok("UPDATE `vehiculo` SET `cedula_usuario`='11111111' WHERE `id_vehiculo`=8");
$ok("UPDATE `vehiculo` SET `cedula_usuario`='22222222', `marca`='Chevrolet', `modelo`='Onix', `anio`=2023 WHERE `id_vehiculo`=9");
$ok("INSERT INTO `vehiculo` (`id_vehiculo`,`cedula_usuario`,`patente`,`marca`,`modelo`,`anio`) VALUES
 (20,'11111111','ABC1234','Toyota','Corolla',2022),
 (21,'57779748','GHI9012','Chevrolet','Onix',2022),
 (22,'22222222','SBC 7745','Peugeot','208',2020),
 (23,'33333333','AB 1234','Toyota','Etios',2020),
 (24,'44444444','CD 2345','Volkswagen','Amarok',2021),
 (25,'55555555','EF 3456','Chevrolet','Cruze',2019),
 (26,'33333333','GH 4567','Peugeot','208',2022)");

// Turnos: las horas son las franjas reales (08:30 + 60 min)
$ok('DELETE FROM `pago`');
$ok('DELETE FROM `factura`');
$ok('DELETE FROM `presupuesto_detalle`');
$ok('DELETE FROM `presupuesto`');
$ok('DELETE FROM `reserva_historial`');
$ok('DELETE FROM `reserva`');
$turnos = [
    [16, '11111111', 8, 1, 1, '2026-11-03', '10:30:00', 'Pendiente', 2500.00, 'parcial'],
    [20, '57779748', 12, 2, 3, '2026-11-05', '11:30:00', 'Pendiente', 3500.00, 'pendiente'],
    [30, '22222222', 4, 1, 2, '2026-10-27', '10:30:00', 'Finalizada', 1800.00, 'pagado'],
    [31, '11111111', 3, 1, 3, '2026-11-06', '15:30:00', 'Pendiente', 3500.00, 'pendiente'],
    [32, '57779748', 21, 2, 1, '2026-11-04', '09:30:00', 'En curso', 2500.00, 'pendiente'],
    [40, '33333333', 23, 1, 1, '2026-11-03', '08:30:00', 'Pendiente', 2500.00, 'pagado'],
    [41, '44444444', 24, 1, 2, '2026-11-03', '09:30:00', 'Pendiente', 1800.00, 'pendiente'],
    [42, '55555555', 25, 1, 3, '2026-11-03', '11:30:00', 'Pendiente', 3500.00, 'pendiente'],
    [43, '33333333', 26, 1, 2, '2026-11-03', '13:30:00', 'Pendiente', 1800.00, 'pendiente'],
    [44, '44444444', 24, 1, 3, '2026-11-03', '15:30:00', 'Pendiente', 3500.00, 'pendiente'],
    [45, '55555555', 25, 2, 1, '2026-11-03', '08:30:00', 'Pendiente', 2500.00, 'pendiente'],
    [46, '33333333', 26, 2, 2, '2026-11-03', '09:30:00', 'Pendiente', 1800.00, 'pendiente'],
    [47, '11111111', 3, 2, 3, '2026-11-03', '16:30:00', 'Pendiente', 3500.00, 'pendiente'],
    [48, '55555555', 25, 1, 1, '2026-11-04', '08:30:00', 'Pendiente', 2500.00, 'pendiente'],
    [49, '33333333', 23, 1, 2, '2026-11-04', '10:30:00', 'Pendiente', 1800.00, 'pendiente'],
    [50, '33333333', 23, 1, 3, '2026-11-05', '09:30:00', 'Pendiente', 3500.00, 'pendiente'],
    [51, '55555555', 25, 1, 1, '2026-11-05', '12:30:00', 'Pendiente', 2500.00, 'pendiente'],
    [52, '44444444', 24, 2, 2, '2026-11-06', '10:30:00', 'Pendiente', 1800.00, 'pendiente'],
];
$st = $pdo->prepare('INSERT INTO `reserva` (`id_reserva`,`cedula_usuario`,`id_vehiculo`,`id_taller`,`id_servicio`,`fecha_reserva`,`hora_reserva`,`estado`,`precio_total`,`estado_pago`) VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ($turnos as $t) { $st->execute($t); }

// Cobros: dos pagados, uno parcial y uno reportado por el cliente
$ok("INSERT INTO `pago` (`id_pago`,`id_reserva`,`monto`,`metodo`,`estado`,`referencia`,`fecha_pago`,`registrado_por`,`notas`) VALUES
 (1,16,1500.00,'efectivo','pagado','REC-0001','2026-11-03 10:35:00','00000000','Abono al momento de la entrega'),
 (2,30,1800.00,'efectivo','pagado','REC-0002','2026-10-27 11:00:00','00000000','Pago del servicio realizado'),
 (4,40,2500.00,'transferencia','pagado','TR-77812','2026-11-03 08:40:00','00000000','Pago por transferencia bancaria'),
 (5,31,3500.00,'transferencia','reportado','TR-90114','2026-11-01 19:40:00',NULL,'Transferencia desde el Banco BPS, comprobante adjunto.')");
$ok("INSERT INTO `factura` (`id_factura`,`numero`,`id_reserva`,`cedula_usuario`,`fecha_emision`,`total`,`estado`,`forma_pago`,`notas`) VALUES
 (1,'F-2026-0001',30,'22222222','2026-10-27',1800.00,'emitida','efectivo','Factura de demostracion')");

// Presupuestos: uno pendiente de aceptar y uno ya aceptado
$ok("INSERT INTO `presupuesto` (`id_presupuesto`,`id_reserva`,`cedula_usuario`,`fecha_presupuesto`,`fecha_vencimiento`,`total`,`estado`,`observaciones`,`creado_por`) VALUES
 (1,31,'11111111','2026-11-01','2026-11-08',3500.00,'pendiente','Presupuesto enviado al cliente. Incluye mano de obra y repuestos originales.','00000000'),
 (2,16,'11111111','2026-11-02','2026-11-09',2500.00,'aceptado','Presupuesto aceptado por el cliente.','00000000')");
$ok("INSERT INTO `presupuesto_detalle` (`id_detalle`,`id_presupuesto`,`descripcion`,`cantidad`,`precio_unitario`,`subtotal`) VALUES
 (1,1,'Cambio de aceite y filtros (mano de obra)',1,1200.00,1200.00),
 (2,1,'Repuestos: filtro de aceite y filtro de aire',1,2300.00,2300.00),
 (3,2,'Revision general y fluidos',1,2500.00,2500.00)");

// Auditoria y avisos
$ok("INSERT INTO `reserva_historial` (`id_historial`,`id_reserva`,`campo`,`valor_anterior`,`valor_nuevo`,`fecha_cambio`,`usuario`) VALUES
 (1,16,'fecha_reserva','2027-11-10','2026-11-03','2026-11-01 09:00:00','00000000'),
 (2,30,'estado','Pendiente','Finalizada','2026-10-27 12:00:00','699542442'),
 (3,32,'estado','Pendiente','En curso','2026-11-04 09:35:00','699542442')");
$ok("INSERT INTO `notificacion` (`id_notificacion`,`id_reserva`,`cedula_usuario`,`email_destino`,`asunto`,`cuerpo`,`tipo`,`estado`,`intentos`,`created_at`) VALUES
 (1,30,'22222222','mariana.silva@gmail.com','Su turno fue finalizado','Hola Mariana, su servicio fue entregado. Adjuntamos la factura.','turno','pendiente',0,'2026-10-27 12:00:00'),
 (2,16,'11111111','carlos.gomez@gmail.com','Nuevo presupuesto disponible','Hola Carlos, hay un presupuesto pendiente de aceptacion.','presupuesto','pendiente',0,'2026-11-01 10:00:00'),
 (3,40,'33333333','anabeatriz@gmail.com','Turno confirmado','Hola Ana, tu turno quedo confirmado para el 03/11 a las 08:30.','turno','pendiente',0,'2026-11-02 18:00:00')");

echo "== 6. Usuario de la base para la app ==\n";
sql("CREATE USER IF NOT EXISTS '$USUARIO'@'127.0.0.1' IDENTIFIED BY '$CLAVE';");
sql("CREATE USER IF NOT EXISTS '$USUARIO'@'localhost' IDENTIFIED BY '$CLAVE';");
sql("GRANT ALL PRIVILEGES ON `$CLON`.* TO '$USUARIO'@'127.0.0.1';");
sql("GRANT ALL PRIVILEGES ON `$CLON`.* TO '$USUARIO'@'localhost';");
sql('FLUSH PRIVILEGES;');

echo "\n== Contenido final del clon ==\n";
foreach (['usuario', 'taller', 'taller_horario', 'servicio', 'vehiculo', 'reserva', 'pago', 'factura', 'presupuesto', 'presupuesto_detalle', 'datos_pago', 'notificacion', 'reserva_historial'] as $t) {
    printf("   %-22s %d filas\n", $t, $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn());
}
echo "\nListo. Credenciales: admin@bluetech.com/Admin123!  santiiuyy12@gmail.com/Gestor123!  carlos.gomez@gmail.com/Cliente123!\n";
