<?php
/**
 * TGS - Taller Global de Servicios
 * Historial de servicios del cliente.
 *
 * Muestra los servicios realizados con anterioridad: por cada vehiculo del
 * cliente, las visitas, con el taller, la fecha, el detalle de lo que se
 * hizo, el costo y los cobros. Es el historial que exige el enunciado
 * (apartado 3.5 y 6 de los requisitos del cliente).
 *
 * El cliente ve su propio historial; gestores y administradores pueden
 * ver el de cualquier cliente (parametro ?cedula=).
 *
 * Dependencias: auth.php, conexion.php, funciones_pago.php
 */
require_once 'auth.php';
verificarPermiso('ver_historial');
require_once 'conexion.php';
require_once 'funciones_pago.php';

$lang_code = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : ($_SESSION['lang'] ?? 'es');
$_SESSION['lang'] = $lang_code;

$cedula_usuario = $_SESSION['cedula'] ?? '';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$es_cliente = ($rol_actual === 'cliente');

$texto = $lang_code === 'en' ? [
    'titulo' => 'Service history', 'hola' => 'Hello', 'salir' => 'Sign Out', 'perfil' => 'My profile', 'pagos' => 'My payments',
    'resumen' => 'Services already performed on your vehicles, with the workshop, the date, the detail and the cost.',
    'reservas' => 'appointments', 'vehiculo' => 'Vehicle', 'sede' => 'Workshop', 'servicio' => 'Service',
    'detalle' => 'Detail', 'costo' => 'Cost', 'fecha' => 'Date', 'estado' => 'Status', 'factura' => 'Invoice',
    'sin_historial' => 'You have no completed services yet.', 'sin_registros' => 'No records for this client.',
    'total_servicios' => 'Services performed', 'gasto_total' => 'Total spent', 'ultimo' => 'Last service',
    'filtro_vehiculo' => 'All vehicles', 'estados' => ['Pendiente' => 'Pending', 'En curso' => 'In progress', 'Finalizada' => 'Completed', 'Cancelada' => 'Cancelled'],
    'pagos' => 'Payments', 'sin_pagos' => 'No payments'
] : [
    'titulo' => 'Historial de servicios', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión', 'perfil' => 'Mi perfil', 'pagos' => 'Mis pagos',
    'resumen' => 'Servicios ya realizados sobre tus vehículos, con el taller, la fecha, el detalle y el costo.',
    'reservas' => 'visitas', 'vehiculo' => 'Vehículo', 'sede' => 'Taller', 'servicio' => 'Servicio',
    'detalle' => 'Detalle', 'costo' => 'Costo', 'fecha' => 'Fecha', 'estado' => 'Estado', 'factura' => 'Factura',
    'sin_historial' => 'Todavía no tenés servicios realizados.', 'sin_registros' => 'No hay registros para este cliente.',
    'total_servicios' => 'Servicios realizados', 'gasto_total' => 'Gasto total', 'ultimo' => 'Último servicio',
    'filtro_vehiculo' => 'Todos los vehículos', 'estados' => ['Pendiente' => 'Pendiente', 'En curso' => 'En curso', 'Finalizada' => 'Finalizada', 'Cancelada' => 'Cancelada'],
    'pagos' => 'Cobros', 'sin_pagos' => 'Sin cobros'
];

// A quien se le muestra el historial.
$cedula_consultada = $es_cliente ? $cedula_usuario : trim((string) ($_GET['cedula'] ?? ''));
if (!$es_cliente && $cedula_consultada === '') {
    // Si es personal del taller y no se indica cliente, se listan los clientes.
    try {
        $stmt = $conexion->query("SELECT cedula, nombre, apellido, email FROM usuario WHERE rol = 'cliente' ORDER BY nombre");
        $clientes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        $clientes = [];
    }
} else {
    $clientes = [];
}

// Filtro por vehiculo.
$vehiculo_filtro = filter_input(INPUT_GET, 'vehiculo', FILTER_VALIDATE_INT) ?: 0;

$vehiculos = [];
$servicios = [];
$resumen_historial = ['total' => 0, 'gasto' => 0.0, 'ultimo' => ''];

if ($cedula_consultada !== '') {
    try {
        $stmt = $conexion->prepare('SELECT * FROM vehiculo WHERE cedula_usuario = :cedula ORDER BY id_vehiculo');
        $stmt->execute(['cedula' => $cedula_consultada]);
        $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Historial: todas las visitas con su detalle y su costo.
        $sql = "SELECT r.id_reserva, r.fecha_reserva, r.hora_reserva, r.estado, r.precio_total, r.estado_pago,
                       r.id_vehiculo, v.marca, v.modelo, v.patente,
                       s.nombre AS servicio_nombre, t.nombre AS taller_nombre,
                       COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado'), 0) AS total_pagado,
                       (SELECT id_presupuesto FROM presupuesto WHERE id_reserva = r.id_reserva ORDER BY id_presupuesto DESC LIMIT 1) AS id_presupuesto
                FROM reserva r
                LEFT JOIN vehiculo v ON v.id_vehiculo = r.id_vehiculo
                LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
                LEFT JOIN taller t ON t.id_taller = r.id_taller
                WHERE r.cedula_usuario = :cedula AND r.estado <> 'Cancelada'";
        $params = ['cedula' => $cedula_consultada];

        if ($vehiculo_filtro) {
            $sql .= ' AND r.id_vehiculo = :id_vehiculo';
            $params['id_vehiculo'] = $vehiculo_filtro;
        }
        $sql .= ' ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC';

        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $servicios = [];
    }

    // Detalle de los items cotizados de cada visita (presupuesto asociado).
    $detalle_por_servicio = [];
    foreach ($servicios as $indice => $servicio) {
        $detalle_por_servicio[$indice] = [];
        if (!empty($servicio['id_presupuesto'])) {
            try {
                $stmt_items = $conexion->prepare('SELECT descripcion, cantidad, precio_unitario, subtotal FROM presupuesto_detalle WHERE id_presupuesto = :id_presupuesto ORDER BY id_detalle');
                $stmt_items->execute(['id_presupuesto' => (int) $servicio['id_presupuesto']]);
                $detalle_por_servicio[$indice] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $detalle_por_servicio[$indice] = [];
            }
        }
    }

    // Resumen: solo los servicios finalizados cuentan como realizados.
    foreach ($servicios as $servicio) {
        if (strtolower((string) ($servicio['estado'] ?? '')) === 'finalizada') {
            $resumen_historial['total']++;
            $resumen_historial['gasto'] += (float) ($servicio['precio_total'] ?? 0);
            if ($resumen_historial['ultimo'] === '' || (string) $servicio['fecha_reserva'] > $resumen_historial['ultimo']) {
                $resumen_historial['ultimo'] = (string) $servicio['fecha_reserva'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
    <link rel="stylesheet" href="css/tgs-panel.css">
    <style>
        .acciones-cabecera { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .nav-enlace { color: #cbd5e1; text-decoration: none; font-size: 14px; }
        .nav-enlace:hover { color: #38bdf8; }
        .btn-salir { background-color: #ef4444; color: #ffffff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-salir:hover { background-color: #dc2626; }
        .rejilla { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .filtros { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
        .filtros select { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 9px 12px; font-size: 13px; }
        .servicio { border: 1px solid #334155; border-radius: 10px; padding: 16px 18px; margin-bottom: 12px; background: rgba(3, 9, 18, 0.35); }
        .servicio-cabecera { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
        .servicio-titulo { font-size: 15px; color: #38bdf8; font-weight: 700; }
        .servicio-meta { font-size: 13px; color: #94a3b8; margin-top: 3px; }
        .detalle { margin: 10px 0; padding: 10px 12px; background: rgba(15, 23, 42, 0.55); border-radius: 8px; font-size: 13px; color: #cbd5e1; }
        .detalle li { margin-bottom: 3px; }
        .detalle ul { margin: 0; padding-left: 18px; }
        .costes { display: flex; gap: 18px; flex-wrap: wrap; font-size: 13px; color: #cbd5e1; margin-top: 8px; }
        .costes b { color: #f8fafc; }
        .importe { color: #fbbf24; font-weight: 700; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 30px; font-size: 14px; }
    </style>
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body class="tgs">
    <header class="tgs-header">
        <a href="index.php" class="marca">TGS - Blue Tech Uruguay</a>
        <div class="acciones-cabecera">
            <?php if ($es_cliente): ?>
                <a href="panel_cliente.php" class="nav-enlace"><?php echo $texto['titulo']; ?></a>
                <a href="pagos_cliente.php" class="nav-enlace"><?php echo $texto['pagos']; ?></a>
                <a href="perfil.php" class="nav-enlace"><?php echo $texto['perfil']; ?></a>
            <?php else: ?>
                <a href="administrar_pagos.php" class="nav-enlace"><?php echo $texto['pagos']; ?></a>
            <?php endif; ?>
            <div class="tgs-idioma">
                <a href="historial_cliente.php?lang=es<?php echo $cedula_consultada ? '&cedula=' . htmlspecialchars($cedula_consultada) : ''; ?>" class="<?php echo $lang_code === 'es' ? 'activo' : ''; ?>">ES</a>
                <a href="historial_cliente.php?lang=en<?php echo $cedula_consultada ? '&cedula=' . htmlspecialchars($cedula_consultada) : ''; ?>" class="<?php echo $lang_code === 'en' ? 'activo' : ''; ?>">EN</a>
            </div>
            <span class="usuario"><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? ''); ?></strong></span>
            <a href="logout.php" class="btn-salir"><?php echo $texto['salir']; ?></a>
        </div>
    </header>

    <main class="tgs-contenedor">
        <div class="tgs-titulo">
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!$es_cliente && $cedula_consultada === ''): ?>
            <!-- Personal del taller: elegir a que cliente se le muestra el historial -->
            <div class="tgs-tarjeta">
                <h3><?php echo $lang_code === 'en' ? 'Select a customer' : 'Elegir cliente'; ?></h3>
                <?php if (empty($clientes)): ?>
                    <p class="estado-vacio"><?php echo $texto['sin_registros']; ?></p>
                <?php else: ?>
                    <?php foreach ($clientes as $cliente): ?>
                        <div class="dato">
                            <span><?php echo htmlspecialchars(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''))); ?></span>
                            <a href="historial_cliente.php?cedula=<?php echo htmlspecialchars($cliente['cedula']); ?>" class="tgs-btn chico"><?php echo $lang_code === 'en' ? 'View' : 'Ver'; ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <div class="rejilla">
                <div class="tgs-metrica"><span><?php echo $texto['total_servicios']; ?></span><strong><?php echo $resumen_historial['total']; ?></strong></div>
                <div class="tgs-metrica"><span><?php echo $texto['gasto_total']; ?></span><strong><?php echo htmlspecialchars(number_format($resumen_historial['gasto'], 2, ',', '.')); ?> USD</strong></div>
                <div class="tgs-metrica"><span><?php echo $texto['ultimo']; ?></span><strong><?php echo $resumen_historial['ultimo'] !== '' ? htmlspecialchars($resumen_historial['ultimo']) : '-'; ?></strong></div>
            </div>

            <?php if (!empty($vehiculos)): ?>
                <form method="GET" class="filtros">
                    <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($cedula_consultada); ?>">
                    <select name="vehiculo">
                        <option value="0"><?php echo $texto['filtro_vehiculo']; ?></option>
                        <?php foreach ($vehiculos as $vehiculo): ?>
                            <option value="<?php echo (int) $vehiculo['id_vehiculo']; ?>" <?php echo $vehiculo_filtro === (int) $vehiculo['id_vehiculo'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(trim(($vehiculo['marca'] ?? '') . ' ' . ($vehiculo['modelo'] ?? '')) . ' (' . ($vehiculo['patente'] ?? '') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="tgs-btn"><?php echo $lang_code === 'en' ? 'Filter' : 'Filtrar'; ?></button>
                </form>
            <?php endif; ?>

            <?php if (empty($servicios)): ?>
                <div class="tgs-tarjeta estado-vacio"><?php echo $texto['sin_historial']; ?></div>
            <?php else: ?>
                <?php foreach ($servicios as $indice => $servicio):
                    $detalle_items = $detalle_por_servicio[$indice] ?? [];
                    $vehiculo_nombre = trim(($servicio['marca'] ?? '') . ' ' . ($servicio['modelo'] ?? ''));
                    $factura_reserva = tgs_factura_de_reserva($conexion, (int) $servicio['id_reserva']);
                    $estado_historial = trim((string) ($servicio['estado'] ?? ''));
                ?>
                    <div class="servicio">
                        <div class="servicio-cabecera">
                            <div>
                                <div class="servicio-titulo"><?php echo htmlspecialchars($servicio['servicio_nombre'] ?? '-'); ?></div>
                                <div class="servicio-meta">
                                    <?php echo $texto['fecha']; ?>:
                                    <?php echo htmlspecialchars($servicio['fecha_reserva'] ?? '-'); ?>
                                    <?php echo htmlspecialchars(substr((string) ($servicio['hora_reserva'] ?? ''), 0, 5)); ?>
                                    &middot; <?php echo $texto['sede']; ?>: <?php echo htmlspecialchars($servicio['taller_nombre'] ?? '-'); ?>
                                    &middot; <?php echo $texto['vehiculo']; ?>: <?php echo htmlspecialchars($vehiculo_nombre); ?> (<?php echo htmlspecialchars($servicio['patente'] ?? '-'); ?>)
                                </div>
                            </div>
                            <span class="tgs-badge <?php echo strtolower(str_replace(' ', '-', $estado_historial)); ?>"><?php echo htmlspecialchars($texto['estados'][$estado_historial] ?? $estado_historial); ?></span>
                        </div>

                        <?php if (!empty($detalle_items)): ?>
                            <div class="detalle">
                                <strong><?php echo $lang_code === 'en' ? 'Detail' : 'Detalle'; ?>:</strong>
                                <ul>
                                    <?php foreach ($detalle_items as $item): ?>
                                        <li>
                                            <?php echo htmlspecialchars($item['descripcion']); ?>
                                            &times; <?php echo (int) $item['cantidad']; ?>
                                            = <?php echo htmlspecialchars(number_format((float) $item['subtotal'], 2, ',', '.')); ?> USD
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="costes">
                            <span><?php echo $texto['costo']; ?>: <b><?php echo htmlspecialchars(number_format((float) ($servicio['precio_total'] ?? 0), 2, ',', '.')); ?> USD</b></span>
                            <span><?php echo $texto['pagos']; ?>: <b><?php echo htmlspecialchars(number_format((float) ($servicio['total_pagado'] ?? 0), 2, ',', '.')); ?> USD</b></span>
                            <?php if ((float) ($servicio['precio_total'] ?? 0) - (float) ($servicio['total_pagado'] ?? 0) > 0.009): ?>
                                <span class="importe"><?php echo ($lang_code === 'en' ? 'Pending' : 'Pendiente'); ?>: <?php echo htmlspecialchars(number_format((float) ($servicio['precio_total'] ?? 0) - (float) ($servicio['total_pagado'] ?? 0), 2, ',', '.')); ?> USD</span>
                            <?php endif; ?>
                            <?php if ($factura_reserva): ?>
                                <a href="factura.php?id=<?php echo (int) $factura_reserva['id_factura']; ?>" class="tgs-btn chico" target="_blank"><?php echo $texto['factura']; ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="tgs-pie">
        <p>&copy; 2026 TGS - <?php echo $lang_code === 'en' ? 'Global Workshop Services. All rights reserved.' : 'Taller Global de Servicios. Todos los derechos reservados.'; ?></p>
    </footer>
</body>
</html>
