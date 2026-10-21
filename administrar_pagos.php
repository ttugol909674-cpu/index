<?php
/**
 * TGS - Taller Global de Servicios
 * Administracion de pagos y facturacion.
 *
 * Pantalla para gestores y administradores que permite:
 *  - Ver el estado de cobro de cada reserva (pendiente, parcial o pagado).
 *  - Registrar cobros (monto, metodo, referencia y notas).
 *  - Anular un cobro mal registrado (queda marcado, no se borra).
 *  - Ajustar el total a cobrar de una reserva (precio acordado).
 *  - Filtrar por situacion de cobro o buscar por cliente o servicio.
 *
 * Reglas de negocio:
 * - El monto debe ser mayor a cero.
 * - El metodo de pago es una lista cerrada: efectivo, tarjeta, transferencia.
 * - No se registran cobros en reservas canceladas.
 * - El estado de cobro de la reserva se recalcula en cada movimiento:
 *   pendiente (nada cobrado), parcial (cobrado menos que el total) o
 *   pagado (cobrado al menos el total).
 *
 * Seguridad:
 * - Verificacion de rol (admin/gestor) y token CSRF en cada accion.
 * - Consultas preparadas para todos los accesos a la base de datos.
 * - Salida de datos con htmlspecialchars.
 *
 * Dependencias: auth.php, conexion.php
 */
require_once 'auth.php';
verificarPermiso('administrar_pagos');
require_once 'conexion.php';
require_once 'funciones_pago.php';

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
    'titulo' => 'Payments & Billing', 'inicio' => 'Back to Home', 'panel' => 'Panel', 'hola' => 'Hello', 'salir' => 'Sign Out',
    'listado' => 'Payments and Billing', 'resumen' => 'Check the collection status of each reservation, register payments and adjust the amount to charge.',
    'cliente' => 'Customer', 'servicio' => 'Service', 'fecha' => 'Date', 'total' => 'Total', 'pagado' => 'Paid', 'saldo' => 'Balance',
    'estado_cobro' => 'Collection status', 'acciones' => 'Actions', 'registrar' => 'Register payment', 'monto' => 'Amount',
    'metodo' => 'Method', 'referencia' => 'Reference', 'notas' => 'Notes', 'anular' => 'Void', 'cobros' => 'Registered payments',
    'sin_cobros' => 'No payments registered yet.', 'sin_reservas' => 'There are no reservations to bill.',
    'facturado' => 'Total billed', 'cobrado' => 'Total collected', 'pendiente' => 'Pending collection',
    'filtro_estado' => 'Filter by collection status', 'todos' => 'All', 'buscar' => 'Search',
    'buscar_placeholder' => 'Customer or service', 'limpiar' => 'Clear filters', 'ajustar_total' => 'Set total',
    'pago_registrado' => 'Payment registered successfully.', 'pago_anulado' => 'Payment voided successfully.', 'pago_aprobado' => 'Reported payment approved.', 'pago_rechazado' => 'Reported payment rejected.',
    'total_actualizado' => 'Total amount updated successfully.',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_monto' => 'Enter an amount greater than zero.', 'error_metodo' => 'Select a valid payment method.',
    'error_reserva' => 'The reservation could not be found.', 'error_cancelada' => 'Payments cannot be registered on a cancelled reservation.',
    'error_operacion' => 'The operation could not be completed.', 'monto_requerido' => 'Amount is required.',
    'metodos' => ['efectivo' => 'Cash', 'tarjeta' => 'Card', 'transferencia' => 'Transfer', 'prepago' => 'Prepaid account'],
    'estados' => ['pendiente' => 'Pending', 'parcial' => 'Partial', 'pagado' => 'Paid', 'reportado' => 'Reported', 'rechazado' => 'Rejected'],
    'registrado_por' => 'Registered by', 'estado_pago' => 'Status', 'anulado' => 'Void', 'reportados' => 'Payments reported by customers', 'aprobar' => 'Approve', 'rechazar' => 'Reject', 'sin_reportados' => 'There are no reported payments to review.'
] : [
    'titulo' => 'Pagos y Facturación', 'inicio' => 'Volver al Inicio', 'panel' => 'Panel', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'listado' => 'Pagos y Facturación', 'resumen' => 'Revisá la situación de cobro de cada reserva, registré pagos y ajustá el total a cobrar.',
    'cliente' => 'Cliente', 'servicio' => 'Servicio', 'fecha' => 'Fecha', 'total' => 'Total', 'pagado' => 'Cobrado', 'saldo' => 'Saldo',
    'estado_cobro' => 'Estado de cobro', 'acciones' => 'Acciones', 'registrar' => 'Registrar cobro', 'monto' => 'Monto',
    'metodo' => 'Método', 'referencia' => 'Referencia', 'notas' => 'Notas', 'anular' => 'Anular', 'cobros' => 'Cobros registrados',
    'sin_cobros' => 'Todavía no hay cobros registrados.', 'sin_reservas' => 'No hay reservas para facturar.',
    'facturado' => 'Total facturado', 'cobrado' => 'Total cobrado', 'pendiente' => 'Saldo pendiente',
    'filtro_estado' => 'Filtrar por estado de cobro', 'todos' => 'Todos', 'buscar' => 'Buscar',
    'buscar_placeholder' => 'Cliente o servicio', 'limpiar' => 'Limpiar filtros', 'ajustar_total' => 'Fijar total',
    'pago_registrado' => 'Cobro registrado correctamente.', 'pago_anulado' => 'Cobro anulado correctamente.', 'pago_aprobado' => 'Cobro reportado aprobado.', 'pago_rechazado' => 'Cobro reportado rechazado.',
    'total_actualizado' => 'Total actualizado correctamente.',
    'error_csrf' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
    'error_monto' => 'Ingresá un monto mayor a cero.', 'error_metodo' => 'Seleccioná un método de pago válido.',
    'error_reserva' => 'No se encontró la reserva.', 'error_cancelada' => 'No se pueden registrar cobros en reservas canceladas.',
    'error_operacion' => 'No se pudo completar la operación.', 'monto_requerido' => 'El monto es obligatorio.',
    'metodos' => ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'prepago' => 'Cuenta prepaga'],
    'estados' => ['pendiente' => 'Pendiente', 'parcial' => 'Parcial', 'pagado' => 'Pagado', 'reportado' => 'Reportado', 'rechazado' => 'Rechazado'],
    'registrado_por' => 'Registrado por', 'estado_pago' => 'Estado', 'anulado' => 'Anulado', 'reportados' => 'Cobros reportados por los clientes', 'aprobar' => 'Aprobar', 'rechazar' => 'Rechazar', 'sin_reportados' => 'No hay cobros reportados para revisar.'
];

$mensaje = '';
$tipo_alerta = 'alert-success';

// Metodos de pago admitidos.
$metodos_validos = array_keys($texto['metodos']);

/**
 * Recalcula el estado de cobro de una reserva a partir de los cobros
 * vigentes y actualiza la columna estado_pago de la tabla reserva.
 * La implementacion vive en funciones_pago.php (se usa tambien desde
 * detalle_reserva.php).
 */
function recalcularEstadoPago(PDO $conexion, int $id_reserva): void
{
    tgs_recalcular_estado_pago($conexion, $id_reserva);
}

// ---------------------------------------------------------------------------
// Procesamiento de acciones POST
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $texto['error_csrf'];
        $tipo_alerta = 'alert-error';
    } elseif (isset($_POST['accion'])) {
        $accion = $_POST['accion'];
        $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);

        // Accion: registrar un cobro sobre la reserva.
        if ($accion === 'registrar_pago' && $id_reserva) {
            $monto = trim((string) ($_POST['monto'] ?? ''));
            $metodo = strtolower(trim((string) ($_POST['metodo'] ?? '')));
            $referencia = trim((string) ($_POST['referencia'] ?? ''));
            $notas = trim((string) ($_POST['notas'] ?? ''));

            if ($monto === '') {
                $mensaje = $texto['monto_requerido'];
                $tipo_alerta = 'alert-error';
            } elseif (!is_numeric($monto) || (float) $monto <= 0) {
                $mensaje = $texto['error_monto'];
                $tipo_alerta = 'alert-error';
            } elseif (!in_array($metodo, $metodos_validos, true)) {
                $mensaje = $texto['error_metodo'];
                $tipo_alerta = 'alert-error';
            } else {
                try {
                    // La reserva debe existir y no estar cancelada.
                    $consulta = $conexion->prepare('SELECT id_reserva, estado FROM reserva WHERE id_reserva = :id_reserva LIMIT 1');
                    $consulta->execute(['id_reserva' => $id_reserva]);
                    $reserva = $consulta->fetch(PDO::FETCH_ASSOC);

                    if (!$reserva) {
                        $mensaje = $texto['error_reserva'];
                        $tipo_alerta = 'alert-error';
                    } elseif (strtolower(trim((string) ($reserva['estado'] ?? ''))) === 'cancelada') {
                        $mensaje = $texto['error_cancelada'];
                        $tipo_alerta = 'alert-error';
                    } else {
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

                        recalcularEstadoPago($conexion, $id_reserva);
                        $mensaje = $texto['pago_registrado'];
                    }
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Accion: aprobar un pago reportado por el cliente. Pasa a 'pagado'
        // y recalcula el estado de cobro de la reserva.
        if ($accion === 'aprobar_pago') {
            $id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_VALIDATE_INT);
            if ($id_pago) {
                try {
                    $buscar = $conexion->prepare('SELECT id_pago, id_reserva, metodo FROM pago WHERE id_pago = :id_pago AND estado = :estado LIMIT 1');
                    $buscar->execute(['id_pago' => $id_pago, 'estado' => 'reportado']);
                    $pago = $buscar->fetch(PDO::FETCH_ASSOC);

                    if (!$pago) {
                        $mensaje = $texto['error_operacion'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        $aprobar = $conexion->prepare("UPDATE pago SET estado = 'pagado' WHERE id_pago = :id_pago");
                        $aprobar->execute(['id_pago' => $id_pago]);
                        tgs_recalcular_estado_pago($conexion, (int) $pago['id_reserva']);
                        // Al concretar el pago se emite la factura electronica
                        // que el cliente puede descargar.
                        $factura = tgs_generar_factura($conexion, (int) $pago['id_reserva'], (string) ($pago['metodo'] ?? 'efectivo'));
                        $mensaje = $texto['pago_aprobado'];
                        if ($factura) {
                            $mensaje .= ' ' . ($lang_code === 'en'
                                ? 'Invoice issued.'
                                : 'Factura emitida.');
                        }
                    }
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Accion: rechazar un pago reportado por el cliente.
        if ($accion === 'rechazar_pago') {
            $id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_VALIDATE_INT);
            if ($id_pago) {
                try {
                    $buscar = $conexion->prepare('SELECT id_pago, id_reserva, metodo FROM pago WHERE id_pago = :id_pago AND estado = :estado LIMIT 1');
                    $buscar->execute(['id_pago' => $id_pago, 'estado' => 'reportado']);
                    $pago = $buscar->fetch(PDO::FETCH_ASSOC);

                    if (!$pago) {
                        $mensaje = $texto['error_operacion'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        $rechazar = $conexion->prepare("UPDATE pago SET estado = 'rechazado' WHERE id_pago = :id_pago");
                        $rechazar->execute(['id_pago' => $id_pago]);
                        tgs_recalcular_estado_pago($conexion, (int) $pago['id_reserva']);
                        $mensaje = $texto['pago_rechazado'];
                    }
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Accion: anular un cobro (no se borra, queda registrado como anulado).
        if ($accion === 'anular_pago') {
            $id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_VALIDATE_INT);
            if ($id_pago) {
                try {
                    $buscar = $conexion->prepare('SELECT id_reserva FROM pago WHERE id_pago = :id_pago LIMIT 1');
                    $buscar->execute(['id_pago' => $id_pago]);
                    $pago = $buscar->fetch(PDO::FETCH_ASSOC);

                    if (!$pago) {
                        $mensaje = $texto['error_operacion'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        $anular = $conexion->prepare("UPDATE pago SET estado = 'anulado' WHERE id_pago = :id_pago");
                        $anular->execute(['id_pago' => $id_pago]);
                        recalcularEstadoPago($conexion, (int) $pago['id_reserva']);
                        $mensaje = $texto['pago_anulado'];
                    }
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        // Accion: ajustar el total a cobrar de la reserva.
        if ($accion === 'actualizar_total' && $id_reserva) {
            $nuevo_total = trim((string) ($_POST['precio_total'] ?? ''));
            if ($nuevo_total === '' || !is_numeric($nuevo_total) || (float) $nuevo_total < 0) {
                $mensaje = $texto['error_monto'];
                $tipo_alerta = 'alert-error';
            } else {
                try {
                    $actualizar = $conexion->prepare('UPDATE reserva SET precio_total = :precio_total WHERE id_reserva = :id_reserva');
                    $actualizar->execute([
                        'precio_total' => number_format((float) $nuevo_total, 2, '.', ''),
                        'id_reserva' => $id_reserva
                    ]);
                    recalcularEstadoPago($conexion, $id_reserva);
                    $mensaje = $texto['total_actualizado'];
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Filtros del listado (GET)
// ---------------------------------------------------------------------------
$filtro_estado = strtolower(trim($_GET['estado_pago'] ?? ''));
$estados_validos = ['pendiente', 'parcial', 'pagado', 'reportado', 'rechazado'];
if (!in_array($filtro_estado, $estados_validos, true)) {
    $filtro_estado = '';
}

$filtro_texto = trim($_GET['q'] ?? '');

// ---------------------------------------------------------------------------
// Consultas: reservas con su estado de cobro
// ---------------------------------------------------------------------------
$condiciones = [];
$parametros = [];

if ($filtro_estado !== '') {
    $condiciones[] = 'r.estado_pago = :estado_pago';
    $parametros['estado_pago'] = $filtro_estado;
}

if ($filtro_texto !== '') {
    $termino = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtro_texto) . '%';
    $condiciones[] = "(u.nombre LIKE :texto ESCAPE '!' OR u.apellido LIKE :texto ESCAPE '!' OR s.nombre LIKE :texto ESCAPE '!')";
    $parametros['texto'] = $termino;
}

$sql_reservas = "SELECT r.id_reserva, r.cedula_usuario, r.fecha_reserva, r.hora_reserva, r.estado, r.precio_total, r.estado_pago,
                        u.nombre, u.apellido, u.email, s.nombre AS servicio_nombre,
                        COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado'), 0) AS total_pagado
                 FROM reserva r
                 LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
                 LEFT JOIN servicio s ON s.id_servicio = r.id_servicio";
if (!empty($condiciones)) {
    $sql_reservas .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql_reservas .= ' ORDER BY r.id_reserva DESC';

$reservas = [];
try {
    $stmt = $conexion->prepare($sql_reservas);
    $stmt->execute($parametros);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservas = [];
}

// ---------------------------------------------------------------------------
// Consultas: cobros reportados por los clientes, a la espera de validacion
// ---------------------------------------------------------------------------
$reportados = [];
try {
    $sql_reportados = "SELECT p.*, u.nombre, u.apellido, u.email, r.fecha_reserva
                       FROM pago p
                       LEFT JOIN reserva r ON r.id_reserva = p.id_reserva
                       LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
                       WHERE p.estado = 'reportado'
                       ORDER BY p.id_pago DESC";
    $stmt_reportados = $conexion->query($sql_reportados);
    $reportados = $stmt_reportados ? $stmt_reportados->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $reportados = [];
}

// ---------------------------------------------------------------------------
// Consultas: cobros registrados (los mas recientes primero)
// ---------------------------------------------------------------------------
$cobros = [];
try {
    $sql_cobros = "SELECT p.*, u.nombre, u.apellido FROM pago p
                   LEFT JOIN reserva r ON r.id_reserva = p.id_reserva
                   LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
                   ORDER BY p.id_pago DESC LIMIT 40";
    $stmt_cobros = $conexion->query($sql_cobros);
    $cobros = $stmt_cobros ? $stmt_cobros->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $cobros = [];
}

// ---------------------------------------------------------------------------
// Resumen general de facturacion (sin filtros)
// ---------------------------------------------------------------------------
$resumen = ['facturado' => 0.0, 'cobrado' => 0.0, 'saldo' => 0.0, 'reservas' => 0];
try {
    $totales = $conexion->query("SELECT COALESCE(SUM(r.precio_total), 0) AS facturado, COUNT(*) AS reservas FROM reserva r");
    $fila = $totales ? $totales->fetch(PDO::FETCH_ASSOC) : null;
    $resumen['facturado'] = (float) ($fila['facturado'] ?? 0);
    $resumen['reservas'] = (int) ($fila['reservas'] ?? 0);

    $cobrado = $conexion->query("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE estado = 'pagado'");
    $resumen['cobrado'] = (float) ($cobrado ? $cobrado->fetchColumn() : 0);
    $resumen['saldo'] = $resumen['facturado'] - $resumen['cobrado'];
} catch (PDOException $e) {
    // Se muestran los valores en cero si la consulta falla.
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
        .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .resumen-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 18px 20px; }
        .resumen-card span { display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
        .resumen-card strong { font-size: 22px; color: #38bdf8; }
        .resumen-card strong.alerta-valor { color: #fbbf24; }
        table { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; overflow: hidden; margin-bottom: 26px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #334155; font-size: 13px; vertical-align: middle; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; }
        .tabla-scroll { overflow-x: auto; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.pendiente { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .badge.parcial { background: rgba(249, 115, 22, 0.14); color: #fdba74; border-color: rgba(249, 115, 22, 0.35); }
        .badge.pagado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.anulado { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .form-cobro { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .form-cobro input, .form-cobro select { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 6px; padding: 6px 8px; font-size: 12px; }
        .form-cobro input[type="number"] { width: 95px; }
        .form-cobro input[type="text"] { width: 110px; }
        .form-cobro input[type="hidden"] { display: none; }
        .form-cobro button, .btn-anular { background: #2563eb; color: white; border: none; border-radius: 6px; padding: 7px 12px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .form-cobro button:hover { background: #1d4ed8; }
        .btn-anular { background: #ef4444; }
        .btn-anular:hover { background: #dc2626; }
        .filtros-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px; }
        .filtros-bar input, .filtros-bar select { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 9px 12px; font-size: 13px; }
        .filtros-bar button { background: #2563eb; color: white; border: none; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .filtros-bar a { color: #38bdf8; font-size: 13px; font-weight: 600; text-decoration: none; }
        .filtros-bar a:hover { text-decoration: underline; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 24px; font-size: 14px; }
        .bloque { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 20px 22px; }
        .bloque h3 { font-size: 15px; color: #38bdf8; margin-bottom: 14px; }
        .btn-pago-aprobar { background: #22c55e; color: white; border: none; border-radius: 6px; padding: 7px 13px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn-pago-aprobar:hover { background: #16a34a; }
        .btn-pago-rechazar { background: #ef4444; color: white; border: none; border-radius: 6px; padding: 7px 13px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn-pago-rechazar:hover { background: #dc2626; }
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
            <!-- Selector de idioma de la administracion de pagos -->
            <div class="language-selector"><a href="administrar_pagos.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a><a href="administrar_pagos.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a></div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong> <span style="font-size: 11px; color: #38bdf8;">(<?php echo htmlspecialchars(ucfirst($rol_actual)); ?>)</span></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $texto['listado']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Cobros reportados por los clientes: el taller los valida -->
        <div class="bloque" style="margin-bottom: 24px;">
            <h3><?php echo $texto['reportados']; ?></h3>
            <?php if (empty($reportados)): ?>
                <p class="estado-vacio"><?php echo $texto['sin_reportados']; ?></p>
            <?php else: ?>
                <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $lang_code === 'en' ? 'Customer' : 'Cliente'; ?></th>
                                <th><?php echo $texto['monto']; ?></th>
                                <th><?php echo $texto['metodo']; ?></th>
                                <th><?php echo $texto['referencia']; ?></th>
                                <th><?php echo $texto['notas']; ?></th>
                                <th><?php echo $texto['fecha']; ?></th>
                                <th><?php echo $texto['acciones']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportados as $reportado):
                                $cliente_reportado = trim(($reportado['nombre'] ?? '') . ' ' . ($reportado['apellido'] ?? '')) ?: 'Cliente';
                            ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($cliente_reportado); ?>
                                        <br><small style="color:#94a3b8;"><?php echo htmlspecialchars($reportado['email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(number_format((float) ($reportado['monto'] ?? 0), 2, ',', '.')); ?></td>
                                    <td><?php echo htmlspecialchars($texto['metodos'][$reportado['metodo'] ?? ''] ?? ($reportado['metodo'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($reportado['referencia'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($reportado['notas'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($reportado['fecha_pago'] ?? '-'); ?></td>
                                    <td style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion" value="aprobar_pago">
                                            <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($reportado['id_pago'] ?? ''); ?>">
                                            <button type="submit" class="btn-pago-aprobar"><?php echo $texto['aprobar']; ?></button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Reject this reported payment?' : '¿Rechazar este cobro reportado?'; ?>');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion" value="rechazar_pago">
                                            <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($reportado['id_pago'] ?? ''); ?>">
                                            <button type="submit" class="btn-pago-rechazar"><?php echo $texto['rechazar']; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Resumen general de facturacion -->
        <div class="resumen-grid">
            <div class="resumen-card">
                <span><?php echo $texto['facturado']; ?></span>
                <strong><?php echo htmlspecialchars(number_format($resumen['facturado'], 2, ',', '.')); ?> USD</strong>
            </div>
            <div class="resumen-card">
                <span><?php echo $texto['cobrado']; ?></span>
                <strong><?php echo htmlspecialchars(number_format($resumen['cobrado'], 2, ',', '.')); ?> USD</strong>
            </div>
            <div class="resumen-card">
                <span><?php echo $texto['pendiente']; ?></span>
                <strong class="<?php echo $resumen['saldo'] > 0.009 ? 'alerta-valor' : ''; ?>"><?php echo htmlspecialchars(number_format($resumen['saldo'], 2, ',', '.')); ?> USD</strong>
            </div>
        </div>

        <!-- Filtros del listado: estado de cobro y busqueda por cliente o servicio -->
        <form action="administrar_pagos.php" method="GET" class="filtros-bar">
            <select name="estado_pago">
                <option value=""><?php echo $texto['filtro_estado']; ?>: <?php echo $texto['todos']; ?></option>
                <option value="pendiente" <?php echo ($filtro_estado === 'pendiente') ? 'selected' : ''; ?>><?php echo $texto['estados']['pendiente']; ?></option>
                <option value="parcial" <?php echo ($filtro_estado === 'parcial') ? 'selected' : ''; ?>><?php echo $texto['estados']['parcial']; ?></option>
                <option value="pagado" <?php echo ($filtro_estado === 'pagado') ? 'selected' : ''; ?>><?php echo $texto['estados']['pagado']; ?></option>
                <option value="reportado" <?php echo ($filtro_estado === 'reportado') ? 'selected' : ''; ?>><?php echo $texto['estados']['reportado']; ?></option>
                <option value="rechazado" <?php echo ($filtro_estado === 'rechazado') ? 'selected' : ''; ?>><?php echo $texto['estados']['rechazado']; ?></option>
            </select>
            <input type="text" name="q" maxlength="150" value="<?php echo htmlspecialchars($filtro_texto); ?>" placeholder="<?php echo htmlspecialchars($texto['buscar_placeholder']); ?>">
            <button type="submit"><?php echo $texto['buscar']; ?></button>
            <?php if ($filtro_estado !== '' || $filtro_texto !== ''): ?>
                <a href="administrar_pagos.php"><?php echo $texto['limpiar']; ?></a>
            <?php endif; ?>
        </form>

        <!-- Listado de reservas con su situacion de cobro -->
        <div class="tabla-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo $texto['cliente']; ?></th>
                        <th><?php echo $texto['servicio']; ?></th>
                        <th><?php echo $texto['fecha']; ?></th>
                        <th><?php echo $texto['total']; ?></th>
                        <th><?php echo $texto['pagado']; ?></th>
                        <th><?php echo $texto['saldo']; ?></th>
                        <th><?php echo $texto['estado_cobro']; ?></th>
                        <th><?php echo $texto['registrar']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservas)): ?>
                        <tr>
                            <td colspan="9" class="estado-vacio"><?php echo $texto['sin_reservas']; ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reservas as $reserva):
                            $id_res = (int) ($reserva['id_reserva'] ?? 0);
                            $precio_total = $reserva['precio_total'] !== null ? (float) $reserva['precio_total'] : null;
                            $total_pagado = (float) ($reserva['total_pagado'] ?? 0);
                            $saldo = $precio_total !== null ? $precio_total - $total_pagado : null;
                            $estado_cobro = strtolower(trim((string) ($reserva['estado_pago'] ?? 'pendiente')));
                            $cliente_nombre = trim(($reserva['nombre'] ?? '') . ' ' . ($reserva['apellido'] ?? '')) ?: 'Cliente';
                        ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($id_res); ?></td>
                                <td><?php echo htmlspecialchars($cliente_nombre); ?></td>
                                <td><?php echo htmlspecialchars($reserva['servicio_nombre'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($reserva['fecha_reserva'] ?? '-'); ?> <?php echo htmlspecialchars(substr((string) ($reserva['hora_reserva'] ?? ''), 0, 5)); ?></td>
                                <td>
                                    <form method="POST" style="display: flex; gap: 4px; align-items: center;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                        <input type="hidden" name="accion" value="actualizar_total">
                                        <input type="hidden" name="id_reserva" value="<?php echo $id_res; ?>">
                                        <input type="number" step="0.01" min="0" name="precio_total" value="<?php echo $precio_total !== null ? htmlspecialchars(number_format($precio_total, 2, '.', '')) : ''; ?>" style="width: 95px; background: rgba(15,23,42,0.9); color: white; border: 1px solid #475569; border-radius: 6px; padding: 6px 8px; font-size: 12px;" aria-label="<?php echo $texto['total']; ?>">
                                        <button type="submit" style="background:#334155; color:#cbd5e1; border:none; border-radius:6px; padding:7px 10px; font-size:12px; font-weight:600; cursor:pointer;" title="<?php echo $texto['ajustar_total']; ?>"><?php echo $texto['ajustar_total']; ?></button>
                                    </form>
                                </td>
                                <td><?php echo htmlspecialchars(number_format($total_pagado, 2, ',', '.')); ?></td>
                                <td><?php echo $saldo !== null ? htmlspecialchars(number_format($saldo, 2, ',', '.')) : '-'; ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($estado_cobro); ?>"><?php echo htmlspecialchars($texto['estados'][$estado_cobro] ?? $estado_cobro); ?></span></td>
                                <td>
                                    <form method="POST" class="form-cobro">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                        <input type="hidden" name="accion" value="registrar_pago">
                                        <input type="hidden" name="id_reserva" value="<?php echo $id_res; ?>">
                                        <input type="number" step="0.01" min="0.01" name="monto" placeholder="<?php echo $texto['monto']; ?>" required>
                                        <select name="metodo">
                                            <?php foreach ($texto['metodos'] as $clave_metodo => $nombre_metodo): ?>
                                                <option value="<?php echo htmlspecialchars($clave_metodo); ?>"><?php echo htmlspecialchars($nombre_metodo); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="referencia" maxlength="60" placeholder="<?php echo $texto['referencia']; ?>">
                                        <input type="text" name="notas" maxlength="255" placeholder="<?php echo $texto['notas']; ?>">
                                        <button type="submit"><?php echo $texto['registrar']; ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Historial de cobros registrados -->
        <h3 style="color: #38bdf8; font-size: 16px; margin-bottom: 12px;"><?php echo $texto['cobros']; ?></h3>
        <div class="tabla-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $texto['estado_pago']; ?></th>
                        <th><?php echo $texto['cliente']; ?></th>
                        <th><?php echo $texto['monto']; ?></th>
                        <th><?php echo $texto['metodo']; ?></th>
                        <th><?php echo $texto['referencia']; ?></th>
                        <th><?php echo $texto['fecha']; ?></th>
                        <th><?php echo $texto['registrado_por']; ?></th>
                        <th><?php echo $texto['notas']; ?></th>
                        <th><?php echo $texto['acciones']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cobros)): ?>
                        <tr>
                            <td colspan="10" class="estado-vacio"><?php echo $texto['sin_cobros']; ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cobros as $cobro):
                            $estado_cobro_pago = strtolower(trim((string) ($cobro['estado'] ?? 'pagado')));
                            $nombre_cobro = trim(($cobro['nombre'] ?? '') . ' ' . ($cobro['apellido'] ?? '')) ?: 'Cliente';
                        ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($cobro['id_pago'] ?? ''); ?></td>
                                <td>Reserva #<?php echo htmlspecialchars($cobro['id_reserva'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($nombre_cobro); ?></td>
                                <td><?php echo htmlspecialchars(number_format((float) ($cobro['monto'] ?? 0), 2, ',', '.')); ?></td>
                                <td><?php echo htmlspecialchars($texto['metodos'][$cobro['metodo'] ?? ''] ?? ($cobro['metodo'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars($cobro['referencia'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($cobro['fecha_pago'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($cobro['registrado_por'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($cobro['notas'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($estado_cobro_pago !== 'anulado'): ?>
                                        <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Void this payment?' : '¿Anular este cobro?'; ?>');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion" value="anular_pago">
                                            <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($cobro['id_pago'] ?? ''); ?>">
                                            <button type="submit" class="btn-anular"><?php echo $texto['anular']; ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge anulado"><?php echo $texto['anulado']; ?></span>
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
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
