<?php
/**
 * TGS - Taller Global de Servicios
 * Panel personal del cliente autenticado.
 *
 * Muestra en un solo lugar todo lo del cliente: sus turnos (con el estado
 * de cobro), sus vehiculos, sus presupuestos y los ultimos avisos que le
 * mando el sistema. Es el punto de entrada del cliente: desde aca se llega
 * a reservar, editar o cancelar turnos (reservas.php), a responder los
 * presupuestos (presupuestos.php) y a modificar sus datos (perfil.php).
 *
 * Reglas de negocio:
 * - Solo el rol cliente accede (verificarPermiso con 'ver_panel_cliente').
 * - Se muestran unicamente los datos del usuario de la sesion: todas las
 *   consultas filtran por la cedula de la sesion.
 *
 * Dependencias: auth.php, conexion.php
 */
require_once 'auth.php';
verificarPermiso('ver_panel_cliente');
require_once 'conexion.php';

// Define los textos básicos del panel según el idioma elegido
$lang_code = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : ($_SESSION['lang'] ?? 'es');
$_SESSION['lang'] = $lang_code;

$cedula_usuario = $_SESSION['cedula'] ?? '';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

$texto = $lang_code === 'en' ? [
    'bienvenida' => 'Welcome', 'salir' => 'Sign Out', 'panel' => 'Customer Panel', 'perfil' => 'My Profile',
    'reservar' => 'Book an appointment', 'mis_turnos' => 'My appointments', 'mis_vehiculos' => 'My vehicles',
    'mis_presupuestos' => 'My budgets', 'avisos' => 'Recent notices', 'mis_pagos' => 'My payments',
    'resumen' => 'Everything about your account: appointments, vehicles, budgets and payments.',
    'turnos' => 'Appointments', 'vehiculo' => 'Vehicle', 'servicio' => 'Service', 'fecha' => 'Date', 'hora' => 'Time',
    'estado' => 'Status', 'pago' => 'Payment', 'total' => 'Total', 'pendiente' => 'Pending', 'en_curso' => 'In progress',
    'finalizado' => 'Completed', 'cancelado' => 'Cancelled', 'sin_turnos' => 'You have no appointments yet.',
    'sin_vehiculos' => 'You have no vehicles registered yet.', 'sin_presupuestos' => 'You have no budgets yet.',
    'sin_avisos' => 'No notices yet.', 'a_pagar' => 'Amount to pay', 'total_todos' => 'Appointments',
    'editar_vehiculo' => 'Edit', 'ver_todo' => 'See all', 'responder' => 'Answer',
    'pagado' => 'Paid', 'parcial' => 'Partial', 'pendiente_pago' => 'Pending payment', 'consultar' => 'Consult',
    'estado_reserva' => ['Pendiente' => 'Pending', 'En curso' => 'In progress', 'Finalizada' => 'Completed', 'Cancelada' => 'Cancelled'],
    'marca' => 'Global Workshop Services', 'derechos' => 'All rights reserved.'
] : [
    'bienvenida' => 'Bienvenido', 'salir' => 'Cerrar Sesión', 'panel' => 'Panel de Cliente', 'perfil' => 'Mi Perfil',
    'reservar' => 'Reservar un turno', 'mis_turnos' => 'Mis turnos', 'mis_vehiculos' => 'Mis vehículos',
    'mis_presupuestos' => 'Mis presupuestos', 'avisos' => 'Últimos avisos', 'mis_pagos' => 'Mis pagos',
    'resumen' => 'Todo sobre tu cuenta: turnos, vehículos, presupuestos y pagos.',
    'turnos' => 'Turnos', 'vehiculo' => 'Vehículo', 'servicio' => 'Servicio', 'fecha' => 'Fecha', 'hora' => 'Hora',
    'estado' => 'Estado', 'pago' => 'Pago', 'total' => 'Total', 'pendiente' => 'Pendientes', 'en_curso' => 'En curso',
    'finalizado' => 'Finalizados', 'cancelado' => 'Cancelados', 'sin_turnos' => 'Todavía no tenés turnos agendados.',
    'sin_vehiculos' => 'Todavía no tenés vehículos registrados.', 'sin_presupuestos' => 'Todavía no tenés presupuestos.',
    'sin_avisos' => 'Todavía no hay avisos.', 'a_pagar' => 'Importe a pagar', 'total_todos' => 'Turnos totales',
    'editar_vehiculo' => 'Editar', 'ver_todo' => 'Ver todo', 'responder' => 'Responder',
    'pagado' => 'Pagado', 'parcial' => 'Parcial', 'pendiente_pago' => 'Pago pendiente', 'consultar' => 'Consultar',
    'estado_reserva' => ['Pendiente' => 'Pendiente', 'En curso' => 'En curso', 'Finalizada' => 'Finalizada', 'Cancelada' => 'Cancelada'],
    'marca' => 'Taller Global de Servicios', 'derechos' => 'Todos los derechos reservados.'
];

// ---------------------------------------------------------------------------
// Datos del cliente
// ---------------------------------------------------------------------------

// Turnos del cliente con su estado de cobro.
$turnos = [];
try {
    $sql = "SELECT r.id_reserva, r.fecha_reserva, r.hora_reserva, r.estado, r.precio_total, r.estado_pago,
                   v.marca, v.modelo, v.patente, s.nombre AS servicio_nombre,
                   COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado'), 0) AS total_pagado
            FROM reserva r
            LEFT JOIN vehiculo v ON v.id_vehiculo = r.id_vehiculo
            LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
            WHERE r.cedula_usuario = :cedula_usuario
            ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC
            LIMIT 8";
    $stmt = $conexion->prepare($sql);
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $turnos = [];
}

// Vehiculos del cliente.
$vehiculos = [];
try {
    $stmt = $conexion->prepare('SELECT * FROM vehiculo WHERE cedula_usuario = :cedula_usuario ORDER BY id_vehiculo');
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vehiculos = [];
}

// Conteos por estado e importe pendiente de pago.
$resumen = ['total' => 0, 'pendiente' => 0, 'en_curso' => 0, 'finalizadas' => 0, 'a_pagar' => 0.0];
try {
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) AS pendiente,
                SUM(CASE WHEN estado = 'En curso' THEN 1 ELSE 0 END) AS en_curso,
                SUM(CASE WHEN estado = 'Finalizada' THEN 1 ELSE 0 END) AS finalizadas
         FROM reserva WHERE cedula_usuario = :cedula_usuario"
    );
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fila) {
        $resumen['total'] = (int) $fila['total'];
        $resumen['pendiente'] = (int) $fila['pendiente'];
        $resumen['en_curso'] = (int) $fila['en_curso'];
        $resumen['finalizadas'] = (int) $fila['finalizadas'];
    }

    // Lo que falta pagar: total de los turnos no cancelados menos lo cobrado.
    # GREATEST (y no MAX) porque MySQL toma MAX con dos argumentos como
    # agregacion y eso rompe la consulta dentro de un SUM.
    $stmt = $conexion->prepare(
        "SELECT COALESCE(SUM(GREATEST(COALESCE(r.precio_total, 0), 0) - COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado'), 0)), 0)
         FROM reserva r
         WHERE r.cedula_usuario = :cedula_usuario AND r.estado <> 'Cancelada'"
    );
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $resumen['a_pagar'] = (float) $stmt->fetchColumn();
} catch (PDOException $e) {
    // Se muestran los valores en cero si la consulta falla.
}

// Presupuestos del cliente (los ultimos).
$presupuestos = [];
try {
    $stmt = $conexion->prepare(
        'SELECT id_presupuesto, id_reserva, fecha_presupuesto, total, estado FROM presupuesto
         WHERE cedula_usuario = :cedula_usuario ORDER BY id_presupuesto DESC LIMIT 5'
    );
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $presupuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $presupuestos = [];
}

// Ultimos avisos recibidos por el cliente.
$avisos = [];
try {
    $stmt = $conexion->prepare(
        'SELECT id_notificacion, asunto, tipo, estado, created_at FROM notificacion
         WHERE cedula_usuario = :cedula_usuario ORDER BY id_notificacion DESC LIMIT 5'
    );
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avisos = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['panel']; ?> - TGS</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/tema-tgs.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        header h1 { font-size: 20px; color: #38bdf8; }
        header .acciones { display: flex; align-items: center; gap: 12px; }
        header .acciones a { color: #cbd5e1; text-decoration: none; font-size: 14px; }
        header .acciones a:hover { color: #38bdf8; }
        main { flex: 1; padding: 30px 20px 50px; max-width: 1200px; width: 100%; margin: 0 auto; }
        .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 26px; }
        .tarjeta-resumen { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 16px 18px; }
        .tarjeta-resumen span { display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .tarjeta-resumen strong { font-size: 22px; color: #38bdf8; }
        .tarjeta-resumen strong.alerta { color: #fbbf24; }
        .bloque { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 20px 22px; margin-bottom: 22px; }
        .bloque h2 { font-size: 16px; color: #38bdf8; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .bloque h2 a { font-size: 13px; color: #94a3b8; text-decoration: none; font-weight: 600; }
        .bloque h2 a:hover { color: #38bdf8; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; padding: 10px; text-align: left; font-weight: 600; }
        td { padding: 10px; border-bottom: 1px solid #334155; color: #cbd5e1; }
        .tabla-scroll { overflow-x: auto; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.pendiente { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .badge.en-curso { background: rgba(56, 189, 248, 0.15); color: #7dd3fc; }
        .badge.finalizada, .badge.pagado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.cancelada, .badge.parcial { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .badge.aceptado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.rechazado { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 20px; font-size: 13px; }
        .btn-panel { display: inline-block; background: #2563eb; color: white; border-radius: 8px; padding: 9px 16px; font-size: 13px; font-weight: 600; text-decoration: none; }
        .btn-panel:hover { background: #1d4ed8; }
        .btn-panel.secundario { background: #334155; color: #cbd5e1; }
        .btn-panel.secundario:hover { background: #475569; }
        .acciones-inicio { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
        .lista-simple { list-style: none; }
        .lista-simple li { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #334155; font-size: 13px; color: #cbd5e1; }
        .lista-simple li:last-child { border-bottom: none; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
</head>
<body>
    <header>
        <h1><?php echo $texto['bienvenida']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></h1>
        <div class="acciones">
            <a href="reservas.php"><?php echo $texto['mis_turnos']; ?></a>
            <a href="presupuestos.php"><?php echo $texto['mis_presupuestos']; ?></a>
            <a href="pagos_cliente.php"><?php echo $texto['mis_pagos']; ?></a>
            <a href="perfil.php"><?php echo $texto['perfil']; ?></a>
            <a href="panel_cliente.php?lang=es">ES</a>
            <a href="panel_cliente.php?lang=en">EN</a>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </div>
    </header>

    <main>
        <div class="acciones">
            <a href="reservas.php" class="btn-panel"><?php echo $texto['reservar']; ?></a>
            <a href="pagos_cliente.php" class="btn-panel secundario"><?php echo $texto['mis_pagos']; ?></a>
            <a href="presupuestos.php" class="btn-panel secundario"><?php echo $texto['mis_presupuestos']; ?></a>
            <a href="perfil.php" class="btn-panel secundario"><?php echo $texto['perfil']; ?></a>
        </div>

        <!-- Resumen de la cuenta -->
        <div class="resumen-grid">
            <div class="tarjeta-resumen">
                <span><?php echo $texto['total_todos']; ?></span>
                <strong><?php echo $resumen['total']; ?></strong>
            </div>
            <div class="tarjeta-resumen">
                <span><?php echo $texto['pendiente']; ?></span>
                <strong><?php echo $resumen['pendiente']; ?></strong>
            </div>
            <div class="tarjeta-resumen">
                <span><?php echo $texto['en_curso']; ?></span>
                <strong><?php echo $resumen['en_curso']; ?></strong>
            </div>
            <div class="tarjeta-resumen">
                <span><?php echo $texto['finalizado']; ?></span>
                <strong><?php echo $resumen['finalizadas']; ?></strong>
            </div>
            <div class="tarjeta-resumen">
                <span><?php echo $texto['a_pagar']; ?></span>
                <strong class="<?php echo $resumen['a_pagar'] > 0.009 ? 'alerta' : ''; ?>"><?php echo htmlspecialchars(number_format($resumen['a_pagar'], 2, ',', '.')); ?> USD</strong>
            </div>
        </div>

        <!-- Turnos del cliente -->
        <div class="bloque">
            <h2>
                <span><?php echo $texto['turnos']; ?></span>
                <a href="reservas.php"><?php echo $texto['ver_todo']; ?> &rarr;</a>
            </h2>
            <?php if (count($turnos) > 0): ?>
                <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $texto['fecha']; ?></th>
                                <th><?php echo $texto['hora']; ?></th>
                                <th><?php echo $texto['servicio']; ?></th>
                                <th><?php echo $texto['vehiculo']; ?></th>
                                <th><?php echo $texto['estado']; ?></th>
                                <th><?php echo $texto['pago']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($turnos as $turno):
                                $estado_turno = strtolower(trim((string) ($turno['estado'] ?? 'Pendiente')));
                                $estado_pago = strtolower(trim((string) ($turno['estado_pago'] ?? 'pendiente')));
                                $vehiculo_turno = trim(($turno['marca'] ?? '') . ' ' . ($turno['modelo'] ?? '')) ?: '-';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($turno['fecha_reserva'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(substr((string) ($turno['hora_reserva'] ?? ''), 0, 5)); ?></td>
                                    <td><?php echo htmlspecialchars($turno['servicio_nombre'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($vehiculo_turno); ?></td>
                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($estado_turno); ?>">
                                            <?php echo htmlspecialchars($texto['estado_reserva'][$turno['estado'] ?? ''] ?? ($turno['estado'] ?? '-')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($estado_pago); ?>">
                                            <?php echo htmlspecialchars($texto[$estado_pago] ?? $estado_pago); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="estado-vacio"><?php echo $texto['sin_turnos']; ?></p>
            <?php endif; ?>
        </div>

        <!-- Vehiculos del cliente -->
        <div class="bloque">
            <h2>
                <span><?php echo $texto['mis_vehiculos']; ?></span>
                <a href="reservas.php#mis-vehiculos"><?php echo $texto['ver_todo']; ?> &rarr;</a>
            </h2>
            <?php if (count($vehiculos) > 0): ?>
                <ul class="lista-simple">
                    <?php foreach ($vehiculos as $vehiculo): ?>
                        <li>
                            <span>
                                <strong><?php echo htmlspecialchars(trim(($vehiculo['marca'] ?? '') . ' ' . ($vehiculo['modelo'] ?? ''))); ?></strong>
                                &middot; <?php echo htmlspecialchars($vehiculo['patente'] ?? 'Sin patente'); ?>
                                <?php if (!empty($vehiculo['anio'])): ?>
                                    &middot; <?php echo htmlspecialchars($vehiculo['anio']); ?>
                                <?php endif; ?>
                            </span>
                            <a href="reservas.php#mis-vehiculos" class="btn-panel secundario"><?php echo $texto['editar_vehiculo']; ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="estado-vacio"><?php echo $texto['sin_vehiculos']; ?></p>
            <?php endif; ?>
        </div>

        <!-- Presupuestos del cliente -->
        <div class="bloque">
            <h2>
                <span><?php echo $texto['mis_presupuestos']; ?></span>
                <a href="presupuestos.php"><?php echo $texto['ver_todo']; ?> &rarr;</a>
            </h2>
            <?php if (count($presupuestos) > 0): ?>
                <ul class="lista-simple">
                    <?php foreach ($presupuestos as $presupuesto):
                        $estado_presupuesto = strtolower(trim((string) ($presupuesto['estado'] ?? 'pendiente')));
                    ?>
                        <li>
                            <span>
                                #<?php echo htmlspecialchars($presupuesto['id_presupuesto'] ?? ''); ?>
                                &middot; <?php echo htmlspecialchars($presupuesto['fecha_presupuesto'] ?? '-'); ?>
                                &middot; <?php echo htmlspecialchars(number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.')); ?> USD
                            </span>
                            <span class="badge <?php echo htmlspecialchars($estado_presupuesto); ?>">
                                <?php echo htmlspecialchars($estado_presupuesto); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="estado-vacio"><?php echo $texto['sin_presupuestos']; ?></p>
            <?php endif; ?>
        </div>

        <!-- Ultimos avisos recibidos -->
        <div class="bloque">
            <h2><span><?php echo $texto['avisos']; ?></span></h2>
            <?php if (count($avisos) > 0): ?>
                <ul class="lista-simple">
                    <?php foreach ($avisos as $aviso): ?>
                        <li>
                            <span>
                                <?php echo htmlspecialchars($aviso['asunto'] ?? ''); ?>
                                <span style="color: #64748b;">&middot; <?php echo htmlspecialchars($aviso['created_at'] ?? ''); ?></span>
                            </span>
                            <span class="badge <?php echo htmlspecialchars(strtolower((string) ($aviso['estado'] ?? 'pendiente'))); ?>">
                                <?php echo htmlspecialchars($aviso['estado'] ?? ''); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="estado-vacio"><?php echo $texto['sin_avisos']; ?></p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $texto['marca']; ?>. <?php echo $texto['derechos']; ?></p>
    </footer>
</body>
</html>
