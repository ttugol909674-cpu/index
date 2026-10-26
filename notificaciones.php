<?php
/**
 * TGS - Taller Global de Servicios
 * Avisos al cliente (notificaciones).
 *
 * Pantalla para gestores y administradores que muestra los avisos que el
 * sistema le manda al cliente (por ejemplo, cuando un turno se finaliza) y
 * permite reenviar los que quedaron pendientes.
 *
 * Estados de una notificacion:
 * - enviado:  el correo salio (por SMTP o por mail()).
 * - pendiente: se registro pero no se pudo enviar (falta configurar el
 *   correo en el .env, o el servidor de correo no respondio).
 * - error:    el envio fallo y se guardo el motivo.
 *
 * Para configurar el transporte de correo hay que definir en .env.local:
 *   SMTP_HOST, SMTP_PUERTO, SMTP_USUARIO, SMTP_CLAVE, SMTP_REMITENTE
 *
 * Acceso: solo administradores y gestores (permiso 'administrar_pagos',
 * el mismo grupo de operacion que ve los cobros y los presupuestos).
 *
 * Dependencias: auth.php, conexion.php, correo.php
 */
require_once 'auth.php';
verificarPermiso('administrar_pagos');
require_once 'conexion.php';
require_once 'correo.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$panel_volver = ($rol_actual === 'admin') ? 'dashboard_admin.php' : 'dashboard_gestor.php';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

$texto = $lang_code === 'en' ? [
    'titulo' => 'Customer Notifications', 'inicio' => 'Back to Home', 'panel' => 'Panel', 'hola' => 'Hello', 'salir' => 'Sign Out',
    'listado' => 'Notifications sent to customers',
    'resumen' => 'Every notice the system sends to a customer is registered here. Pending notices can be retried once the mail server is configured.',
    'destinatario' => 'Recipient', 'asunto' => 'Subject', 'tipo' => 'Type', 'estado' => 'Status', 'fecha' => 'Date',
    'intentos' => 'Attempts', 'detalle' => 'Transport response', 'acciones' => 'Actions', 'reenviar' => 'Send again',
    'sin_notificaciones' => 'No notifications registered yet.',
    'tipos' => ['finalizacion' => 'Vehicle ready', 'reprogramacion' => 'Rescheduled', 'general' => 'General'],
    'estados' => ['enviado' => 'Sent', 'pendiente' => 'Pending', 'error' => 'Error'],
    'enviado_ok' => 'The notice was sent successfully.',
    'enviado_error' => 'The notice could not be sent: ',
    'ver_cuerpo' => 'View message',
    'cuerpo' => 'Message content',
    'volver' => 'Back'
] : [
    'titulo' => 'Avisos al Cliente', 'inicio' => 'Volver al Inicio', 'panel' => 'Panel', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'listado' => 'Avisos enviados a los clientes',
    'resumen' => 'Cada aviso que el sistema manda a un cliente queda registrado acá. Los pendientes se pueden reenviar cuando se configure el servidor de correo.',
    'destinatario' => 'Destinatario', 'asunto' => 'Asunto', 'tipo' => 'Tipo', 'estado' => 'Estado', 'fecha' => 'Fecha',
    'intentos' => 'Intentos', 'detalle' => 'Respuesta del transporte', 'acciones' => 'Acciones', 'reenviar' => 'Reenviar',
    'sin_notificaciones' => 'Todavía no hay avisos registrados.',
    'tipos' => ['finalizacion' => 'Vehículo listo', 'reprogramacion' => 'Turno reprogramado', 'general' => 'General'],
    'estados' => ['enviado' => 'Enviado', 'pendiente' => 'Pendiente', 'error' => 'Error'],
    'enviado_ok' => 'El aviso se envió correctamente.',
    'enviado_error' => 'No se pudo enviar el aviso: ',
    'ver_cuerpo' => 'Ver mensaje',
    'cuerpo' => 'Contenido del mensaje',
    'volver' => 'Volver'
];

$mensaje = '';
$tipo_alerta = 'alert-success';

// Reenvio de una notificacion pendiente.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['id_notificacion'])) {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $lang_code === 'en' ? 'The session expired. Please reload the page.' : 'La sesión expiró. Recargá la página.';
        $tipo_alerta = 'alert-error';
    } elseif ($_POST['accion'] === 'reenviar') {
        $id_notificacion = filter_input(INPUT_POST, 'id_notificacion', FILTER_VALIDATE_INT);
        if ($id_notificacion) {
            $resultado = tgs_reintentar_notificacion($conexion, (int) $id_notificacion);
            if ($resultado['estado'] === 'enviado') {
                $mensaje = $texto['enviado_ok'];
            } else {
                $mensaje = $texto['enviado_error'] . ($resultado['detalle'] ?? '');
                $tipo_alerta = 'alert-error';
            }
        }
    }
}

// Estado del transporte de correo configurado.
$correo = tgs_config_correo();
$transporte = 'mail()';
if ($correo['host'] !== '') {
    $transporte = 'SMTP (' . $correo['host'] . ':' . $correo['puerto'] . ')';
} elseif (!tgs_tiene_mta()) {
    $transporte = 'sin configurar';
}

// Filtro por estado.
$filtro_estado = strtolower(trim($_GET['estado'] ?? ''));
$estados_validos = ['enviado', 'pendiente', 'error'];
if (!in_array($filtro_estado, $estados_validos, true)) {
    $filtro_estado = '';
}

$notificaciones = [];
try {
    $sql = 'SELECT n.*, r.id_reserva AS reserva FROM notificacion n LEFT JOIN reserva r ON r.id_reserva = n.id_reserva';
    $parametros = [];
    if ($filtro_estado !== '') {
        $sql .= ' WHERE n.estado = :estado';
        $parametros['estado'] = $filtro_estado;
    }
    $sql .= ' ORDER BY n.id_notificacion DESC LIMIT 100';

    $stmt = $conexion->prepare($sql);
    $stmt->execute($parametros);
    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notificaciones = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .container { flex: 1; padding: 40px 20px; max-width: 1200px; width: 100%; margin: 0 auto; }
        .welcome-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 26px 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); margin-bottom: 24px; }
        .welcome-card h2 { font-size: 22px; color: #38bdf8; margin-bottom: 8px; }
        .welcome-card p { color: #cbd5e1; font-size: 14px; }
        .transporte { display: inline-block; margin-top: 10px; font-size: 12px; color: #94a3b8; background: rgba(15, 23, 42, 0.9); border: 1px solid #334155; border-radius: 999px; padding: 5px 12px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #22c55e; background: rgba(34, 197, 94, 0.1); color: #bbf7d0; font-size: 14px; }
        .alert-error { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .filtros-bar { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .filtros-bar select { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 9px 12px; font-size: 13px; }
        .filtros-bar a { color: #38bdf8; font-size: 13px; font-weight: 600; text-decoration: none; }
        .tabla-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; overflow: hidden; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #334155; font-size: 13px; vertical-align: top; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.enviado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.pendiente { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .badge.error { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .detalle-transporte { font-size: 11px; color: #94a3b8; max-width: 260px; word-break: break-word; }
        .btn { border: none; border-radius: 6px; padding: 7px 12px; font-size: 12px; font-weight: 600; cursor: pointer; background: #2563eb; color: white; }
        .btn:hover { background: #1d4ed8; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 24px; font-size: 14px; }
        .cuerpo-mensaje { white-space: pre-wrap; font-size: 12px; color: #cbd5e1; background: rgba(3, 9, 18, 0.5); border: 1px solid #334155; border-radius: 6px; padding: 8px 10px; margin-top: 6px; max-width: 320px; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <header>
        <a href="<?php echo htmlspecialchars($panel_volver); ?>" class="logo">TGS · <?php echo $texto['titulo']; ?></a>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> <?php echo $texto['inicio']; ?></a>
            <a href="<?php echo htmlspecialchars($panel_volver); ?>" class="nav-link" style="color: #38bdf8; font-weight: bold;"><?php echo $texto['panel']; ?></a>
            <!-- Selector de idioma de los avisos al cliente -->
            <div class="language-selector"><a href="notificaciones.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a><a href="notificaciones.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a></div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $texto['listado']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
            <span class="transporte"><?php echo $lang_code === 'en' ? 'Mail transport' : 'Transporte de correo'; ?>: <?php echo htmlspecialchars($transporte); ?></span>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <form action="notificaciones.php" method="GET" class="filtros-bar">
            <select name="estado">
                <option value=""><?php echo $lang_code === 'en' ? 'All statuses' : 'Todos los estados'; ?></option>
                <option value="enviado" <?php echo ($filtro_estado === 'enviado') ? 'selected' : ''; ?>><?php echo $texto['estados']['enviado']; ?></option>
                <option value="pendiente" <?php echo ($filtro_estado === 'pendiente') ? 'selected' : ''; ?>><?php echo $texto['estados']['pendiente']; ?></option>
                <option value="error" <?php echo ($filtro_estado === 'error') ? 'selected' : ''; ?>><?php echo $texto['estados']['error']; ?></option>
            </select>
            <button type="submit" class="btn"><?php echo $lang_code === 'en' ? 'Filter' : 'Filtrar'; ?></button>
            <?php if ($filtro_estado !== ''): ?>
                <a href="notificaciones.php"><?php echo $lang_code === 'en' ? 'Clear' : 'Limpiar'; ?></a>
            <?php endif; ?>
        </form>

        <div class="tabla-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $texto['destinatario']; ?></th>
                        <th><?php echo $texto['asunto']; ?></th>
                        <th><?php echo $texto['tipo']; ?></th>
                        <th><?php echo $texto['estado']; ?></th>
                        <th><?php echo $texto['fecha']; ?></th>
                        <th><?php echo $texto['intentos']; ?></th>
                        <th><?php echo $texto['detalle']; ?></th>
                        <th><?php echo $texto['acciones']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notificaciones)): ?>
                        <tr>
                            <td colspan="9" class="estado-vacio"><?php echo $texto['sin_notificaciones']; ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notificaciones as $notificacion):
                            $estado_notificacion = strtolower(trim((string) ($notificacion['estado'] ?? 'pendiente')));
                            $tipo_notificacion = strtolower(trim((string) ($notificacion['tipo'] ?? 'general')));
                        ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($notificacion['id_notificacion'] ?? ''); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($notificacion['email_destino'] ?? ''); ?>
                                    <?php if (!empty($notificacion['reserva'])): ?>
                                        <div class="detalle-transporte"><?php echo $lang_code === 'en' ? 'Reservation' : 'Reserva'; ?> #<?php echo htmlspecialchars($notificacion['reserva']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($notificacion['asunto'] ?? ''); ?>
                                    <div class="cuerpo-mensaje"><?php echo htmlspecialchars($notificacion['cuerpo'] ?? ''); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($texto['tipos'][$tipo_notificacion] ?? $tipo_notificacion); ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($estado_notificacion); ?>"><?php echo htmlspecialchars($texto['estados'][$estado_notificacion] ?? $estado_notificacion); ?></span></td>
                                <td><?php echo htmlspecialchars($notificacion['created_at'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($notificacion['intentos'] ?? 0); ?></td>
                                <td><div class="detalle-transporte"><?php echo htmlspecialchars($notificacion['respuesta_transporte'] ?? '-'); ?></div></td>
                                <td>
                                    <?php if ($estado_notificacion !== 'enviado'): ?>
                                        <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Send this notice again?' : '¿Reenviar este aviso?'; ?>');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion" value="reenviar">
                                            <input type="hidden" name="id_notificacion" value="<?php echo htmlspecialchars($notificacion['id_notificacion'] ?? ''); ?>">
                                            <button type="submit" class="btn"><?php echo $texto['reenviar']; ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $lang_code === 'en' ? 'Global Workshop Services. All rights reserved.' : 'Taller Global de Servicios. Todos los derechos reservados.'; ?></p>
    </footer>
</body>
</html>
