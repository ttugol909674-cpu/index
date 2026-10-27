<?php
/**
 * TGS - Taller Global de Servicios
 * Presupuestos del cliente.
 *
 * Pantalla donde el cliente revisa las propuestas de trabajo enviadas por el
 * taller y decide: acepta el presupuesto o lo rechaza.
 *
 * Reglas de negocio:
 * - Solo se ven los presupuestos del usuario de la sesion.
 * - Solo un presupuesto pendiente se puede aceptar o rechazar.
 * - Al aceptar, el total acordado pasa a ser el importe a cobrar de la
 *   reserva (reserva.precio_total) y el cobro vuelve a quedar pendiente.
 *
 * Seguridad:
 * - Token CSRF en cada accion.
 * - Consultas preparadas: el id del presupuesto siempre se filtra por la
 *   cedula del usuario de la sesion, nunca solo por su id.
 * - Salida de datos con htmlspecialchars.
 *
 * Dependencias: auth.php, conexion.php
 */
require_once 'auth.php';
verificarPermiso('ver_presupuestos_propios');
require_once 'conexion.php';
require_once 'formato.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$cedula_usuario = $_SESSION['cedula'] ?? '';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));

// El cliente vuelve a sus turnos; el gestor o admin, a su panel.
$panel_volver = 'reservas.php';
if ($rol_actual === 'admin') {
    $panel_volver = 'dashboard_admin.php';
} elseif ($rol_actual === 'gestor') {
    $panel_volver = 'dashboard_gestor.php';
}

// Textos traducidos.
$texto = $lang_code === 'en' ? [
    'titulo' => 'My Budgets', 'hola' => 'Hello', 'salir' => 'Sign Out', 'volver' => 'Back',
    'resumen' => 'Review the work proposals sent by the workshop and accept or reject them.',
    'fecha' => 'Date', 'vencimiento' => 'Valid until', 'total' => 'Total', 'estado' => 'Status',
    'items' => 'Items', 'descripcion' => 'Description', 'cantidad' => 'Qty', 'precio' => 'Unit price', 'subtotal' => 'Subtotal',
    'observaciones' => 'Notes', 'aceptar' => 'Accept', 'rechazar' => 'Reject',
    'sin_presupuestos' => 'You have no budgets yet. When the workshop sends a proposal it will appear here.',
    'aceptado' => 'Budget accepted successfully. The agreed amount is now the amount to pay.',
    'rechazado' => 'Budget rejected successfully.',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_operacion' => 'The operation could not be completed.',
    'error_estado' => 'This budget was already answered.',
    'estados' => ['pendiente' => 'Pending', 'aceptado' => 'Accepted', 'rechazado' => 'Rejected'],
    'respondido' => 'Answered on'
] : [
    'titulo' => 'Mis Presupuestos', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión', 'volver' => 'Volver',
    'resumen' => 'Revisá las propuestas de trabajo que te envió el taller y aceptalas o rechazalas.',
    'fecha' => 'Fecha', 'vencimiento' => 'Válido hasta', 'total' => 'Total', 'estado' => 'Estado',
    'items' => 'Items', 'descripcion' => 'Descripción', 'cantidad' => 'Cantidad', 'precio' => 'Precio unitario', 'subtotal' => 'Subtotal',
    'observaciones' => 'Observaciones', 'aceptar' => 'Aceptar', 'rechazar' => 'Rechazar',
    'sin_presupuestos' => 'Todavía no tenés presupuestos. Cuando el taller envíe una propuesta, va a aparecer acá.',
    'aceptado' => 'Presupuesto aceptado. El monto acordado es ahora el importe a pagar.',
    'rechazado' => 'Presupuesto rechazado correctamente.',
    'error_csrf' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
    'error_operacion' => 'No se pudo completar la operación.',
    'error_estado' => 'Este presupuesto ya tiene una respuesta.',
    'estados' => ['pendiente' => 'Pendiente', 'aceptado' => 'Aceptado', 'rechazado' => 'Rechazado'],
    'respondido' => 'Respondido el'
];

$mensaje = '';
$tipo_alerta = 'alert-success';

// ---------------------------------------------------------------------------
// Acciones POST: aceptar o rechazar un presupuesto
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $texto['error_csrf'];
        $tipo_alerta = 'alert-error';
    } elseif (isset($_POST['accion'], $_POST['id_presupuesto'])) {
        $accion = $_POST['accion'];
        $id_presupuesto = filter_input(INPUT_POST, 'id_presupuesto', FILTER_VALIDATE_INT);

        if ($id_presupuesto && in_array($accion, ['aceptar', 'rechazar'], true)) {
            try {
                // El filtro por cedula impide que un cliente responda un
                // presupuesto que no es suyo.
                $consulta = $conexion->prepare('SELECT id_presupuesto, id_reserva, total, estado FROM presupuesto WHERE id_presupuesto = :id_presupuesto AND cedula_usuario = :cedula_usuario LIMIT 1');
                $consulta->execute([
                    'id_presupuesto' => $id_presupuesto,
                    'cedula_usuario' => $cedula_usuario
                ]);
                $presupuesto = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$presupuesto) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                } elseif (strtolower((string) ($presupuesto['estado'] ?? '')) !== 'pendiente') {
                    $mensaje = $texto['error_estado'];
                    $tipo_alerta = 'alert-error';
                } else {
                    $nuevo_estado = $accion === 'aceptar' ? 'aceptado' : 'rechazado';

                    $actualizar = $conexion->prepare('UPDATE presupuesto SET estado = :estado, fecha_respuesta = NOW(), respondido_por = :respondido_por WHERE id_presupuesto = :id_presupuesto');
                    $actualizar->execute([
                        'estado' => $nuevo_estado,
                        'respondido_por' => $cedula_usuario,
                        'id_presupuesto' => $id_presupuesto
                    ]);

                    // Al aceptar, el total acordado pasa a ser el importe a
                    // cobrar de la reserva y el cobro vuelve a estar pendiente.
                    if ($nuevo_estado === 'aceptado') {
                        $actualizar_reserva = $conexion->prepare('UPDATE reserva SET precio_total = :precio_total, estado_pago = :estado_pago WHERE id_reserva = :id_reserva');
                        $actualizar_reserva->execute([
                            'precio_total' => number_format((float) ($presupuesto['total'] ?? 0), 2, '.', ''),
                            'estado_pago' => 'pendiente',
                            'id_reserva' => (int) $presupuesto['id_reserva']
                        ]);
                    }

                    $mensaje = ($nuevo_estado === 'aceptado') ? $texto['aceptado'] : $texto['rechazado'];
                }
            } catch (PDOException $e) {
                $mensaje = $texto['error_operacion'];
                $tipo_alerta = 'alert-error';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Listado de presupuestos del usuario con su detalle
// ---------------------------------------------------------------------------
$presupuestos = [];
$items_por_presupuesto = [];
try {
    $consulta = $conexion->prepare('SELECT p.*, r.fecha_reserva, s.nombre AS servicio_nombre, t.nombre AS taller_nombre
                                    FROM presupuesto p
                                    LEFT JOIN reserva r ON r.id_reserva = p.id_reserva
                                    LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
                                    LEFT JOIN taller t ON t.id_taller = r.id_taller
                                    WHERE p.cedula_usuario = :cedula_usuario
                                    ORDER BY p.id_presupuesto DESC');
    $consulta->execute(['cedula_usuario' => $cedula_usuario]);
    $presupuestos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($presupuestos)) {
        $consulta_items = $conexion->prepare('SELECT * FROM presupuesto_detalle WHERE id_presupuesto = :id_presupuesto ORDER BY id_detalle');
        foreach ($presupuestos as $presupuesto_item) {
            $consulta_items->execute(['id_presupuesto' => (int) $presupuesto_item['id_presupuesto']]);
            $items_por_presupuesto[(int) $presupuesto_item['id_presupuesto']] = $consulta_items->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $presupuestos = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        nav span { color: #94a3b8; font-size: 14px; }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.3s; }
        nav a.btn-logout:hover { background-color: #dc2626; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .container { flex: 1; padding: 40px 20px; max-width: 900px; width: 100%; margin: 0 auto; }
        .welcome-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 26px 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); margin-bottom: 24px; }
        .welcome-card h2 { font-size: 22px; color: #38bdf8; margin-bottom: 8px; }
        .welcome-card p { color: #cbd5e1; font-size: 14px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #22c55e; background: rgba(34, 197, 94, 0.1); color: #bbf7d0; font-size: 14px; }
        .alert-error { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .presupuesto-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 22px; margin-bottom: 20px; }
        .presupuesto-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .presupuesto-head h3 { font-size: 16px; color: #38bdf8; }
        .presupuesto-meta { font-size: 13px; color: #94a3b8; margin-top: 4px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.pendiente { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .badge.aceptado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.rechazado { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 14px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; padding: 10px; text-align: left; font-weight: 600; }
        td { padding: 9px 10px; border-bottom: 1px solid #334155; color: #cbd5e1; }
        .observaciones { font-size: 13px; color: #cbd5e1; background: rgba(3, 9, 18, 0.42); border: 1px solid #334155; border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; }
        .presupuesto-pie { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .total { font-size: 15px; color: #cbd5e1; }
        .total strong { color: #38bdf8; font-size: 20px; }
        .acciones { display: flex; gap: 10px; }
        .btn { border: none; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-aceptar { background: #22c55e; color: white; }
        .btn-aceptar:hover { background: #16a34a; }
        .btn-rechazar { background: #ef4444; color: white; }
        .btn-rechazar:hover { background: #dc2626; }
        .btn-volver { display: inline-block; margin-top: 8px; color: #38bdf8; text-decoration: none; font-size: 14px; font-weight: 600; }
        .btn-volver:hover { text-decoration: underline; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 30px; font-size: 14px; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/base-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <?php
# Cabecera comun del sitio: logo, navegacion del rol, idioma y salir.
$tgs_volver = isset($panel_volver) ? $panel_volver : '';
include 'includes/cabecera.php';
?>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (empty($presupuestos)): ?>
            <div class="presupuesto-card estado-vacio"><?php echo $texto['sin_presupuestos']; ?></div>
        <?php else: ?>
            <?php foreach ($presupuestos as $presupuesto):
                $id_presupuesto = (int) ($presupuesto['id_presupuesto'] ?? 0);
                $estado_presupuesto = strtolower(trim((string) ($presupuesto['estado'] ?? 'pendiente')));
                $items = $items_por_presupuesto[$id_presupuesto] ?? [];
            ?>
                <div class="presupuesto-card">
                    <div class="presupuesto-head">
                        <div>
                            <h3><?php echo $lang_code === 'en' ? 'Budget' : 'Presupuesto'; ?> #<?php echo $id_presupuesto; ?></h3>
                            <div class="presupuesto-meta">
                                <?php echo ($lang_code === 'en' ? 'Reservation' : 'Reserva'); ?> #<?php echo htmlspecialchars($presupuesto['id_reserva'] ?? ''); ?>
                                &middot; <?php echo htmlspecialchars(tgs_traducir($presupuesto['servicio_nombre'] ?? '-')); ?>
                                <?php if (!empty($presupuesto['taller_nombre'])): ?>
                                    &middot; <?php echo htmlspecialchars(tgs_traducir($presupuesto['taller_nombre'])); ?>
                                <?php endif; ?>
                                &middot; <?php echo ($lang_code === 'en' ? 'Date' : 'Fecha'); ?> <?php echo htmlspecialchars(tgs_fecha_legible($presupuesto['fecha_presupuesto'] ?? '-', $lang_code)); ?>
                                <?php if (!empty($presupuesto['fecha_vencimiento'])): ?>
                                    &middot; <?php echo $texto['vencimiento']; ?> <?php echo htmlspecialchars(tgs_fecha_legible($presupuesto['fecha_vencimiento'], $lang_code)); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge <?php echo htmlspecialchars($estado_presupuesto); ?>"><?php echo htmlspecialchars($texto['estados'][$estado_presupuesto] ?? $estado_presupuesto); ?></span>
                    </div>

                    <?php if (!empty($presupuesto['observaciones'])): ?>
                        <div class="observaciones">
                            <strong><?php echo $texto['observaciones']; ?>:</strong>
                            <?php echo htmlspecialchars($presupuesto['observaciones']); ?>
                        </div>
                    <?php endif; ?>

                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $texto['descripcion']; ?></th>
                                <th><?php echo $texto['cantidad']; ?></th>
                                <th><?php echo $texto['precio']; ?></th>
                                <th><?php echo $texto['subtotal']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="4" class="estado-vacio">-</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['descripcion'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['cantidad'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars(number_format((float) ($item['precio_unitario'] ?? 0), 2, ',', '.')); ?></td>
                                        <td><?php echo htmlspecialchars(number_format((float) ($item['subtotal'] ?? 0), 2, ',', '.')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="presupuesto-pie">
                        <div class="total">
                            <?php echo $texto['total']; ?>:
                            <strong><?php echo htmlspecialchars(number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.')); ?> USD</strong>
                        </div>
                        <?php if ($estado_presupuesto === 'pendiente'): ?>
                            <div class="acciones">
                                <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Accept this budget?' : '¿Aceptás este presupuesto?'; ?>');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                    <input type="hidden" name="accion" value="aceptar">
                                    <input type="hidden" name="id_presupuesto" value="<?php echo $id_presupuesto; ?>">
                                    <button type="submit" class="btn btn-aceptar"><?php echo $texto['aceptar']; ?></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Reject this budget?' : '¿Rechazás este presupuesto?'; ?>');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                    <input type="hidden" name="accion" value="rechazar">
                                    <input type="hidden" name="id_presupuesto" value="<?php echo $id_presupuesto; ?>">
                                    <button type="submit" class="btn btn-rechazar"><?php echo $texto['rechazar']; ?></button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="total" style="font-size: 13px; color: #94a3b8;">
                                <?php echo $texto['respondido']; ?>
                                <?php echo htmlspecialchars(tgs_fecha_legible($presupuesto['fecha_respuesta'] ?? '-', $lang_code)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <a href="<?php echo htmlspecialchars($panel_volver); ?>" class="btn-volver">&larr; <?php echo $texto['volver']; ?></a>
    </div>

    <?php include 'includes/pie.php'; ?>
</body>
</html>
