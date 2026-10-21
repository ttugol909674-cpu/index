<?php
/**
 * TGS - Taller Global de Servicios
 * Factura electronica descargable.
 *
 * Muestra el comprobante de un turno con los datos del cliente, el servicio,
 * los items cotizados, los cobros y el saldo. La pagina esta preparada para
 * imprimirse: el boton "Descargar" abre el dialogo de impresion del
 * navegador, donde se puede elegir "Guardar como PDF".
 *
 * Acceso: el cliente ve sus propias facturas; gestores y administradores
 * ven las de cualquier cliente.
 *
 * Dependencias: auth.php, conexion.php, funciones_pago.php
 */
require_once 'auth.php';
verificarPermiso('ver_facturas');
require_once 'conexion.php';
require_once 'funciones_pago.php';

$lang_code = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : ($_SESSION['lang'] ?? 'es');
$_SESSION['lang'] = $lang_code;

$cedula_usuario = $_SESSION['cedula'] ?? '';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$es_cliente = ($rol_actual === 'cliente');

$texto = $lang_code === 'en' ? [
    'titulo' => 'Invoice', 'numero' => 'Invoice number', 'fecha' => 'Issue date', 'cliente' => 'Customer',
    'ci' => 'ID number', 'email' => 'Email', 'telefono' => 'Phone', 'detalle' => 'Service detail',
    'vehiculo' => 'Vehicle', 'sede' => 'Workshop', 'servicio' => 'Service', 'items' => 'Items',
    'pagos' => 'Payments', 'total' => 'Total', 'saldo' => 'Balance', 'metodo' => 'Method', 'referencia' => 'Reference',
    'descargar' => 'Download / Print', 'volver' => 'Back', 'no_existe' => 'The invoice does not exist.',
    'sin_items' => 'Service without itemization', 'empresa' => 'Blue Tech Uruguay',
    'proyecto' => 'TGS - Taller Global de Servicios', 'ruta' => 'Av. Italia 2485, Montevideo',
    'sin_pagos' => 'No payments registered for this appointment.', 'gracias' => 'Thank you for your purchase.'
] : [
    'titulo' => 'Factura', 'numero' => 'Número de factura', 'fecha' => 'Fecha de emisión', 'cliente' => 'Cliente',
    'ci' => 'C.I.', 'email' => 'Correo', 'telefono' => 'Teléfono', 'detalle' => 'Detalle del servicio',
    'vehiculo' => 'Vehículo', 'sede' => 'Sede', 'servicio' => 'Servicio', 'items' => 'Items',
    'pagos' => 'Cobros', 'total' => 'Total', 'saldo' => 'Saldo', 'metodo' => 'Método', 'referencia' => 'Referencia',
    'descargar' => 'Descargar / Imprimir', 'volver' => 'Volver', 'no_existe' => 'La factura no existe.',
    'sin_items' => 'Servicio sin items detallados', 'empresa' => 'Blue Tech Uruguay',
    'proyecto' => 'TGS - Taller Global de Servicios', 'ruta' => 'Av. Italia 2485, Montevideo',
    'sin_pagos' => 'No hay cobros registrados para este turno.', 'gracias' => 'Gracias por su compra.'
];

$id_factura = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$factura = null;
$reserva = null;
$items = [];
$cobros = [];

if ($id_factura) {
    try {
        $consulta = $conexion->prepare(
            'SELECT f.*, r.fecha_reserva, r.hora_reserva, r.estado, r.precio_total,
                    u.nombre, u.apellido, u.email, u.telefono,
                    v.marca, v.modelo, v.patente,
                    s.nombre AS servicio_nombre, t.nombre AS taller_nombre
             FROM factura f
             JOIN reserva r ON r.id_reserva = f.id_reserva
             LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
             LEFT JOIN vehiculo v ON v.id_vehiculo = r.id_vehiculo
             LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
             LEFT JOIN taller t ON t.id_taller = r.id_taller
             WHERE f.id_factura = :id_factura LIMIT 1'
        );
        $consulta->execute(['id_factura' => $id_factura]);
        $factura = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($factura) {
            // El cliente solo puede abrir sus propias facturas.
            if ($es_cliente && (string) $factura['cedula_usuario'] !== (string) $cedula_usuario) {
                $factura = null;
            } else {
                $id_reserva_factura = (int) $factura['id_reserva'];

                $consulta_items = $conexion->prepare(
                    'SELECT descripcion, cantidad, precio_unitario, subtotal
                     FROM presupuesto_detalle
                     WHERE id_presupuesto = (SELECT id_presupuesto FROM presupuesto WHERE id_reserva = :id_reserva ORDER BY id_presupuesto DESC LIMIT 1)
                     ORDER BY id_detalle'
                );
                $consulta_items->execute(['id_reserva' => $id_reserva_factura]);
                $items = $consulta_items->fetchAll(PDO::FETCH_ASSOC);

                $consulta_cobros = $conexion->prepare(
                    "SELECT monto, metodo, referencia, fecha_pago, estado FROM pago
                     WHERE id_reserva = :id_reserva AND estado <> 'anulado' ORDER BY fecha_pago"
                );
                $consulta_cobros->execute(['id_reserva' => $id_reserva_factura]);
                $cobros = $consulta_cobros->fetchAll(PDO::FETCH_ASSOC);

                $reserva = $factura;
            }
        }
    } catch (PDOException $e) {
        $factura = null;
    }
}

$total_pagado = 0.0;
foreach ($cobros as $cobro) {
    $total_pagado += (float) ($cobro['monto'] ?? 0);
}
$total_factura = (float) ($factura['total'] ?? 0);
$saldo = max(0, $total_factura - $total_pagado);
$metodos = tgs_metodos_pago();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> <?php echo htmlspecialchars($factura['numero'] ?? ''); ?> - TGS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 30px 16px 60px; }
        .factura { background-color: #ffffff; color: #0f172a; width: 100%; max-width: 780px; border-radius: 10px; padding: 38px 40px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45); }
        .factura-cabecera { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #0b1329; padding-bottom: 18px; margin-bottom: 22px; }
        .empresa-nombre { font-size: 20px; font-weight: 800; color: #0b1329; }
        .empresa-dato { font-size: 12px; color: #475569; margin-top: 3px; }
        .factura-numero { text-align: right; }
        .factura-numero strong { display: block; font-size: 17px; color: #0369a1; }
        .factura-numero span { font-size: 12px; color: #475569; }
        .bloque { margin-bottom: 20px; }
        .bloque h3 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.09em; color: #64748b; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: #f1f5f9; color: #334155; text-align: left; padding: 9px 10px; font-size: 12px; }
        td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; color: #1e293b; }
        td.num, th.num { text-align: right; }
        .datos { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 26px; font-size: 13px; }
        .datos div { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px dotted #cbd5e1; padding: 4px 0; }
        .datos span:first-child { color: #64748b; }
        .totales { margin-top: 14px; margin-left: auto; width: 280px; font-size: 13px; }
        .totales div { display: flex; justify-content: space-between; padding: 5px 0; }
        .totales .total-final { border-top: 2px solid #0b1329; margin-top: 6px; padding-top: 10px; font-size: 16px; font-weight: 800; }
        .totales .total-final span:last-child { color: #0369a1; }
        .pie-factura { margin-top: 26px; border-top: 1px solid #e2e8f0; padding-top: 14px; font-size: 11px; color: #64748b; text-align: center; }
        .barra-acciones { display: flex; gap: 10px; justify-content: center; margin-top: 22px; }
        .barra-acciones button, .barra-acciones a { background-color: #2563eb; color: #ffffff; border: none; border-radius: 8px; padding: 11px 20px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .barra-acciones .secundario { background-color: #334155; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 40px; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .factura { box-shadow: none; max-width: 100%; border-radius: 0; padding: 0; }
            .barra-acciones { display: none; }
        }
    </style>
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <?php if (!$factura): ?>
        <div class="estado-vacio"><?php echo $texto['no_existe']; ?></div>
    <?php else: ?>
        <div class="factura">
            <div class="factura-cabecera">
                <div>
                    <div class="empresa-nombre"><?php echo $texto['empresa']; ?></div>
                    <div class="empresa-dato"><?php echo $texto['proyecto']; ?></div>
                    <div class="empresa-dato"><?php echo $texto['ruta']; ?></div>
                </div>
                <div class="factura-numero">
                    <strong><?php echo $texto['numero']; ?> <?php echo htmlspecialchars($factura['numero']); ?></strong>
                    <span><?php echo $texto['fecha']; ?>: <?php echo htmlspecialchars($factura['fecha_emision']); ?></span>
                </div>
            </div>

            <div class="bloque">
                <h3><?php echo $texto['cliente']; ?></h3>
                <div class="datos">
                    <div><span><?php echo $texto['cliente']; ?></span><span><?php echo htmlspecialchars(trim(($factura['nombre'] ?? '') . ' ' . ($factura['apellido'] ?? ''))); ?></span></div>
                    <div><span><?php echo $texto['ci']; ?></span><span><?php echo htmlspecialchars($factura['cedula_usuario']); ?></span></div>
                    <div><span><?php echo $texto['email']; ?></span><span><?php echo htmlspecialchars($factura['email'] ?? '-'); ?></span></div>
                    <div><span><?php echo $texto['telefono']; ?></span><span><?php echo htmlspecialchars($factura['telefono'] ?? '-'); ?></span></div>
                </div>
            </div>

            <div class="bloque">
                <h3><?php echo $texto['detalle']; ?></h3>
                <div class="datos">
                    <div><span><?php echo $texto['servicio']; ?></span><span><?php echo htmlspecialchars($factura['servicio_nombre'] ?? '-'); ?></span></div>
                    <div><span><?php echo $texto['sede']; ?></span><span><?php echo htmlspecialchars($factura['taller_nombre'] ?? '-'); ?></span></div>
                    <div>
                        <span><?php echo $texto['vehiculo']; ?></span>
                        <span>
                            <?php echo htmlspecialchars(trim(($factura['marca'] ?? '') . ' ' . ($factura['modelo'] ?? ''))); ?>
                            (<?php echo htmlspecialchars($factura['patente'] ?? '-'); ?>)
                        </span>
                    </div>
                    <div><span><?php echo $lang_code === 'en' ? 'Date' : 'Fecha'; ?></span><span><?php echo htmlspecialchars($factura['fecha_reserva'] ?? '-'); ?> <?php echo htmlspecialchars(substr((string) ($factura['hora_reserva'] ?? ''), 0, 5)); ?></span></div>
                </div>
            </div>

            <div class="bloque">
                <h3><?php echo $texto['items']; ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo $texto['descripcion'] ?? $texto['items']; ?></th>
                            <th class="num"><?php echo $texto['cantidad'] ?? 'Cant.'; ?></th>
                            <th class="num"><?php echo $texto['precio'] ?? 'Precio'; ?></th>
                            <th class="num"><?php echo $texto['subtotal'] ?? 'Subtotal'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="4"><?php echo $factura['servicio_nombre'] ?? $texto['sin_items']; ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                                    <td class="num"><?php echo (int) $item['cantidad']; ?></td>
                                    <td class="num"><?php echo htmlspecialchars(number_format((float) $item['precio_unitario'], 2, ',', '.')); ?></td>
                                    <td class="num"><?php echo htmlspecialchars(number_format((float) $item['subtotal'], 2, ',', '.')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="bloque">
                <h3><?php echo $texto['pagos']; ?></h3>
                <?php if (empty($cobros)): ?>
                    <div class="datos"><div><span><?php echo $texto['sin_pagos']; ?></span><span>-</span></div></div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $texto['metodo']; ?></th>
                                <th><?php echo $texto['referencia']; ?></th>
                                <th><?php echo $texto['fecha']; ?></th>
                                <th class="num"><?php echo $texto['monto'] ?? 'Monto'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cobros as $cobro): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($metodos[$cobro['metodo'] ?? ''] ?? ($cobro['metodo'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($cobro['referencia'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cobro['fecha_pago'] ?? '-'); ?></td>
                                    <td class="num"><?php echo htmlspecialchars(number_format((float) ($cobro['monto'] ?? 0), 2, ',', '.')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="totales">
                <div><span><?php echo $texto['total']; ?></span><span><?php echo number_format($total_factura, 2, ',', '.'); ?> USD</span></div>
                <div><span><?php echo $texto['pagos']; ?></span><span><?php echo number_format($total_pagado, 2, ',', '.'); ?> USD</span></div>
                <div class="total-final"><span><?php echo $texto['saldo']; ?></span><span><?php echo number_format($saldo, 2, ',', '.'); ?> USD</span></div>
            </div>

            <div class="pie-factura">
                <?php echo $texto['gracias']; ?><br>
                <?php echo $texto['empresa']; ?> · <?php echo $texto['proyecto']; ?>
            </div>
        </div>

        <div class="barra-acciones">
            <button type="button" onclick="window.print()"><?php echo $texto['descargar']; ?></button>
            <a class="secundario" href="<?php echo $es_cliente ? 'pagos_cliente.php' : 'administrar_pagos.php'; ?>"><?php echo $texto['volver']; ?></a>
        </div>
    <?php endif; ?>
</body>
</html>
