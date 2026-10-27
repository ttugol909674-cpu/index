<?php
/**
 * TGS - Taller Global de Servicios
 * Detalle de un turno.
 *
 * Vista completa de una reserva: cliente, vehiculo, servicio, estado,
 * cobros, presupuestos, avisos enviados e historial de cambios. Desde aca
 * se puede trabajar sobre el turno: cambiar el estado, reprogramarlo,
 * registrar un cobro o anular uno.
 *
 * Es la pantalla de destino cuando se hace clic en un turno de la agenda
 * (agenda_gestor.php) o en la lista de reservas.
 *
 * Acceso: gestores y administradores (permiso 'administrar_turnos').
 *
 * Dependencias: auth.php, conexion.php, correo.php, funciones_pago.php,
 * taller_disponibilidad.php
 */
require_once 'auth.php';
verificarPermiso('administrar_turnos');
require_once 'conexion.php';
require_once 'formato.php';
require_once 'correo.php';
require_once 'funciones_pago.php';
require_once 'taller_disponibilidad.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$panel_volver = ($rol_actual === 'admin') ? 'dashboard_admin.php' : 'dashboard_gestor.php';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$cedula_usuario = $_SESSION['cedula'] ?? '';

$texto = $lang_code === 'en' ? [
    'titulo' => 'Appointment detail', 'inicio' => 'Back to Home', 'panel' => 'Panel', 'hola' => 'Hello', 'salir' => 'Sign Out',
    'resumen' => 'Everything about this appointment: customer, vehicle, payments, budgets and history.',
    'cliente' => 'Customer', 'vehiculo' => 'Vehicle', 'servicio' => 'Service', 'sede' => 'Workshop',
    'fecha' => 'Date', 'hora' => 'Time', 'estado' => 'Status', 'pago' => 'Payment', 'total' => 'Total',
    'telefono' => 'Phone', 'correo' => 'Email', 'patente' => 'License plate', 'anio' => 'Year', 'marca' => 'Brand', 'modelo' => 'Model',
    'cobros' => 'Payments', 'registrar_cobro' => 'Register payment', 'monto' => 'Amount', 'metodo' => 'Method',
    'referencia' => 'Reference', 'notas' => 'Notes', 'anular' => 'Void', 'presupuestos' => 'Budgets',
    'avisos' => 'Notices sent', 'historial' => 'Change history', 'acciones' => 'Actions',
    'cambiar_estado' => 'Change status', 'reprogramar' => 'Reschedule', 'no_existe' => 'The appointment does not exist.',
    'sin_cobros' => 'No payments registered.', 'sin_presupuestos' => 'No budgets for this appointment.',
    'sin_avisos' => 'No notices sent for this appointment.', 'sin_historial' => 'No changes recorded.',
    'campo' => 'Field', 'anterior' => 'Previous', 'nuevo' => 'New', 'usuario' => 'User', 'saldo' => 'Balance',
    'exito_cobro' => 'Payment registered successfully.', 'exito_anular' => 'Payment voided successfully.',
    'exito_estado' => 'Status updated successfully.', 'exito_reprogramar' => 'Appointment rescheduled successfully.',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_operacion' => 'The operation could not be completed.',
    'error_monto' => 'Enter an amount greater than zero.', 'error_metodo' => 'Select a valid payment method.',
    'error_disponibilidad' => 'That slot is not available: ',
    'estados' => ['Pendiente' => 'Pending', 'En curso' => 'In progress', 'Finalizada' => 'Completed', 'Cancelada' => 'Cancelled'],
    'estados_presupuesto' => ['pendiente' => 'Pending', 'aceptado' => 'Accepted', 'rechazado' => 'Rejected', 'vencido' => 'Expired'],
    'pagos' => ['pagado' => 'Paid', 'parcial' => 'Partial', 'pendiente' => 'Pending', 'anulado' => 'Void'],
    'metodos' => ['efectivo' => 'Cash', 'tarjeta' => 'Card', 'transferencia' => 'Transfer', 'prepago' => 'Prepaid account'],
    'volver' => 'Back to the agenda', 'volver_lista' => 'Back to the list'
] : [
    'titulo' => 'Detalle del turno', 'inicio' => 'Volver al Inicio', 'panel' => 'Panel', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'resumen' => 'Todo sobre este turno: cliente, vehículo, cobros, presupuestos e historial.',
    'cliente' => 'Cliente', 'vehiculo' => 'Vehículo', 'servicio' => 'Servicio', 'sede' => 'Sede',
    'fecha' => 'Fecha', 'hora' => 'Hora', 'estado' => 'Estado', 'pago' => 'Pago', 'total' => 'Total',
    'telefono' => 'Teléfono', 'correo' => 'Correo', 'patente' => 'Patente', 'anio' => 'Año', 'marca' => 'Marca', 'modelo' => 'Modelo',
    'cobros' => 'Cobros', 'registrar_cobro' => 'Registrar cobro', 'monto' => 'Monto', 'metodo' => 'Método',
    'referencia' => 'Referencia', 'notas' => 'Notas', 'anular' => 'Anular', 'presupuestos' => 'Presupuestos',
    'avisos' => 'Avisos enviados', 'historial' => 'Historial de cambios', 'acciones' => 'Acciones',
    'cambiar_estado' => 'Cambiar estado', 'reprogramar' => 'Reprogramar', 'no_existe' => 'El turno no existe.',
    'sin_cobros' => 'No hay cobros registrados.', 'sin_presupuestos' => 'No hay presupuestos para este turno.',
    'sin_avisos' => 'No se enviaron avisos para este turno.', 'sin_historial' => 'No hay cambios registrados.',
    'campo' => 'Campo', 'anterior' => 'Anterior', 'nuevo' => 'Nuevo', 'usuario' => 'Usuario', 'saldo' => 'Saldo',
    'exito_cobro' => 'Cobro registrado correctamente.', 'exito_anular' => 'Cobro anulado correctamente.',
    'exito_estado' => 'Estado actualizado correctamente.', 'exito_reprogramar' => 'Turno reprogramado correctamente.',
    'error_csrf' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
    'error_operacion' => 'No se pudo completar la operación.',
    'error_monto' => 'Ingresá un monto mayor a cero.', 'error_metodo' => 'Seleccioná un método de pago válido.',
    'error_disponibilidad' => 'Ese horario no está disponible: ',
    'estados' => ['Pendiente' => 'Pendiente', 'En curso' => 'En curso', 'Finalizada' => 'Finalizada', 'Cancelada' => 'Cancelada'],
    'estados_presupuesto' => ['pendiente' => 'Pendiente', 'aceptado' => 'Aceptado', 'rechazado' => 'Rechazado', 'vencido' => 'Vencido'],
    'pagos' => ['pagado' => 'Pagado', 'parcial' => 'Parcial', 'pendiente' => 'Pendiente', 'anulado' => 'Anulado'],
    'metodos' => ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'prepago' => 'Cuenta prepaga'],
    'volver' => 'Volver a la agenda', 'volver_lista' => 'Volver al listado'
];

$mensaje = '';
$tipo_alerta = 'alert-success';

// Modo embebido: la pantalla se muestra dentro de la agenda (agenda_gestor.php)
// en un iframe. En ese modo no se dibuja la cabecera de la pagina, se agrega
// un boton de cierre y se avisa a la pagina padre cuando algo cambio.
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

$id_reserva = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
}

// ---------------------------------------------------------------------------
// Acciones
// ---------------------------------------------------------------------------
if ($id_reserva && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $texto['error_csrf'];
        $tipo_alerta = 'alert-error';
    } else {
        $accion = $_POST['accion'];

        // Registrar un cobro.
        if ($accion === 'registrar_pago') {
            $monto = trim((string) ($_POST['monto'] ?? ''));
            $metodo = strtolower(trim((string) ($_POST['metodo'] ?? 'efectivo')));
            $referencia = trim((string) ($_POST['referencia'] ?? ''));
            $notas = trim((string) ($_POST['notas'] ?? ''));

            if ($monto === '' || !is_numeric($monto) || (float) $monto <= 0) {
                $mensaje = $texto['error_monto'];
                $tipo_alerta = 'alert-error';
            } elseif (!array_key_exists($metodo, $texto['metodos'])) {
                $mensaje = $texto['error_metodo'];
                $tipo_alerta = 'alert-error';
            } else {
                try {
                    $insertar = $conexion->prepare('INSERT INTO pago (id_reserva, monto, metodo, estado, referencia, fecha_pago, registrado_por, notas) VALUES (:id_reserva, :monto, :metodo, :estado, :referencia, NOW(), :registrado_por, :notas)');
                    $insertar->execute([
                        'id_reserva' => $id_reserva,
                        'monto' => number_format((float) $monto, 2, '.', ''),
                        'metodo' => $metodo,
                        'estado' => 'pagado',
                        'referencia' => $referencia !== '' ? $referencia : null,
                        'registrado_por' => $cedula_usuario !== '' ? $cedula_usuario : null,
                        'notas' => $notas !== '' ? $notas : null
                    ]);
                    tgs_recalcular_estado_pago($conexion, (int) $id_reserva);
                    $mensaje = $texto['exito_cobro'];
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Anular un cobro.
        if ($accion === 'anular_pago') {
            $id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_VALIDATE_INT);
            if ($id_pago) {
                try {
                    $anular = $conexion->prepare("UPDATE pago SET estado = 'anulado' WHERE id_pago = :id_pago AND id_reserva = :id_reserva");
                    $anular->execute(['id_pago' => $id_pago, 'id_reserva' => $id_reserva]);
                    tgs_recalcular_estado_pago($conexion, (int) $id_reserva);
                    $mensaje = $texto['exito_anular'];
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Cambiar el estado del turno.
        if ($accion === 'cambiar_estado') {
            $estado = trim((string) ($_POST['estado_reserva'] ?? ''));
            $permitidos = ['Pendiente', 'En curso', 'Finalizada', 'Cancelada'];
            if (in_array($estado, $permitidos, true)) {
                try {
                    $actualizar = $conexion->prepare('UPDATE reserva SET estado = :estado WHERE id_reserva = :id_reserva');
                    $actualizar->execute(['estado' => $estado, 'id_reserva' => $id_reserva]);
                    $mensaje = $texto['exito_estado'];

                    // Al finalizar se avisa al cliente que su vehiculo esta listo.
                    if ($estado === 'Finalizada') {
                        $mensaje .= ' ' . tgs_avisar_reserva_finalizada($conexion, (int) $id_reserva);
                    }
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Reprogramar el turno (fecha, hora y sede).
        if ($accion === 'reprogramar') {
            $nueva_fecha = trim((string) ($_POST['nueva_fecha'] ?? ''));
            $nueva_hora = trim((string) ($_POST['nueva_hora'] ?? ''));
            $nueva_sede = filter_input(INPUT_POST, 'nueva_sede', FILTER_VALIDATE_INT);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nueva_fecha) || !preg_match('/^\d{2}:\d{2}$/', $nueva_hora)) {
                $mensaje = $texto['error_operacion'];
                $tipo_alerta = 'alert-error';
            } else {
                try {
                    $consulta = $conexion->prepare('SELECT id_taller FROM reserva WHERE id_reserva = :id_reserva');
                    $consulta->execute(['id_reserva' => $id_reserva]);
                    $sede_actual = (int) ($consulta->fetchColumn() ?: 0);
                    $sede_destino = $nueva_sede ?: $sede_actual;

                    $disponibilidad = tgs_comprobar_disponibilidad($conexion, (int) $sede_destino, $nueva_fecha, $nueva_hora, (int) $id_reserva);

                    if (!$disponibilidad['ok']) {
                        $mensaje = $texto['error_disponibilidad'] . $disponibilidad['mensaje'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        $actualizar = $conexion->prepare('UPDATE reserva SET fecha_reserva = :fecha, hora_reserva = :hora, id_taller = :id_taller WHERE id_reserva = :id_reserva');
                        $actualizar->execute([
                            'fecha' => $nueva_fecha,
                            'hora' => $nueva_hora,
                            'id_taller' => (int) $sede_destino,
                            'id_reserva' => $id_reserva
                        ]);

                        // Se deja constancia en el historial.
                        $historial = $conexion->prepare('INSERT INTO reserva_historial (id_reserva, campo, valor_anterior, valor_nuevo, usuario) VALUES (:id_reserva, :campo, :valor_anterior, :valor_nuevo, :usuario)');
                        $historial->execute([
                            'id_reserva' => $id_reserva,
                            'campo' => 'reprogramacion',
                            'valor_anterior' => $sede_actual . ' ' . $nueva_fecha,
                            'valor_nuevo' => $sede_destino . ' ' . $nueva_fecha . ' ' . $nueva_hora,
                            'usuario' => $nombre_usuario
                        ]);

                        // Aviso al cliente.
                        $cliente = $conexion->prepare('SELECT u.email, u.nombre, u.apellido FROM reserva r LEFT JOIN usuario u ON u.cedula = r.cedula_usuario WHERE r.id_reserva = :id_reserva LIMIT 1');
                        $cliente->execute(['id_reserva' => $id_reserva]);
                        $datos_cliente = $cliente->fetch(PDO::FETCH_ASSOC);
                        if ($datos_cliente && !empty($datos_cliente['email'])) {
                            $nombre_cliente = trim(($datos_cliente['nombre'] ?? '') . ' ' . ($datos_cliente['apellido'] ?? '')) ?: 'Cliente';
                            tgs_notificar($conexion, [
                                'id_reserva' => $id_reserva,
                                'email' => $datos_cliente['email'],
                                'asunto' => 'Tu turno fue reprogramado',
                                'cuerpo' => "Hola $nombre_cliente,\n\nTu turno fue reprogramado para el $nueva_fecha a las $nueva_hora.\n\nSaludos,\nTGS",
                                'tipo' => 'reprogramacion'
                            ]);
                        }

                        $mensaje = $texto['exito_reprogramar'];
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
// Datos del turno
// ---------------------------------------------------------------------------
$reserva = null;
if ($id_reserva) {
    try {
        $consulta = $conexion->prepare(
            "SELECT r.*, u.nombre, u.apellido, u.email, u.telefono,
                    v.marca, v.modelo, v.patente, v.anio,
                    s.nombre AS servicio_nombre, s.precio AS precio_servicio,
                    t.nombre AS taller_nombre, t.direccion AS taller_direccion
             FROM reserva r
             LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
             LEFT JOIN vehiculo v ON v.id_vehiculo = r.id_vehiculo
             LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
             LEFT JOIN taller t ON t.id_taller = r.id_taller
             WHERE r.id_reserva = :id_reserva LIMIT 1"
        );
        $consulta->execute(['id_reserva' => $id_reserva]);
        $reserva = $consulta->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $reserva = null;
    }
}

$cobros = [];
$presupuestos = [];
$avisos = [];
$historial = [];
$sedes = [];
$factura_reserva = null;

if ($reserva) {
    try {
        $stmt = $conexion->prepare('SELECT * FROM pago WHERE id_reserva = :id_reserva ORDER BY id_pago DESC');
        $stmt->execute(['id_reserva' => $id_reserva]);
        $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cobros = [];
    }

    try {
        $stmt = $conexion->prepare('SELECT * FROM presupuesto WHERE id_reserva = :id_reserva ORDER BY id_presupuesto DESC');
        $stmt->execute(['id_reserva' => $id_reserva]);
        $presupuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $presupuestos = [];
    }

    try {
        $stmt = $conexion->prepare("SELECT * FROM notificacion WHERE id_reserva = :id_reserva ORDER BY id_notificacion DESC");
        $stmt->execute(['id_reserva' => $id_reserva]);
        $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $avisos = [];
    }

    // Factura electronica emitida para este turno (si ya existe).
    $factura_reserva = tgs_factura_de_reserva($conexion, (int) $id_reserva);

    try {
        $stmt = $conexion->prepare('SELECT * FROM reserva_historial WHERE id_reserva = :id_reserva ORDER BY id_historial DESC LIMIT 20');
        $stmt->execute(['id_reserva' => $id_reserva]);
        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $historial = [];
    }
}

try {
    $stmt = $conexion->query('SELECT id_taller, nombre FROM taller ORDER BY nombre');
    $sedes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $sedes = [];
}

$total_pagado = 0.0;
foreach ($cobros as $cobro) {
    if (strtolower((string) ($cobro['estado'] ?? '')) === 'pagado') {
        $total_pagado += (float) ($cobro['monto'] ?? 0);
    }
}
$precio_total = $reserva && $reserva['precio_total'] !== null ? (float) $reserva['precio_total'] : null;
$saldo = $precio_total !== null ? $precio_total - $total_pagado : 0.0;
$estado_pago = strtolower(trim((string) ($reserva['estado_pago'] ?? 'pendiente')));
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
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        nav span { color: #94a3b8; font-size: 14px; }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; }
        nav a.btn-logout:hover { background-color: #dc2626; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .container { flex: 1; padding: 30px 20px 50px; max-width: 1150px; width: 100%; margin: 0 auto; }
        .encabezado { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 22px 26px; margin-bottom: 20px; }
        .encabezado h2 { font-size: 21px; color: #38bdf8; margin-bottom: 6px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .encabezado p { color: #cbd5e1; font-size: 14px; }
        .badge { display: inline-block; padding: 4px 11px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.pagado, .badge.aceptado, .badge.finalizada { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.parcial { background: rgba(249, 115, 22, 0.14); color: #fdba74; border-color: rgba(249, 115, 22, 0.35); }
        .badge.pendiente { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .badge.anulado, .badge.rechazado, .badge.cancelada { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .badge.en-curso { background: rgba(56, 189, 248, 0.15); color: #7dd3fc; }
        .rejilla { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .bloque { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 20px 22px; }
        .bloque h3 { font-size: 15px; color: #38bdf8; margin-bottom: 14px; }
        .dato { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5); font-size: 13px; }
        .dato:last-child { border-bottom: none; }
        .dato span:first-child { color: #94a3b8; }
        .dato span:last-child { color: #f8fafc; text-align: right; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 14px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; padding: 9px; text-align: left; font-weight: 600; }
        td { padding: 8px 9px; border-bottom: 1px solid #334155; color: #cbd5e1; }
        .form-cobro { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .form-cobro input, .form-cobro select { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 6px; padding: 7px 9px; font-size: 12px; }
        .form-cobro input[type="number"] { width: 95px; }
        .form-cobro input[type="text"] { width: 120px; }
        .form-cobro button, .btn { background: #2563eb; color: white; border: none; border-radius: 6px; padding: 8px 13px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .form-cobro button:hover, .btn:hover { background: #1d4ed8; }
        .btn-peligro { background: #ef4444; }
        .btn-peligro:hover { background: #dc2626; }
        .btn-neutro { background: #334155; color: #cbd5e1; }
        .btn-neutro:hover { background: #475569; }
        .acciones { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #22c55e; background: rgba(34, 197, 94, 0.1); color: #bbf7d0; font-size: 14px; }
        .alert-error { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 18px; font-size: 13px; }
        .volver { display: inline-block; margin-top: 18px; color: #38bdf8; text-decoration: none; font-size: 14px; font-weight: 600; }
        .volver:hover { text-decoration: underline; }
        /* Modo embebido: la pantalla se muestra dentro de la agenda */
        body.modo-embed { background: transparent; display: block; }
        body.modo-embed .container { padding: 14px; max-width: 100%; }
        body.modo-embed .bloque { padding: 16px 18px; }
        .embed-barra { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 16px; background-color: rgba(15, 23, 42, 0.95); border-bottom: 1px solid #334155; color: #38bdf8; font-size: 14px; }
        .embed-barra .btn { padding: 6px 12px; font-size: 12px; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/base-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
    <script src="js/fecha.js" defer></script>
</head>
<body<?php echo $embed ? ' class="modo-embed"' : ''; ?>>
    <?php if (!$embed): ?>
    <?php
# Cabecera comun del sitio: logo, navegacion del rol, idioma y salir.
$tgs_volver = isset($panel_volver) ? $panel_volver : '';
include 'includes/cabecera.php';
?>
    <?php else: ?>
        <!-- Cabecera minima del modo embebido, con boton de cierre -->
        <div class="embed-barra">
            <strong><?php echo $lang_code === 'en' ? 'Appointment' : 'Turno'; ?> #<?php echo (int) $id_reserva; ?></strong>
            <button type="button" class="btn btn-neutro" onclick="cerrarDetalle()"><?php echo $lang_code === 'en' ? 'Close' : 'Cerrar'; ?></button>
        </div>
    <?php endif; ?>

    <div class="container">
        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (!$reserva): ?>
            <div class="bloque estado-vacio"><?php echo $texto['no_existe']; ?></div>
        <?php else:
            $estado_turno = trim((string) ($reserva['estado'] ?? 'Pendiente'));
            // La clase CSS no puede tener espacios: "En curso" -> "en-curso".
            $clase_estado = str_replace(' ', '-', strtolower($estado_turno));
            $cliente_nombre = trim(($reserva['nombre'] ?? '') . ' ' . ($reserva['apellido'] ?? '')) ?: 'Cliente';
            $vehiculo_nombre = trim(($reserva['marca'] ?? '') . ' ' . ($reserva['modelo'] ?? '')) ?: '-';
        ?>
            <div class="encabezado">
                <h2>
                    <?php echo $lang_code === 'en' ? 'Appointment' : 'Turno'; ?> #<?php echo htmlspecialchars($reserva['id_reserva']); ?>
                    <span class="badge <?php echo htmlspecialchars($clase_estado); ?>"><?php echo htmlspecialchars($texto['estados'][$estado_turno] ?? $estado_turno); ?></span>
                    <span class="badge <?php echo htmlspecialchars($estado_pago); ?>"><?php echo htmlspecialchars($texto['pagos'][$estado_pago] ?? $estado_pago); ?></span>
                </h2>
                <p><?php echo $texto['resumen']; ?></p>
            </div>

            <div class="rejilla">
                <!-- Cliente, vehiculo y turno -->
                <div class="bloque">
                    <h3><?php echo $texto['cliente']; ?> &amp; <?php echo $texto['vehiculo']; ?></h3>
                    <div class="dato"><span><?php echo $texto['cliente']; ?></span><span><?php echo htmlspecialchars($cliente_nombre); ?></span></div>
                    <div class="dato"><span><?php echo $texto['correo']; ?></span><span><?php echo htmlspecialchars($reserva['email'] ?? '-'); ?></span></div>
                    <div class="dato"><span><?php echo $texto['telefono']; ?></span><span><?php echo htmlspecialchars($reserva['telefono'] ?? '-'); ?></span></div>
                    <div class="dato"><span><?php echo $texto['vehiculo']; ?></span><span><?php echo htmlspecialchars($vehiculo_nombre); ?></span></div>
                    <div class="dato"><span><?php echo $texto['patente']; ?></span><span><?php echo htmlspecialchars($reserva['patente'] ?? '-'); ?></span></div>
                    <div class="dato"><span><?php echo $texto['anio']; ?></span><span><?php echo htmlspecialchars($reserva['anio'] ?? '-'); ?></span></div>
                    <div class="dato"><span><?php echo $texto['servicio']; ?></span><span><?php echo htmlspecialchars($reserva['servicio_nombre'] ?? '-'); ?></span></div>
                    <div class="dato"><span><?php echo $texto['sede']; ?></span><span><?php echo htmlspecialchars($reserva['taller_nombre'] ?? '-'); ?></span></div>
                    <div class="dato"><span><?php echo $texto['fecha']; ?></span><span><?php echo htmlspecialchars(tgs_fecha_hora_legible($reserva['fecha_reserva'] ?? '-', (string) ($reserva['hora_reserva'] ?? ''), $lang_code)); ?></span></div>
                    <div class="dato"><span><?php echo $texto['total']; ?></span><span><?php echo $precio_total !== null ? htmlspecialchars(number_format($precio_total, 2, ',', '.')) . ' USD' : '-'; ?></span></div>
                    <div class="dato"><span><?php echo $texto['saldo']; ?></span><span><?php echo htmlspecialchars(number_format($saldo, 2, ',', '.')); ?> USD</span></div>
                </div>

                <!-- Acciones sobre el turno -->
                <div class="bloque">
                    <h3><?php echo $texto['acciones']; ?></h3>

                    <!-- Cambiar estado -->
                    <form method="POST" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="accion" value="cambiar_estado">
                        <input type="hidden" name="id_reserva" value="<?php echo (int) $reserva['id_reserva']; ?>">
                        <select name="estado_reserva" style="background: rgba(15,23,42,0.9); color: white; border: 1px solid #475569; border-radius: 6px; padding: 8px; font-size: 13px;">
                            <?php foreach ($texto['estados'] as $valor_estado => $nombre_estado): ?>
                                <option value="<?php echo htmlspecialchars($valor_estado); ?>" <?php echo ($estado_turno === $valor_estado) ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombre_estado); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn"><?php echo $texto['cambiar_estado']; ?></button>
                    </form>

                    <!-- Reprogramar -->
                    <form method="POST" style="margin-top: 12px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="accion" value="reprogramar">
                        <input type="hidden" name="id_reserva" value="<?php echo (int) $reserva['id_reserva']; ?>">
                        <div class="form-cobro">
                            <?php echo tgs_campo_fecha('nueva_fecha', (string) ($reserva['fecha_reserva'] ?? ''), $lang_code, array('required' => true)); ?>
                            <input type="time" name="nueva_hora" value="<?php echo htmlspecialchars(substr((string) ($reserva['hora_reserva'] ?? ''), 0, 5)); ?>" required>
                            <select name="nueva_sede">
                                <?php foreach ($sedes as $sede): ?>
                                    <option value="<?php echo (int) $sede['id_taller']; ?>" <?php echo ((int) ($reserva['id_taller'] ?? 0) === (int) $sede['id_taller']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sede['nombre'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn"><?php echo $texto['reprogramar']; ?></button>
                        </div>
                    </form>

                    <!-- Registrar cobro -->
                    <h3 style="margin-top: 18px;"><?php echo $texto['registrar_cobro']; ?></h3>
                    <form method="POST" class="form-cobro">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="accion" value="registrar_pago">
                        <input type="hidden" name="id_reserva" value="<?php echo (int) $reserva['id_reserva']; ?>">
                        <input type="number" step="0.01" min="0.01" name="monto" placeholder="<?php echo $texto['monto']; ?>" required>
                        <select name="metodo">
                            <?php foreach ($texto['metodos'] as $clave_metodo => $nombre_metodo): ?>
                                <option value="<?php echo htmlspecialchars($clave_metodo); ?>"><?php echo htmlspecialchars($nombre_metodo); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="referencia" maxlength="60" placeholder="<?php echo $texto['referencia']; ?>">
                        <input type="text" name="notas" maxlength="255" placeholder="<?php echo $texto['notas']; ?>">
                        <button type="submit"><?php echo $texto['registrar_cobro']; ?></button>
                    </form>
                </div>
            </div>

            <!-- Cobros del turno -->
            <div class="bloque" style="margin-top: 20px;">
                <h3><?php echo $texto['cobros']; ?></h3>
                <?php if (!empty($factura_reserva)): ?>
                    <!-- Factura electronica: el cliente puede descargarla -->
                    <p style="margin-bottom: 12px; font-size: 13px; color: #cbd5e1;">
                        <?php echo $lang_code === 'en' ? 'Invoice' : 'Factura'; ?>:
                        <a href="factura.php?id=<?php echo (int) $factura_reserva['id_factura']; ?>" target="_blank" class="tgs-btn chico"><?php echo htmlspecialchars($factura_reserva['numero']); ?></a>
                        (<?php echo htmlspecialchars($factura_reserva['fecha_emision']); ?> &middot; <?php echo htmlspecialchars(number_format((float) $factura_reserva['total'], 2, ',', '.')); ?> USD)
                    </p>
                <?php endif; ?>
                <?php if (empty($cobros)): ?>
                    <p class="estado-vacio"><?php echo $texto['sin_cobros']; ?></p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $texto['monto']; ?></th>
                                <th><?php echo $texto['metodo']; ?></th>
                                <th><?php echo $texto['referencia']; ?></th>
                                <th><?php echo $texto['estado']; ?></th>
                                <th><?php echo $texto['fecha']; ?></th>
                                <th><?php echo $texto['acciones']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cobros as $cobro):
                                $estado_cobro = strtolower(trim((string) ($cobro['estado'] ?? 'pagado')));
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(number_format((float) ($cobro['monto'] ?? 0), 2, ',', '.')); ?></td>
                                    <td><?php echo htmlspecialchars($texto['metodos'][$cobro['metodo'] ?? ''] ?? ($cobro['metodo'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($cobro['referencia'] ?? '-'); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($estado_cobro); ?>"><?php echo htmlspecialchars($texto['pagos'][$estado_cobro] ?? $estado_cobro); ?></span></td>
                                    <td><?php echo htmlspecialchars(tgs_fecha_legible($cobro['fecha_pago'] ?? '-', $lang_code)); ?></td>
                                    <td>
                                        <?php if ($estado_cobro !== 'anulado'): ?>
                                            <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Void this payment?' : '¿Anular este cobro?'; ?>');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                                <input type="hidden" name="accion" value="anular_pago">
                                                <input type="hidden" name="id_reserva" value="<?php echo (int) $reserva['id_reserva']; ?>">
                                                <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($cobro['id_pago'] ?? ''); ?>">
                                                <button type="submit" class="btn btn-peligro"><?php echo $texto['anular']; ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="rejilla" style="margin-top: 20px;">
                <!-- Presupuestos del turno -->
                <div class="bloque">
                    <h3><?php echo $texto['presupuestos']; ?></h3>
                    <?php if (empty($presupuestos)): ?>
                        <p class="estado-vacio"><?php echo $texto['sin_presupuestos']; ?></p>
                    <?php else: ?>
                        <?php foreach ($presupuestos as $presupuesto):
                            $estado_presupuesto = strtolower(trim((string) ($presupuesto['estado'] ?? 'pendiente')));
                        ?>
                            <div class="dato">
                                <span>#<?php echo htmlspecialchars($presupuesto['id_presupuesto']); ?> · <?php echo htmlspecialchars(tgs_fecha_legible($presupuesto['fecha_presupuesto'] ?? '-', $lang_code)); ?></span>
                                <span>
                                    <?php echo htmlspecialchars(number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.')); ?> USD
                                    <span class="badge <?php echo htmlspecialchars($estado_presupuesto); ?>"><?php echo htmlspecialchars($texto['estados_presupuesto'][$estado_presupuesto] ?? $estado_presupuesto); ?></span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Avisos enviados -->
                <div class="bloque">
                    <h3><?php echo $texto['avisos']; ?></h3>
                    <?php if (empty($avisos)): ?>
                        <p class="estado-vacio"><?php echo $texto['sin_avisos']; ?></p>
                    <?php else: ?>
                        <?php foreach ($avisos as $aviso):
                            $estado_aviso = strtolower(trim((string) ($aviso['estado'] ?? 'pendiente')));
                        ?>
                            <div class="dato">
                                <span><?php echo htmlspecialchars($aviso['asunto'] ?? ''); ?><br><small style="color:#64748b;"><?php echo htmlspecialchars($aviso['created_at'] ?? ''); ?></small></span>
                                <span><span class="badge <?php echo htmlspecialchars($estado_aviso); ?>"><?php echo htmlspecialchars($texto['pagos'][$estado_aviso] ?? $estado_aviso); ?></span></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial -->
            <div class="bloque" style="margin-top: 20px;">
                <h3><?php echo $texto['historial']; ?></h3>
                <?php if (empty($historial)): ?>
                    <p class="estado-vacio"><?php echo $texto['sin_historial']; ?></p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $texto['campo']; ?></th>
                                <th><?php echo $texto['anterior']; ?></th>
                                <th><?php echo $texto['nuevo']; ?></th>
                                <th><?php echo $texto['usuario']; ?></th>
                                <th><?php echo $texto['fecha']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $cambio): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cambio['campo'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cambio['valor_anterior'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cambio['valor_nuevo'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cambio['usuario'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cambio['fecha_cambio'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="acciones">
                <a href="agenda_gestor.php?fecha=<?php echo htmlspecialchars($reserva['fecha_reserva'] ?? ''); ?>" class="btn btn-neutro"><?php echo $texto['volver']; ?></a>
                <a href="administrar_reservas.php" class="btn btn-neutro"><?php echo $texto['volver_lista']; ?></a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$embed): ?>
    <?php include 'includes/pie.php'; ?>
    <?php endif; ?>

    <?php if ($embed): ?>
    <script>
        // En modo embebido la pagina avisa a la agenda (pagina padre) que
        //algo cambio, para que actualice la grilla sin recargarla a mano.
        function cerrarDetalle() {
            if (window.parent !== window) {
                window.parent.postMessage({ tipo: 'tgs-cerrar' }, window.location.origin);
            }
        }
        <?php if (!empty($mensaje) && $tipo_alerta === 'alert-success'): ?>
        if (window.parent !== window) {
            window.parent.postMessage({ tipo: 'tgs-actualizar' }, window.location.origin);
        }
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
