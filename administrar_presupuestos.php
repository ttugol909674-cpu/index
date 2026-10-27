<?php
/**
 * TGS - Taller Global de Servicios
 * Administracion de presupuestos.
 *
 * Pantalla para gestores y administradores que permite:
 *  - Crear un presupuesto (propuesta de trabajo y precio) sobre una reserva.
 *  - Editar o eliminar un presupuesto mientras sigue pendiente.
 *  - Consultar el estado de cada presupuesto enviado al cliente.
 *
 * El cliente acepta o rechaza el presupuesto desde "presupuestos.php".
 *
 * Reglas de negocio:
 * - Un presupuesto tiene al menos un item con descripcion, cantidad y precio.
 * - El total se calcula como la suma de (cantidad x precio unitario) y se
 *   guarda en la columna total de la tabla presupuesto.
 * - Solo los presupuestos pendientes se pueden editar o eliminar.
 * - Las cantidades son enteros mayores a cero y los precios no negativos.
 *
 * Seguridad:
 * - Verificacion de rol (admin/gestor) y token CSRF en cada accion.
 * - Consultas preparadas para todos los accesos a la base de datos.
 * - Salida de datos con htmlspecialchars.
 *
 * Dependencias: auth.php, conexion.php
 */
require_once 'auth.php';
verificarPermiso('administrar_presupuestos');
require_once 'conexion.php';
require_once 'formato.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$panel_volver = ($rol_actual === 'admin') ? 'dashboard_admin.php' : 'dashboard_gestor.php';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$cedula_usuario = $_SESSION['cedula'] ?? '';

// Textos traducidos.
$texto = $lang_code === 'en' ? [
    'titulo' => 'Budgets', 'inicio' => 'Back to Home', 'panel' => 'Panel', 'hola' => 'Hello', 'salir' => 'Sign Out',
    'listado' => 'Budgets', 'resumen' => 'Prepare the work proposal for a reservation and send it to the customer for approval.',
    'nuevo' => 'New budget', 'cliente' => 'Customer', 'servicio' => 'Service', 'reserva' => 'Reservation',
    'fecha' => 'Date', 'vencimiento' => 'Valid until', 'total' => 'Total', 'estado' => 'Status', 'acciones' => 'Actions',
    'guardar' => 'Save budget', 'editar' => 'Edit', 'eliminar' => 'Delete', 'ver' => 'View', 'cancelar' => 'Cancel',
    'items' => 'Items', 'descripcion' => 'Description', 'cantidad' => 'Qty', 'precio' => 'Unit price', 'subtotal' => 'Subtotal',
    'agregar_item' => 'Add item', 'quitar_item' => 'Remove', 'observaciones' => 'Notes',
    'sin_presupuestos' => 'No budgets registered yet.', 'sin_items' => 'Add at least one item to the budget.',
    'error_items' => 'Add at least one item with a description.', 'error_cantidad' => 'Quantities must be whole numbers greater than zero.',
    'error_precio' => 'Prices cannot be negative.', 'error_reserva' => 'Select a valid reservation.',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_operacion' => 'The operation could not be completed.',
    'guardado' => 'Budget saved successfully.', 'eliminado' => 'Budget deleted successfully.',
    'error_editado' => 'Only pending budgets can be edited or deleted.',
    'estados' => ['pendiente' => 'Pending', 'aceptado' => 'Accepted', 'rechazado' => 'Rejected']
] : [
    'titulo' => 'Presupuestos', 'inicio' => 'Volver al Inicio', 'panel' => 'Panel', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'listado' => 'Presupuestos', 'resumen' => 'Prepará la propuesta de trabajo de una reserva y enviala al cliente para su aprobación.',
    'nuevo' => 'Nuevo presupuesto', 'cliente' => 'Cliente', 'servicio' => 'Servicio', 'reserva' => 'Reserva',
    'fecha' => 'Fecha', 'vencimiento' => 'Válido hasta', 'total' => 'Total', 'estado' => 'Estado', 'acciones' => 'Acciones',
    'guardar' => 'Guardar presupuesto', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'ver' => 'Ver', 'cancelar' => 'Cancelar',
    'items' => 'Items', 'descripcion' => 'Descripción', 'cantidad' => 'Cantidad', 'precio' => 'Precio unitario', 'subtotal' => 'Subtotal',
    'agregar_item' => 'Agregar item', 'quitar_item' => 'Quitar', 'observaciones' => 'Observaciones',
    'sin_presupuestos' => 'Todavía no hay presupuestos registrados.', 'sin_items' => 'Agregá al menos un item al presupuesto.',
    'error_items' => 'Agregá al menos un item con descripción.', 'error_cantidad' => 'Las cantidades deben ser números enteros mayores a cero.',
    'error_precio' => 'Los precios no pueden ser negativos.', 'error_reserva' => 'Seleccioná una reserva válida.',
    'error_csrf' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
    'error_operacion' => 'No se pudo completar la operación.',
    'guardado' => 'Presupuesto guardado correctamente.', 'eliminado' => 'Presupuesto eliminado correctamente.',
    'error_editado' => 'Solo los presupuestos pendientes se pueden editar o eliminar.',
    'estados' => ['pendiente' => 'Pendiente', 'aceptado' => 'Aceptado', 'rechazado' => 'Rechazado']
];

$mensaje = '';
$tipo_alerta = 'alert-success';

// ---------------------------------------------------------------------------
// Procesamiento de acciones POST
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $texto['error_csrf'];
        $tipo_alerta = 'alert-error';
    } elseif (isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        // Accion: guardar un presupuesto nuevo o editar uno existente.
        if ($accion === 'guardar_presupuesto') {
            $id_presupuesto = filter_input(INPUT_POST, 'id_presupuesto', FILTER_VALIDATE_INT) ?: 0;
            $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
            $fecha_presupuesto = trim((string) ($_POST['fecha_presupuesto'] ?? ''));
            $fecha_vencimiento = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
            $observaciones = trim((string) ($_POST['observaciones'] ?? ''));

            // Los items llegan como arreglos: descripcion[], cantidad[], precio_unitario[].
            $descripciones = (array) ($_POST['descripcion'] ?? []);
            $cantidades = (array) ($_POST['cantidad'] ?? []);
            $precios = (array) ($_POST['precio_unitario'] ?? []);

            $items = [];
            $total = 0.0;
            $error_items = '';

            foreach ($descripciones as $indice => $descripcion_item) {
                $descripcion_item = trim((string) $descripcion_item);
                if ($descripcion_item === '') {
                    continue;
                }
                $cantidad_item = $cantidades[$indice] ?? 1;
                $precio_item = $precios[$indice] ?? 0;

                if (!ctype_digit((string) $cantidad_item) || (int) $cantidad_item < 1) {
                    $error_items = $texto['error_cantidad'];
                    break;
                }
                if (!is_numeric($precio_item) || (float) $precio_item < 0) {
                    $error_items = $texto['error_precio'];
                    break;
                }
                $subtotal_item = (int) $cantidad_item * (float) $precio_item;
                $total += $subtotal_item;
                $items[] = [
                    'descripcion' => $descripcion_item,
                    'cantidad' => (int) $cantidad_item,
                    'precio_unitario' => (float) $precio_item,
                    'subtotal' => $subtotal_item
                ];
            }

            if ($error_items !== '') {
                $mensaje = $error_items;
                $tipo_alerta = 'alert-error';
            } elseif (empty($items)) {
                $mensaje = $texto['error_items'];
                $tipo_alerta = 'alert-error';
            } elseif (!$id_reserva) {
                $mensaje = $texto['error_reserva'];
                $tipo_alerta = 'alert-error';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_presupuesto)) {
                $mensaje = $texto['error_operacion'];
                $tipo_alerta = 'alert-error';
            } else {
                try {
                    // La reserva debe existir: de ella se toma el cliente.
                    $consulta_reserva = $conexion->prepare('SELECT id_reserva, cedula_usuario FROM reserva WHERE id_reserva = :id_reserva LIMIT 1');
                    $consulta_reserva->execute(['id_reserva' => $id_reserva]);
                    $reserva = $consulta_reserva->fetch(PDO::FETCH_ASSOC);

                    if (!$reserva) {
                        $mensaje = $texto['error_reserva'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        $cliente_presupuesto = $reserva['cedula_usuario'];

                        // Si se edita, el presupuesto debe seguir pendiente.
                        if ($id_presupuesto) {
                            $verificar = $conexion->prepare('SELECT estado FROM presupuesto WHERE id_presupuesto = :id_presupuesto LIMIT 1');
                            $verificar->execute(['id_presupuesto' => $id_presupuesto]);
                            $estado_actual = $verificar->fetchColumn();

                            if ($estado_actual === false) {
                                $mensaje = $texto['error_operacion'];
                                $tipo_alerta = 'alert-error';
                            } elseif (strtolower((string) $estado_actual) !== 'pendiente') {
                                $mensaje = $texto['error_editado'];
                                $tipo_alerta = 'alert-error';
                            } else {
                                $actualizar = $conexion->prepare('UPDATE presupuesto SET id_reserva = :id_reserva, fecha_presupuesto = :fecha_presupuesto, fecha_vencimiento = :fecha_vencimiento, total = :total, observaciones = :observaciones WHERE id_presupuesto = :id_presupuesto');
                                $actualizar->execute([
                                    'id_reserva' => $id_reserva,
                                    'fecha_presupuesto' => $fecha_presupuesto,
                                    'fecha_vencimiento' => $fecha_vencimiento !== '' ? $fecha_vencimiento : null,
                                    'total' => number_format($total, 2, '.', ''),
                                    'observaciones' => $observaciones !== '' ? $observaciones : null,
                                    'id_presupuesto' => $id_presupuesto
                                ]);

                                $borrar_detalles = $conexion->prepare('DELETE FROM presupuesto_detalle WHERE id_presupuesto = :id_presupuesto');
                                $borrar_detalles->execute(['id_presupuesto' => $id_presupuesto]);

                                $guardar_item = $conexion->prepare('INSERT INTO presupuesto_detalle (id_presupuesto, descripcion, cantidad, precio_unitario, subtotal) VALUES (:id_presupuesto, :descripcion, :cantidad, :precio_unitario, :subtotal)');
                                foreach ($items as $item) {
                                    $guardar_item->execute([
                                        'id_presupuesto' => $id_presupuesto,
                                        'descripcion' => $item['descripcion'],
                                        'cantidad' => $item['cantidad'],
                                        'precio_unitario' => number_format($item['precio_unitario'], 2, '.', ''),
                                        'subtotal' => number_format($item['subtotal'], 2, '.', '')
                                    ]);
                                }

                                $mensaje = $texto['guardado'];
                            }
                        } else {
                            $insertar = $conexion->prepare('INSERT INTO presupuesto (id_reserva, cedula_usuario, fecha_presupuesto, fecha_vencimiento, total, estado, observaciones, creado_por) VALUES (:id_reserva, :cedula_usuario, :fecha_presupuesto, :fecha_vencimiento, :total, :estado, :observaciones, :creado_por)');
                            $insertar->execute([
                                'id_reserva' => $id_reserva,
                                'cedula_usuario' => $cliente_presupuesto,
                                'fecha_presupuesto' => $fecha_presupuesto,
                                'fecha_vencimiento' => $fecha_vencimiento !== '' ? $fecha_vencimiento : null,
                                'total' => number_format($total, 2, '.', ''),
                                'estado' => 'pendiente',
                                'observaciones' => $observaciones !== '' ? $observaciones : null,
                                'creado_por' => $cedula_usuario !== '' ? $cedula_usuario : null
                            ]);
                            $id_presupuesto = (int) $conexion->lastInsertId();

                            $guardar_item = $conexion->prepare('INSERT INTO presupuesto_detalle (id_presupuesto, descripcion, cantidad, precio_unitario, subtotal) VALUES (:id_presupuesto, :descripcion, :cantidad, :precio_unitario, :subtotal)');
                            foreach ($items as $item) {
                                $guardar_item->execute([
                                    'id_presupuesto' => $id_presupuesto,
                                    'descripcion' => $item['descripcion'],
                                    'cantidad' => $item['cantidad'],
                                    'precio_unitario' => number_format($item['precio_unitario'], 2, '.', ''),
                                    'subtotal' => number_format($item['subtotal'], 2, '.', '')
                                ]);
                            }

                            $mensaje = $texto['guardado'];
                        }
                    }
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Accion: eliminar un presupuesto pendiente.
        if ($accion === 'eliminar_presupuesto') {
            $id_presupuesto = filter_input(INPUT_POST, 'id_presupuesto', FILTER_VALIDATE_INT);
            if ($id_presupuesto) {
                try {
                    $verificar = $conexion->prepare('SELECT estado FROM presupuesto WHERE id_presupuesto = :id_presupuesto LIMIT 1');
                    $verificar->execute(['id_presupuesto' => $id_presupuesto]);
                    $estado_actual = $verificar->fetchColumn();

                    if ($estado_actual === false) {
                        $mensaje = $texto['error_operacion'];
                        $tipo_alerta = 'alert-error';
                    } elseif (strtolower((string) $estado_actual) !== 'pendiente') {
                        $mensaje = $texto['error_editado'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        $borrar = $conexion->prepare('DELETE FROM presupuesto WHERE id_presupuesto = :id_presupuesto');
                        $borrar->execute(['id_presupuesto' => $id_presupuesto]);
                        $mensaje = $texto['eliminado'];
                    }
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Datos para el formulario y el listado
// ---------------------------------------------------------------------------
// Reserva en edicion (si viene por GET con ?editar=ID).
$presupuesto_edicion = null;
$items_edicion = [];
$id_editar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($id_editar) {
    try {
        $consulta = $conexion->prepare('SELECT * FROM presupuesto WHERE id_presupuesto = :id_presupuesto LIMIT 1');
        $consulta->execute(['id_presupuesto' => $id_editar]);
        $presupuesto_edicion = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($presupuesto_edicion) {
            $consulta_items = $conexion->prepare('SELECT * FROM presupuesto_detalle WHERE id_presupuesto = :id_presupuesto ORDER BY id_detalle');
            $consulta_items->execute(['id_presupuesto' => $id_editar]);
            $items_edicion = $consulta_items->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $presupuesto_edicion = null;
    }
}

// Reservas disponibles para generar un presupuesto.
$reservas_disponibles = [];
try {
    $consulta = $conexion->query("SELECT r.id_reserva, r.fecha_reserva, r.estado, r.precio_total, u.nombre, u.apellido, s.nombre AS servicio_nombre
                                  FROM reserva r
                                  LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
                                  LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
                                  ORDER BY r.id_reserva DESC");
    $reservas_disponibles = $consulta ? $consulta->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $reservas_disponibles = [];
}

// Listado de presupuestos con su detalle.
$presupuestos = [];
try {
    $consulta = $conexion->query("SELECT p.*, u.nombre, u.apellido, r.fecha_reserva, s.nombre AS servicio_nombre
                                  FROM presupuesto p
                                  LEFT JOIN usuario u ON u.cedula = p.cedula_usuario
                                  LEFT JOIN reserva r ON r.id_reserva = p.id_reserva
                                  LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
                                  ORDER BY p.id_presupuesto DESC");
    $presupuestos = $consulta ? $consulta->fetchAll(PDO::FETCH_ASSOC) : [];
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
        .container { flex: 1; padding: 40px 20px; max-width: 1300px; width: 100%; margin: 0 auto; }
        .welcome-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 26px 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); margin-bottom: 24px; }
        .welcome-card h2 { font-size: 22px; color: #38bdf8; margin-bottom: 8px; }
        .welcome-card p { color: #cbd5e1; font-size: 14px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #22c55e; background: rgba(34, 197, 94, 0.1); color: #bbf7d0; font-size: 14px; }
        .alert-error { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .card-form { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 22px; margin-bottom: 26px; }
        .card-form h3 { color: #38bdf8; font-size: 16px; margin-bottom: 16px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 13px; color: #94a3b8; }
        .form-group input, .form-group select, .form-group textarea { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 9px 12px; font-size: 14px; outline: none; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #38bdf8; }
        .tabla-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; overflow: hidden; margin-bottom: 24px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #334155; font-size: 13px; vertical-align: middle; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; }
        .items-table input { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 6px; padding: 7px 9px; font-size: 13px; width: 100%; }
        .items-table input.cantidad { width: 75px; }
        .items-table input.precio { width: 110px; }
        .items-table input.subtotal { width: 110px; background: rgba(15, 23, 42, 0.6); color: #94a3b8; }
        .btn { border: none; border-radius: 6px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-primario { background: #2563eb; color: white; }
        .btn-primario:hover { background: #1d4ed8; }
        .btn-secundario { background: #334155; color: #cbd5e1; }
        .btn-secundario:hover { background: #475569; }
        .btn-peligro { background: #ef4444; color: white; }
        .btn-peligro:hover { background: #dc2626; }
        .btn-mini { padding: 5px 10px; font-size: 12px; }
        .total-general { display: flex; justify-content: flex-end; align-items: center; gap: 14px; margin-top: 14px; font-size: 15px; }
        .total-general strong { color: #38bdf8; font-size: 20px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.pendiente { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .badge.aceptado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.rechazado { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 24px; font-size: 14px; }
        .detalle-items { margin: 0 0 14px; font-size: 13px; color: #cbd5e1; }
        .detalle-items li { margin-bottom: 4px; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/base-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
    <script src="js/fecha.js" defer></script>
</head>
<body>
    <?php
# Cabecera comun del sitio: logo, navegacion del rol, idioma y salir.
$tgs_volver = isset($panel_volver) ? $panel_volver : '';
include 'includes/cabecera.php';
?>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $texto['listado']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Formulario de alta o edicion de presupuesto -->
        <div class="card-form">
            <h3>
                <?php echo $presupuesto_edicion ? $texto['editar'] . ' #' . htmlspecialchars($presupuesto_edicion['id_presupuesto']) : $texto['nuevo']; ?>
            </h3>
            <form method="POST" id="form-presupuesto">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="accion" value="guardar_presupuesto">
                <input type="hidden" name="id_presupuesto" value="<?php echo htmlspecialchars($presupuesto_edicion['id_presupuesto'] ?? 0); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_reserva"><?php echo $texto['reserva']; ?></label>
                        <select id="id_reserva" name="id_reserva" required>
                            <option value=""><?php echo $lang_code === 'en' ? 'Select a reservation' : 'Selecciona una reserva'; ?></option>
                            <?php foreach ($reservas_disponibles as $reserva_opcion):
                                $opcion_id = (int) ($reserva_opcion['id_reserva'] ?? 0);
                                $opcion_cliente = trim(($reserva_opcion['nombre'] ?? '') . ' ' . ($reserva_opcion['apellido'] ?? '')) ?: 'Cliente';
                                $opcion_texto = '#' . $opcion_id . ' · ' . $opcion_cliente . ' · ' . tgs_traducir($reserva_opcion['servicio_nombre'] ?? '-') . ' · ' . ($reserva_opcion['fecha_reserva'] ?? '');
                                $opcion_seleccionada = ($presupuesto_edicion && (int) $presupuesto_edicion['id_reserva'] === $opcion_id) ? ' selected' : '';
                            ?>
                                <option value="<?php echo $opcion_id; ?>"<?php echo $opcion_seleccionada; ?>><?php echo htmlspecialchars($opcion_texto); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_presupuesto"><?php echo $texto['fecha']; ?></label>
                        <?php echo tgs_campo_fecha('fecha_presupuesto', (string) ($presupuesto_edicion['fecha_presupuesto'] ?? date('Y-m-d')), $lang_code, array('required' => true, 'id' => 'fecha_presupuesto')); ?>
                    </div>
                    <div class="form-group">
                        <label for="fecha_vencimiento"><?php echo $texto['vencimiento']; ?></label>
                        <?php echo tgs_campo_fecha('fecha_vencimiento', (string) ($presupuesto_edicion['fecha_vencimiento'] ?? ''), $lang_code, array('id' => 'fecha_vencimiento')); ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="observaciones"><?php echo $texto['observaciones']; ?></label>
                    <input type="text" id="observaciones" name="observaciones" maxlength="255" value="<?php echo htmlspecialchars($presupuesto_edicion['observaciones'] ?? ''); ?>">
                </div>

                <!-- Items del presupuesto: se agregan filas desde JavaScript -->
                <h4 style="color: #cbd5e1; font-size: 14px; margin-bottom: 10px;"><?php echo $texto['items']; ?></h4>
                <div class="tabla-scroll">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th><?php echo $texto['descripcion']; ?></th>
                                <th><?php echo $texto['cantidad']; ?></th>
                                <th><?php echo $texto['precio']; ?></th>
                                <th><?php echo $texto['subtotal']; ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="filas-items">
                            <?php if (!empty($items_edicion)): ?>
                                <?php foreach ($items_edicion as $item): ?>
                                    <tr>
                                        <td><input type="text" name="descripcion[]" maxlength="255" value="<?php echo htmlspecialchars($item['descripcion'] ?? ''); ?>" required></td>
                                        <td><input type="number" class="cantidad" name="cantidad[]" min="1" step="1" value="<?php echo htmlspecialchars($item['cantidad'] ?? 1); ?>"></td>
                                        <td><input type="number" class="precio" name="precio_unitario[]" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float) ($item['precio_unitario'] ?? 0), 2, '.', '')); ?>"></td>
                                        <td><input type="text" class="subtotal" value="<?php echo htmlspecialchars(number_format((float) ($item['subtotal'] ?? 0), 2, '.', '')); ?>" readonly></td>
                                        <td><button type="button" class="btn btn-peligro btn-mini" onclick="quitarFila(this)"><?php echo $texto['quitar_item']; ?></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="descripcion[]" maxlength="255"></td>
                                    <td><input type="number" class="cantidad" name="cantidad[]" min="1" step="1" value="1"></td>
                                    <td><input type="number" class="precio" name="precio_unitario[]" min="0" step="0.01" value="0.00"></td>
                                    <td><input type="text" class="subtotal" value="0.00" readonly></td>
                                    <td><button type="button" class="btn btn-peligro btn-mini" onclick="quitarFila(this)"><?php echo $texto['quitar_item']; ?></button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="total-general">
                    <span><?php echo $texto['total']; ?>:</span>
                    <strong id="total-general">0.00 USD</strong>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secundario" onclick="agregarFila()"><?php echo $texto['agregar_item']; ?></button>
                    <button type="submit" class="btn btn-primario"><?php echo $texto['guardar']; ?></button>
                    <?php if ($presupuesto_edicion): ?>
                        <a href="administrar_presupuestos.php" class="btn btn-secundario"><?php echo $texto['cancelar']; ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Listado de presupuestos -->
        <div class="tabla-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $texto['reserva']; ?></th>
                        <th><?php echo $texto['cliente']; ?></th>
                        <th><?php echo $texto['fecha']; ?></th>
                        <th><?php echo $texto['vencimiento']; ?></th>
                        <th><?php echo $texto['total']; ?></th>
                        <th><?php echo $texto['estado']; ?></th>
                        <th><?php echo $texto['acciones']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($presupuestos)): ?>
                        <tr>
                            <td colspan="8" class="estado-vacio"><?php echo $texto['sin_presupuestos']; ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($presupuestos as $presupuesto):
                            $estado_presupuesto = strtolower(trim((string) ($presupuesto['estado'] ?? 'pendiente')));
                            $cliente_presupuesto = trim(($presupuesto['nombre'] ?? '') . ' ' . ($presupuesto['apellido'] ?? '')) ?: 'Cliente';
                        ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($presupuesto['id_presupuesto'] ?? ''); ?></td>
                                <td>#<?php echo htmlspecialchars($presupuesto['id_reserva'] ?? ''); ?> · <?php echo htmlspecialchars(tgs_traducir($presupuesto['servicio_nombre'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars($cliente_presupuesto); ?></td>
                                <td><?php echo htmlspecialchars(tgs_fecha_legible($presupuesto['fecha_presupuesto'] ?? '-', $lang_code)); ?></td>
                                <td><?php echo htmlspecialchars(tgs_fecha_legible($presupuesto['fecha_vencimiento'] ?? '-', $lang_code)); ?></td>
                                <td><?php echo htmlspecialchars(number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.')); ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($estado_presupuesto); ?>"><?php echo htmlspecialchars($texto['estados'][$estado_presupuesto] ?? $estado_presupuesto); ?></span></td>
                                <td style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php if ($estado_presupuesto === 'pendiente'): ?>
                                        <a href="administrar_presupuestos.php?editar=<?php echo htmlspecialchars($presupuesto['id_presupuesto'] ?? ''); ?>" class="btn btn-secundario btn-mini"><?php echo $texto['editar']; ?></a>
                                        <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Delete this budget?' : '¿Eliminar este presupuesto?'; ?>');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion" value="eliminar_presupuesto">
                                            <input type="hidden" name="id_presupuesto" value="<?php echo htmlspecialchars($presupuesto['id_presupuesto'] ?? ''); ?>">
                                            <button type="submit" class="btn btn-peligro btn-mini"><?php echo $texto['eliminar']; ?></button>
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

    <?php include 'includes/pie.php'; ?>
    <script>
        // Agrega una fila de item al final de la tabla de items.
        function agregarFila() {
            var cuerpo = document.getElementById('filas-items');
            var fila = document.createElement('tr');
            fila.innerHTML =
                '<td><input type="text" name="descripcion[]" maxlength="255" required></td>' +
                '<td><input type="number" class="cantidad" name="cantidad[]" min="1" step="1" value="1"></td>' +
                '<td><input type="number" class="precio" name="precio_unitario[]" min="0" step="0.01" value="0.00"></td>' +
                '<td><input type="text" class="subtotal" value="0.00" readonly></td>' +
                '<td><button type="button" class="btn btn-peligro btn-mini"><?php echo $texto['quitar_item']; ?></button></td>';
            cuerpo.appendChild(fila);
            recalcularTotal();
        }

        // Quita la fila de item a la que pertenece el boton.
        function quitarFila(boton) {
            var filas = document.querySelectorAll('#filas-items tr');
            if (filas.length > 1) {
                boton.closest('tr').remove();
            } else {
                boton.closest('tr').querySelectorAll('input').forEach(function (campo) {
                    campo.value = campo.classList.contains('cantidad') ? '1' : (campo.classList.contains('precio') ? '0.00' : '');
                });
            }
            recalcularTotal();
        }

        // Calcula el subtotal de cada fila y el total general del presupuesto.
        function recalcularTotal() {
            var total = 0;
            document.querySelectorAll('#filas-items tr').forEach(function (fila) {
                var cantidad = parseFloat(fila.querySelector('.cantidad') ? fila.querySelector('.cantidad').value : '0') || 0;
                var precio = parseFloat(fila.querySelector('.precio') ? fila.querySelector('.precio').value : '0') || 0;
                var subtotal = cantidad * precio;
                total += subtotal;
                var campoSubtotal = fila.querySelector('.subtotal');
                if (campoSubtotal) { campoSubtotal.value = subtotal.toFixed(2); }
            });
            document.getElementById('total-general').textContent = total.toFixed(2) + ' USD';
        }

        document.querySelectorAll('#filas-items input').forEach(function (campo) {
            campo.addEventListener('input', recalcularTotal);
        });
        document.getElementById('form-presupuesto').addEventListener('submit', function () {
            // Agrega los listeners de las filas creadas despues de cargar la pagina.
            document.querySelectorAll('#filas-items input').forEach(function (campo) {
                if (!campo.dataset.listener) {
                    campo.dataset.listener = '1';
                    campo.addEventListener('input', recalcularTotal);
                }
            });
        });
        recalcularTotal();
    </script>
</body>
</html>
