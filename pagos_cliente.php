<?php
/**
 * TGS - Taller Global de Servicios
 * Pagos del cliente.
 *
 * Pantalla donde el cliente ve cuanto debe, con que cuenta transferir y
 * reporta los pagos que ya hizo. El cobro queda con estado 'reportado' y
 * no cuenta como pagado hasta que el taller lo valida desde
 * administrar_pagos.php.
 *
 * Reglas de negocio:
 * - Solo el cliente accede, y solo sobre sus propias reservas.
 * - El monto reportado no puede superar el saldo pendiente.
 * - Los pagos declarados por el cliente no modifican el saldo: el estado
 *   de cobro de la reserva se recalcula cuando el taller aprueba el cobro.
 *
 * Dependencias: auth.php, conexion.php, funciones_pago.php
 */
require_once 'auth.php';
verificarPermiso('ver_pagos_propios');
require_once 'conexion.php';
require_once 'formato.php';
require_once 'funciones_pago.php';
require_once 'funciones_pago.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$cedula_usuario = $_SESSION['cedula'] ?? '';

$texto = $lang_code === 'en' ? [
    'titulo' => 'My payments', 'salir' => 'Sign Out', 'perfil' => 'My Profile', 'panel' => 'My panel',
    'resumen' => 'Here you can see the balance of your appointments, transfer to our account and report the payment so we can confirm it.',
    'pendientes' => 'Pending payment', 'pagados' => 'Paid appointments', 'sin_pendientes' => 'You have no pending payments. Well done!',
    'servicio' => 'Service', 'vehiculo' => 'Vehicle', 'fecha' => 'Date', 'total' => 'Total', 'saldo' => 'Balance',
    'reportar' => 'Report payment', 'monto' => 'Amount', 'metodo' => 'Method', 'referencia' => 'Receipt or reference',
    'notas' => 'Notes', 'enviar' => 'Send report', 'mis_pagos' => 'My reported payments',
    'datos_bancarios' => 'Bank details', 'alias' => 'Alias', 'cbu' => 'CBU', 'copiar' => 'Copy',
    'sin_datos' => 'No bank details have been published yet.',
    'historial' => 'History', 'estado' => 'Status', 'sin_historial' => 'You have not reported any payment yet.',
    'metodos' => ['efectivo' => 'Cash', 'tarjeta' => 'Card', 'transferencia' => 'Transfer'],
    'estados' => ['reportado' => 'Reported', 'pagado' => 'Approved', 'rechazado' => 'Rejected', 'anulado' => 'Void'],
    'exito' => 'Your payment report was sent. The workshop will confirm it shortly.',
    'factura' => 'Invoice',
    'exito_prepago' => 'Payment made with your prepaid balance.',
    'saldo_prepago' => 'Prepaid balance',
    'pagar_prepago' => 'Pay with my prepaid balance',
    'sin_saldo' => 'You have no prepaid balance available.',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_monto' => 'Enter an amount greater than zero.',
    'error_excede' => 'The amount is higher than the pending balance.',
    'error_metodo' => 'Select a valid payment method.',
    'error_reserva' => 'That appointment is not yours.',
    'error_operacion' => 'The payment could not be reported.'
] : [
    'titulo' => 'Mis pagos', 'salir' => 'Cerrar Sesión', 'perfil' => 'Mi Perfil', 'panel' => 'Mi panel',
    'resumen' => 'Acá ves el saldo de tus turnos, transferís a nuestra cuenta y reportás el pago para que lo confirmemos.',
    'pendientes' => 'Pagos pendientes', 'pagados' => 'Turnos pagados', 'sin_pendientes' => 'No tenés pagos pendientes. ¡Bien!',
    'servicio' => 'Servicio', 'vehiculo' => 'Vehículo', 'fecha' => 'Fecha', 'total' => 'Total', 'saldo' => 'Saldo',
    'reportar' => 'Reportar pago', 'monto' => 'Monto', 'metodo' => 'Método', 'referencia' => 'Comprobante o referencia',
    'notas' => 'Notas', 'enviar' => 'Enviar reporte', 'mis_pagos' => 'Pagos que reporté',
    'datos_bancarios' => 'Datos para transferir', 'alias' => 'Alias', 'cbu' => 'CBU', 'copiar' => 'Copiar',
    'sin_datos' => 'Todavía no publicamos los datos bancarios.',
    'historial' => 'Historial', 'estado' => 'Estado', 'sin_historial' => 'Todavía no reportaste ningún pago.',
    'metodos' => ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia'],
    'estados' => ['reportado' => 'Reportado', 'pagado' => 'Aprobado', 'rechazado' => 'Rechazado', 'anulado' => 'Anulado'],
    'exito' => 'Tu reporte de pago se envió. El taller lo confirmará en breve.',
    'factura' => 'Factura',
    'exito_prepago' => 'Pago realizado con tu saldo prepago.',
    'saldo_prepago' => 'Saldo prepago',
    'pagar_prepago' => 'Pagar con mi saldo prepago',
    'sin_saldo' => 'No tenés saldo prepago disponible.',
    'error_csrf' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
    'error_monto' => 'Ingresá un monto mayor a cero.',
    'error_excede' => 'El monto es mayor que el saldo pendiente.',
    'error_metodo' => 'Seleccioná un método de pago válido.',
    'error_reserva' => 'Ese turno no es tuyo.',
    'error_operacion' => 'No se pudo registrar el reporte de pago.'
];

$mensaje = '';
$tipo_alerta = 'alert-success';

// ---------------------------------------------------------------------------
// Reporte de un pago por parte del cliente
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['id_reserva'])) {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $texto['error_csrf'];
        $tipo_alerta = 'alert-error';
    } elseif ($_POST['accion'] === 'pagar_prepago') {
        // Pago directo desde la aplicacion con el saldo de la cuenta
        // prepaga del cliente: el cobro queda pagado y se descuenta el saldo.
        $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
        $monto = trim((string) ($_POST['monto'] ?? ''));

        if (!is_numeric($monto) || (float) $monto <= 0) {
            $mensaje = $texto['error_monto'];
            $tipo_alerta = 'alert-error';
        } elseif (!$id_reserva) {
            $mensaje = $texto['error_reserva'];
            $tipo_alerta = 'alert-error';
        } else {
            try {
                $consulta = $conexion->prepare(
                    'SELECT r.id_reserva, r.cedula_usuario, r.precio_total,
                            COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = :estado_pagado), 0) AS total_pagado
                     FROM reserva r
                     WHERE r.id_reserva = :id_reserva AND r.cedula_usuario = :cedula_usuario
                     LIMIT 1'
                );
                $consulta->execute([
                    'id_reserva' => $id_reserva,
                    'cedula_usuario' => $cedula_usuario,
                    'estado_pagado' => 'pagado'
                ]);
                $reserva = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$reserva) {
                    $mensaje = $texto['error_reserva'];
                    $tipo_alerta = 'alert-error';
                } else {
                    $total = $reserva['precio_total'] !== null ? (float) $reserva['precio_total'] : 0.0;
                    $saldo = $total - (float) $reserva['total_pagado'];
                    $saldo_prepago = tgs_saldo_prepago($conexion, $cedula_usuario);

                    if ((float) $monto - $saldo > 0.009) {
                        $mensaje = $texto['error_excede'];
                        $tipo_alerta = 'alert-error';
                    } elseif ((float) $monto - $saldo_prepago > 0.009) {
                        $mensaje = $texto['sin_saldo'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        $conexion->beginTransaction();
                        $insertar = $conexion->prepare(
                            "INSERT INTO pago (id_reserva, monto, metodo, estado, referencia, fecha_pago, registrado_por, notas)
                             VALUES (:id_reserva, :monto, 'prepago', 'pagado', :referencia, NOW(), :registrado_por, :notas)"
                        );
                        $insertar->execute([
                            'id_reserva' => $id_reserva,
                            'monto' => number_format((float) $monto, 2, '.', ''),
                            'referencia' => 'PREPAGO-' . date('Ymd'),
                            'registrado_por' => $cedula_usuario,
                            'notas' => 'Pago con saldo de cuenta prepaga'
                        ]);

                        // Se descuenta del saldo prepago del cliente.
                        $descontar = $conexion->prepare('UPDATE usuario SET saldo_prepago = COALESCE(saldo_prepago, 0) - :monto WHERE cedula = :cedula_usuario');
                        $descontar->execute([
                            'monto' => number_format((float) $monto, 2, '.', ''),
                            'cedula_usuario' => $cedula_usuario
                        ]);

                        $conexion->commit();
                        tgs_recalcular_estado_pago($conexion, (int) $id_reserva);
                        tgs_generar_factura($conexion, (int) $id_reserva, 'prepago', 'Pagada con saldo de cuenta prepaga');
                        $mensaje = $texto['exito_prepago'];
                    }
                }
            } catch (PDOException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                $mensaje = $texto['error_operacion'];
                $tipo_alerta = 'alert-error';
            }
        }
    } elseif ($_POST['accion'] === 'reportar_pago') {
        $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
        $monto = trim((string) ($_POST['monto'] ?? ''));
        $metodo = strtolower(trim((string) ($_POST['metodo'] ?? '')));
        $referencia = trim((string) ($_POST['referencia'] ?? ''));
        $notas = trim((string) ($_POST['notas'] ?? ''));

        if (!is_numeric($monto) || (float) $monto <= 0) {
            $mensaje = $texto['error_monto'];
            $tipo_alerta = 'alert-error';
        } elseif (!array_key_exists($metodo, $texto['metodos'])) {
            $mensaje = $texto['error_metodo'];
            $tipo_alerta = 'alert-error';
        } elseif (!$id_reserva) {
            $mensaje = $texto['error_reserva'];
            $tipo_alerta = 'alert-error';
        } else {
            try {
                // La reserva tiene que ser del cliente: el filtro va por
                // la cedula de la sesion, nunca por un id recibido.
                // Ojo: en PDO no se pueden mezclar placeholders posicionales
                // (?) con nombrados (:nombre) en la misma consulta.
                $consulta = $conexion->prepare(
                    'SELECT r.id_reserva, r.precio_total,
                            COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = :estado_pagado), 0) AS total_pagado
                     FROM reserva r
                     WHERE r.id_reserva = :id_reserva AND r.cedula_usuario = :cedula_usuario
                     LIMIT 1'
                );
                $consulta->execute([
                    'id_reserva' => $id_reserva,
                    'cedula_usuario' => $cedula_usuario,
                    'estado_pagado' => 'pagado'
                ]);
                $reserva = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$reserva) {
                    $mensaje = $texto['error_reserva'];
                    $tipo_alerta = 'alert-error';
                } else {
                    $total = $reserva['precio_total'] !== null ? (float) $reserva['precio_total'] : 0.0;
                    $saldo = $total - (float) $reserva['total_pagado'];

                    if ((float) $monto - $saldo > 0.009) {
                        $mensaje = $texto['error_excede'];
                        $tipo_alerta = 'alert-error';
                    } else {
                        // Queda como 'reportado': todavia no cuenta como pagado.
                        $insertar = $conexion->prepare(
                            "INSERT INTO pago (id_reserva, monto, metodo, estado, referencia, fecha_pago, registrado_por, notas)
                             VALUES (:id_reserva, :monto, :metodo, 'reportado', :referencia, NOW(), NULL, :notas)"
                        );
                        $insertar->execute([
                            'id_reserva' => $id_reserva,
                            'monto' => number_format((float) $monto, 2, '.', ''),
                            'metodo' => $metodo,
                            'referencia' => $referencia !== '' ? $referencia : null,
                            'notas' => $notas !== '' ? $notas : null
                        ]);
                        $mensaje = $texto['exito'];
                    }
                }
            } catch (PDOException $e) {
                $mensaje = $texto['error_operacion'];
                $tipo_alerta = 'alert-error';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Turnos del cliente con su saldo pendiente
// ---------------------------------------------------------------------------
$turnos = [];
try {
    $stmt = $conexion->prepare(
        "SELECT r.id_reserva, r.fecha_reserva, r.hora_reserva, r.estado, r.precio_total,
                v.marca, v.modelo, v.patente, s.nombre AS servicio_nombre,
                COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado'), 0) AS total_pagado,
                COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'reportado'), 0) AS total_reportado
         FROM reserva r
         LEFT JOIN vehiculo v ON v.id_vehiculo = r.id_vehiculo
         LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
         WHERE r.cedula_usuario = :cedula_usuario AND r.estado <> 'Cancelada'
         ORDER BY r.fecha_reserva DESC"
    );
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $turnos = [];
}

// Pagos reportados por el cliente.
$mis_pagos = [];
try {
    $stmt = $conexion->prepare(
        "SELECT p.*, r.fecha_reserva FROM pago p
         JOIN reserva r ON r.id_reserva = p.id_reserva
         WHERE r.cedula_usuario = :cedula_usuario
         ORDER BY p.id_pago DESC LIMIT 20"
    );
    $stmt->execute(['cedula_usuario' => $cedula_usuario]);
    $mis_pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mis_pagos = [];
}

// Cuentas bancarias publicadas.
$cuentas = [];
try {
    $stmt = $conexion->query('SELECT * FROM datos_pago WHERE activo = 1 ORDER BY id_datos_pago');
    $cuentas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $cuentas = [];
}

// Saldo de la cuenta prepaga del cliente.
$saldo_prepago = tgs_saldo_prepago($conexion, $cedula_usuario);
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
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        /* Cabecera del area de cliente: navegacion, idioma y cierre de sesion. */
        .acciones-cabecera { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .nav-enlace { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-enlace:hover { color: #38bdf8; }
        .nav-enlace.activo { color: #38bdf8; font-weight: 700; }
        .btn-salir { background-color: #ef4444; color: #ffffff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .btn-salir:hover { background-color: #dc2626; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        header .acciones { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        header .acciones a { color: #cbd5e1; text-decoration: none; font-size: 14px; }
        header .acciones a:hover { color: #38bdf8; }
        .container { flex: 1; padding: 30px 20px 50px; max-width: 1050px; width: 100%; margin: 0 auto; }
        .bienvenida { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 22px 26px; margin-bottom: 20px; }
        .bienvenida h2 { font-size: 21px; color: #38bdf8; margin-bottom: 6px; }
        .bienvenida p { color: #cbd5e1; font-size: 14px; }
        .bloque { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 20px 22px; margin-bottom: 20px; }
        .bloque h3 { font-size: 15px; color: #38bdf8; margin-bottom: 14px; }
        .tarjeta-turno { border: 1px solid #334155; border-radius: 10px; padding: 16px; margin-bottom: 14px; background: rgba(3, 9, 18, 0.4); }
        .tarjeta-turno .fila { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; color: #cbd5e1; padding: 3px 0; }
        .tarjeta-turno .fila span:first-child { color: #94a3b8; }
        .tarjeta-turno .saldo { font-size: 18px; color: #fbbf24; font-weight: 700; }
        .tarjeta-turno .saldo.pagado { color: #4ade80; }
        .form-cobro { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-top: 12px; padding-top: 12px; border-top: 1px solid #334155; }
        .campo { display: flex; flex-direction: column; gap: 4px; }
        .campo label { font-size: 12px; color: #94a3b8; }
        .campo input, .campo select { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 9px 11px; font-size: 13px; outline: none; }
        .campo input:focus, .campo select:focus { border-color: #38bdf8; }
        .campo input[type="number"] { width: 120px; }
        .campo input[type="text"] { width: 170px; }
        .boton { background: #2563eb; color: white; border: none; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .boton:hover { background: #1d4ed8; }
        .dato-bancario { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.6); }
        .dato-bancario:last-child { border-bottom: none; }
        .dato-bancario .texto { flex: 1; }
        .dato-bancario .texto strong { display: block; font-size: 14px; color: #f8fafc; }
        .dato-bancario .texto span { font-size: 13px; color: #94a3b8; }
        .dato-bancario code { background: rgba(15, 23, 42, 0.9); border: 1px solid #334155; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #7dd3fc; }
        .dato-bancario img { width: 130px; height: 130px; border-radius: 8px; background: white; padding: 6px; }
        .btn-factura { display: inline-block; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); border-radius: 6px; padding: 4px 9px; font-size: 12px; font-weight: 600; text-decoration: none; }
        .boton.prepago { background: #22c55e; }
        .btn-copiar { background: #334155; color: #cbd5e1; border: none; border-radius: 6px; padding: 5px 10px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn-copiar:hover { background: #475569; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; padding: 9px; text-align: left; font-weight: 600; }
        td { padding: 9px; border-bottom: 1px solid #334155; color: #cbd5e1; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.reportado { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .badge.pagado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.rechazado, .badge.anulado { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #22c55e; background: rgba(34, 197, 94, 0.1); color: #bbf7d0; font-size: 14px; }
        .alert-error { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 20px; font-size: 13px; }
        .volver { display: inline-block; margin-top: 6px; color: #38bdf8; text-decoration: none; font-size: 14px; font-weight: 600; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TGS - Blue Tech Uruguay</a>
        <div class="acciones">
            <a href="panel_cliente.php"><?php echo $texto['panel']; ?></a>
            <a href="perfil.php"><?php echo $texto['perfil']; ?></a>
            <a href="presupuestos.php"><?php echo $lang_code === 'en' ? 'My budgets' : 'Mis presupuestos'; ?></a>
            <!-- Selector de idioma de la pantalla de pagos -->
            <a href="pagos_cliente.php?lang=es" style="<?php echo $lang_code === 'es' ? 'color:#38bdf8; font-weight:700;' : ''; ?>">ES</a>
            <a href="pagos_cliente.php?lang=en" style="<?php echo $lang_code === 'en' ? 'color:#38bdf8; font-weight:700;' : ''; ?>">EN</a>
            <a href="logout.php" class="btn-salir"><?php echo $texto['salir']; ?></a>
        </div>
    </header>

    <div class="container">
        <div class="bienvenida">
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Turnos con saldo pendiente -->
        <div class="bloque">
            <h3><?php echo $texto['pendientes']; ?></h3>
            <?php
            $pendientes = [];
            foreach ($turnos as $turno) {
                $total = $turno['precio_total'] !== null ? (float) $turno['precio_total'] : 0.0;
                $saldo = $total - (float) ($turno['total_pagado'] ?? 0);
                if ($saldo > 0.009) {
                    $turno['_saldo'] = $saldo;
                    $pendientes[] = $turno;
                }
            }
            ?>
            <?php if (empty($pendientes)): ?>
                <p class="estado-vacio"><?php echo $texto['sin_pendientes']; ?></p>
            <?php else: ?>
                <?php foreach ($pendientes as $turno):
                    $vehiculo = trim(($turno['marca'] ?? '') . ' ' . ($turno['modelo'] ?? '')) ?: '-';
                    $saldo = (float) $turno['_saldo'];
                    $reportado = (float) ($turno['total_reportado'] ?? 0);
                ?>
                    <div class="tarjeta-turno">
                        <div class="fila"><span><?php echo $texto['servicio']; ?></span><span><?php echo htmlspecialchars($turno['servicio_nombre'] ?? '-'); ?></span></div>
                        <div class="fila"><span><?php echo $texto['vehiculo']; ?></span><span><?php echo htmlspecialchars($vehiculo); ?> (<?php echo htmlspecialchars($turno['patente'] ?? '-'); ?>)</span></div>
                        <div class="fila"><span><?php echo $texto['fecha']; ?></span><span><?php echo htmlspecialchars(tgs_fecha_legible($turno['fecha_reserva'] ?? '-', $lang_code)); ?> <?php echo htmlspecialchars(substr((string) ($turno['hora_reserva'] ?? ''), 0, 5)); ?></span></div>
                        <div class="fila"><span><?php echo $texto['total']; ?></span><span><?php echo htmlspecialchars(number_format($turno['precio_total'] !== null ? (float) $turno['precio_total'] : 0, 2, ',', '.')); ?> USD</span></div>
                        <div class="fila"><span><?php echo $texto['saldo']; ?></span><span class="saldo"><?php echo htmlspecialchars(number_format($saldo, 2, ',', '.')); ?> USD</span></div>
                        <?php if ($reportado > 0.009): ?>
                            <div class="fila"><span><?php echo $lang_code === 'en' ? 'Reported and waiting for confirmation' : 'Reportado y pendiente de confirmación'; ?></span><span><?php echo htmlspecialchars(number_format($reportado, 2, ',', '.')); ?> USD</span></div>
                        <?php endif; ?>

                        <!-- Reporte de pago: queda 'reportado' hasta que el taller lo valide -->
                        <form method="POST" class="form-cobro">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="accion" value="reportar_pago">
                            <input type="hidden" name="id_reserva" value="<?php echo (int) $turno['id_reserva']; ?>">
                            <div class="campo">
                                <label for="monto_<?php echo (int) $turno['id_reserva']; ?>"><?php echo $texto['monto']; ?></label>
                                <input type="number" step="0.01" min="0.01" max="<?php echo htmlspecialchars(number_format($saldo, 2, '.', '')); ?>" id="monto_<?php echo (int) $turno['id_reserva']; ?>" name="monto" value="<?php echo htmlspecialchars(number_format($saldo, 2, '.', '')); ?>" required>
                            </div>
                            <div class="campo">
                                <label for="metodo_<?php echo (int) $turno['id_reserva']; ?>"><?php echo $texto['metodo']; ?></label>
                                <select id="metodo_<?php echo (int) $turno['id_reserva']; ?>" name="metodo">
                                    <?php foreach ($texto['metodos'] as $clave_metodo => $nombre_metodo): ?>
                                        <option value="<?php echo htmlspecialchars($clave_metodo); ?>"><?php echo htmlspecialchars($nombre_metodo); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="referencia_<?php echo (int) $turno['id_reserva']; ?>"><?php echo $texto['referencia']; ?></label>
                                <input type="text" id="referencia_<?php echo (int) $turno['id_reserva']; ?>" name="referencia" maxlength="60">
                            </div>
                            <div class="campo">
                                <label for="notas_<?php echo (int) $turno['id_reserva']; ?>"><?php echo $texto['notas']; ?></label>
                                <input type="text" id="notas_<?php echo (int) $turno['id_reserva']; ?>" name="notas" maxlength="255">
                            </div>
                            <button type="submit" class="boton"><?php echo $texto['enviar']; ?></button>
                        </form>

                        <!-- Pago directo con el saldo de la cuenta prepaga -->
                        <?php if ($saldo_prepago > 0.009): ?>
                            <form method="POST" class="form-cobro">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                <input type="hidden" name="accion" value="pagar_prepago">
                                <input type="hidden" name="id_reserva" value="<?php echo (int) $turno['id_reserva']; ?>">
                                <div class="campo">
                                    <label for="monto_prepago_<?php echo (int) $turno['id_reserva']; ?>"><?php echo $texto['saldo_prepago']; ?> (<?php echo htmlspecialchars(number_format($saldo_prepago, 2, ',', '.')); ?> USD)</label>
                                    <input type="number" step="0.01" min="0.01" max="<?php echo htmlspecialchars(number_format(min($saldo, $saldo_prepago), 2, '.', '')); ?>" id="monto_prepago_<?php echo (int) $turno['id_reserva']; ?>" name="monto" value="<?php echo htmlspecialchars(number_format(min($saldo, $saldo_prepago), 2, '.', '')); ?>" required>
                                </div>
                                <button type="submit" class="boton prepago"><?php echo $texto['pagar_prepago']; ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Datos bancarios para transferir -->
        <div class="bloque">
            <h3><?php echo $texto['datos_bancarios']; ?></h3>
            <?php if (empty($cuentas)): ?>
                <p class="estado-vacio"><?php echo $texto['sin_datos']; ?></p>
            <?php else: ?>
                <?php foreach ($cuentas as $cuenta): ?>
                    <div class="dato-bancario">
                        <div class="texto">
                            <strong><?php echo htmlspecialchars($cuenta['nombre'] ?? ''); ?></strong>
                            <?php if (!empty($cuenta['alias'])): ?>
                                <span><?php echo $texto['alias']; ?>:</span> <code><?php echo htmlspecialchars($cuenta['alias']); ?></code>
                                <button type="button" class="btn-copiar" onclick="copiarTexto('<?php echo htmlspecialchars(addslashes($cuenta['alias'] ?? '')); ?>', this)"><?php echo $texto['copiar']; ?></button>
                            <?php endif; ?>
                            <?php if (!empty($cuenta['cbu'])): ?>
                                <br><span><?php echo $texto['cbu']; ?>:</span> <code><?php echo htmlspecialchars($cuenta['cbu']); ?></code>
                                <button type="button" class="btn-copiar" onclick="copiarTexto('<?php echo htmlspecialchars(addslashes($cuenta['cbu'] ?? '')); ?>', this)"><?php echo $texto['copiar']; ?></button>
                            <?php endif; ?>
                            <?php if (!empty($cuenta['notas'])): ?>
                                <br><small style="color:#64748b;"><?php echo htmlspecialchars($cuenta['notas']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($cuenta['qr'])): ?>
                            <img src="<?php echo htmlspecialchars($cuenta['qr']); ?>" alt="<?php echo $lang_code === 'en' ? 'QR code to transfer' : 'Código QR para transferir'; ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Historial de pagos reportados por el cliente -->
        <div class="bloque">
            <h3><?php echo $texto['mis_pagos']; ?></h3>
            <?php if (empty($mis_pagos)): ?>
                <p class="estado-vacio"><?php echo $texto['sin_historial']; ?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo $lang_code === 'en' ? 'Payment' : 'Cobro'; ?></th>
                            <th><?php echo $texto['fecha']; ?></th>
                            <th><?php echo $texto['metodo']; ?></th>
                            <th><?php echo $texto['referencia']; ?></th>
                            <th><?php echo $texto['estado']; ?></th>
                            <th><?php echo $texto['factura']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mis_pagos as $pago):
                            $estado_pago = strtolower(trim((string) ($pago['estado'] ?? 'reportado')));
                            $factura_pago = tgs_factura_de_reserva($conexion, (int) ($pago['id_reserva'] ?? 0));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars(number_format((float) ($pago['monto'] ?? 0), 2, ',', '.')); ?> USD</td>
                                <td><?php echo htmlspecialchars(tgs_fecha_legible($pago['fecha_pago'] ?? '-', $lang_code)); ?></td>
                                <td><?php echo htmlspecialchars($texto['metodos'][$pago['metodo'] ?? ''] ?? ($pago['metodo'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars($pago['referencia'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($estado_pago); ?>"><?php echo htmlspecialchars($texto['estados'][$estado_pago] ?? $estado_pago); ?></span></td>
                                <td>
                                    <?php if ($factura_pago): ?>
                                        <a href="factura.php?id=<?php echo (int) $factura_pago['id_factura']; ?>" target="_blank" class="btn-factura"><?php echo htmlspecialchars($factura_pago['numero']); ?></a>
                                    <?php else: ?>
                                        <span style="color: #64748b; font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <a href="panel_cliente.php" class="volver">&larr; <?php echo $texto['panel']; ?></a>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $lang_code === 'en' ? 'Global Workshop Services. All rights reserved.' : 'Taller Global de Servicios. Todos los derechos reservados.'; ?></p>
    </footer>
    <script>
        // Copia al portapapeles el alias o el CBU.
        function copiarTexto(texto, boton) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto);
            } else {
                var campo = document.createElement('textarea');
                campo.value = texto;
                document.body.appendChild(campo);
                campo.select();
                document.execCommand('copy');
                document.body.removeChild(campo);
            }
            var textoOriginal = boton.textContent;
            boton.textContent = '<?php echo $lang_code === 'en' ? 'Copied' : 'Copiado'; ?>';
            setTimeout(function () { boton.textContent = textoOriginal; }, 1500);
        }
    </script>
</body>
</html>
