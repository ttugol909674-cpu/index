<?php
/**
 * TGS - Taller Global de Servicios
 * Agenda del gestor.
 *
 * Vista de un dia de trabajo: una fila por sede y una columna por franja
 * horaria, con los turnos de cada sede en su horario. Sirve para ver de
 * un vistazo como esta ocupado el taller y detectar huecos.
 *
 * Debajo de la grilla se listan los turnos del dia con su estado y su
 * situacion de cobro, para poder trabajar sobre ellos.
 *
 * Reglas de negocio:
 * - Solo gestores y administradores (permiso 'administrar_turnos').
 * - Las reservas canceladas no ocupan lugar en la agenda.
 * - La grilla usa el horario y la capacidad configurados en cada sede
 *   (taller_horario y taller.capacidad).
 *
 * Dependencias: auth.php, conexion.php, taller_disponibilidad.php
 */
require_once 'auth.php';
verificarPermiso('administrar_turnos');
require_once 'conexion.php';
require_once 'taller_disponibilidad.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$panel_volver = ($rol_actual === 'admin') ? 'dashboard_admin.php' : 'dashboard_gestor.php';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

$texto = $lang_code === 'en' ? [
    'titulo' => 'Workshop Agenda', 'inicio' => 'Back to Home', 'panel' => 'Panel', 'hola' => 'Hello', 'salir' => 'Sign Out',
    'listado' => 'Daily agenda',
    'resumen' => 'One row per workshop and one column per time slot, with the appointments of each day.',
    'anterior' => 'Previous day', 'siguiente' => 'Next day', 'hoy' => 'Today', 'fecha' => 'Date',
    'sede' => 'Workshop', 'libre' => 'Free', 'sin_turnos' => 'No appointments on this day.',
    'turnos_del_dia' => "Day's appointments", 'cliente' => 'Customer', 'vehiculo' => 'Vehicle', 'servicio' => 'Service',
    'estado' => 'Status', 'pago' => 'Payment', 'hora' => 'Time', 'total' => 'Total appointments',
    'por_cobrar' => 'Pending collection', 'ocupacion' => 'Occupancy', 'sin_sedes' => 'No workshops registered.',
    'estados' => ['Pendiente' => 'Pending', 'En curso' => 'In progress', 'Finalizada' => 'Completed', 'Cancelada' => 'Cancelled'],
    'pagos' => ['pagado' => 'Paid', 'parcial' => 'Partial', 'pendiente' => 'Pending'],
    'cerrado' => 'Closed', 'volver' => 'Back'
] : [
    'titulo' => 'Agenda de Talleres', 'inicio' => 'Volver al Inicio', 'panel' => 'Panel', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'listado' => 'Agenda del día',
    'resumen' => 'Una fila por sede y una columna por franja horaria, con los turnos de cada día.',
    'anterior' => 'Día anterior', 'siguiente' => 'Día siguiente', 'hoy' => 'Hoy', 'fecha' => 'Fecha',
    'sede' => 'Sede', 'libre' => 'Libre', 'sin_turnos' => 'No hay turnos para este día.',
    'turnos_del_dia' => 'Turnos del día', 'cliente' => 'Cliente', 'vehiculo' => 'Vehículo', 'servicio' => 'Servicio',
    'estado' => 'Estado', 'pago' => 'Pago', 'hora' => 'Hora', 'total' => 'Turnos totales',
    'por_cobrar' => 'Pendiente de cobro', 'ocupacion' => 'Ocupación', 'sin_sedes' => 'No hay sedes registradas.',
    'estados' => ['Pendiente' => 'Pendiente', 'En curso' => 'En curso', 'Finalizada' => 'Finalizada', 'Cancelada' => 'Cancelada'],
    'pagos' => ['pagado' => 'Pagado', 'parcial' => 'Parcial', 'pendiente' => 'Pendiente'],
    'cerrado' => 'Cerrado', 'volver' => 'Volver'
];

// Fecha de la agenda (por defecto, hoy).
$fecha = trim((string) ($_GET['fecha'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}
$dia_semana = tgs_dia_semana($fecha);

// Navegacion entre dias.
$fecha_anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fecha_siguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$es_hoy = ($fecha === date('Y-m-d'));

// Sedes con su horario del dia.
$sedes = [];
try {
    $stmt = $conexion->query('SELECT id_taller, nombre, direccion, capacidad FROM taller ORDER BY nombre');
    $sedes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $sedes = [];
}

// Turnos del dia (los cancelados no ocupan lugar).
$turnos = [];
try {
    $stmt = $conexion->prepare(
        "SELECT r.id_reserva, r.id_taller, r.hora_reserva, r.estado, r.precio_total, r.estado_pago,
                u.nombre, u.apellido, u.telefono,
                v.marca, v.modelo, v.patente,
                s.nombre AS servicio_nombre,
                COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado'), 0) AS total_pagado
         FROM reserva r
         LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
         LEFT JOIN vehiculo v ON v.id_vehiculo = r.id_vehiculo
         LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
         WHERE r.fecha_reserva = :fecha_reserva AND r.estado <> 'Cancelada'
         ORDER BY r.hora_reserva"
    );
    $stmt->execute(['fecha_reserva' => $fecha]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $turnos = [];
}

/**
 * Agrupa los turnos por sede y franja horaria. La franja que contiene la
 * hora del turno se calcula igual que en la disponibilidad: si la hora
 * cae entre dos franjas, ocupa la primera.
 */
function agrupar_turnos_por_franja(array $turnos, int $id_taller, array $franjas, string $fecha): array
{
    $duracion = tgs_duracion_franja() * 60;
    $agrupados = [];

    foreach ($franjas as $franja) {
        $inicio = strtotime($fecha . ' ' . $franja['inicio']);
        $fin = $inicio + $duracion;
        $agrupados[$franja['inicio']] = [];
    }

    foreach ($turnos as $turno) {
        if ((int) ($turno['id_taller'] ?? 0) !== $id_taller) {
            continue;
        }
        $hora_turno = strtotime($fecha . ' ' . substr((string) ($turno['hora_reserva'] ?? '00:00:00'), 0, 5));
        if ($hora_turno === false) {
            continue;
        }
        foreach ($franjas as $franja) {
            $inicio = strtotime($fecha . ' ' . $franja['inicio']);
            $fin = $inicio + $duracion;
            if ($hora_turno >= $inicio && $hora_turno < $fin) {
                $agrupados[$franja['inicio']][] = $turno;
                break;
            }
        }
    }

    return $agrupados;
}

// Franjas de cada sede para el dia elegido.
$franjas_por_sede = [];
foreach ($sedes as $sede) {
    $id_sede = (int) $sede['id_taller'];
    $horario = tgs_horario_del_dia($conexion, $id_sede, $fecha);

    if ($horario === null) {
        $franjas_por_sede[$id_sede] = [];
        continue;
    }

    $franjas = [];
    $duracion = tgs_duracion_franja() * 60;
    $inicio = strtotime($fecha . ' ' . $horario['hora_inicio']);
    $fin = strtotime($fecha . ' ' . $horario['hora_fin']);
    for ($cursor = $inicio; $cursor + $duracion <= $fin; $cursor += $duracion) {
        $franjas[] = [
            'inicio' => date('H:i', $cursor),
            'fin' => date('H:i', $cursor + $duracion)
        ];
    }
    $franjas_por_sede[$id_sede] = $franjas;
}

// Resumen del dia.
$resumen = ['total' => count($turnos), 'por_cobrar' => 0.0, 'ocupacion' => 0, 'franjas_totales' => 0];
foreach ($franjas_por_sede as $franjas) {
    $resumen['franjas_totales'] += count($franjas);
}
foreach ($turnos as $turno) {
    $precio = $turno['precio_total'] !== null ? (float) $turno['precio_total'] : 0.0;
    $pagado = (float) ($turno['total_pagado'] ?? 0);
    $resumen['por_cobrar'] += max(0, $precio - $pagado);
}
$resumen['ocupacion'] = $resumen['franjas_totales'] > 0
    ? (int) round(count($turnos) / $resumen['franjas_totales'] * 100)
    : 0;
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
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; }
        nav a.btn-logout:hover { background-color: #dc2626; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .container { flex: 1; padding: 30px 20px 50px; max-width: 1400px; width: 100%; margin: 0 auto; }
        .welcome-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 22px 26px; margin-bottom: 20px; }
        .welcome-card h2 { font-size: 21px; color: #38bdf8; margin-bottom: 6px; }
        .welcome-card p { color: #cbd5e1; font-size: 14px; }
        .navegacion-fecha { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 14px; }
        .navegacion-fecha a, .navegacion-fecha button { background: #334155; color: #cbd5e1; border: none; border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .navegacion-fecha a:hover, .navegacion-fecha button:hover { background: #475569; }
        .navegacion-fecha input[type="date"] { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 8px 12px; font-size: 13px; }
        .navegacion-fecha .hoy { background: #2563eb; color: white; }
        .navegacion-fecha .hoy:hover { background: #1d4ed8; }
        .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .tarjeta { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 14px 16px; }
        .tarjeta span { display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .tarjeta strong { font-size: 21px; color: #38bdf8; }
        .tarjeta strong.alerta { color: #fbbf24; }
        .agenda-scroll { overflow-x: auto; margin-bottom: 26px; }
        table.agenda { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; overflow: hidden; }
        table.agenda th, table.agenda td { border: 1px solid #334155; padding: 8px; font-size: 12px; vertical-align: top; }
        table.agenda th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; text-align: center; white-space: nowrap; }
        table.agenda th.sede-col { text-align: left; }
        table.agenda td.sede { background-color: rgba(15, 23, 42, 0.95); color: #f8fafc; font-weight: 600; white-space: nowrap; }
        table.agenda td.franja { min-width: 110px; }
        .franja-libre { color: #475569; font-size: 11px; }
        .turno { background: rgba(56, 189, 248, 0.12); border-left: 3px solid #38bdf8; border-radius: 4px; padding: 5px 7px; margin-bottom: 5px; }
        .turno.en-curso { background: rgba(250, 204, 21, 0.12); border-left-color: #facc15; }
        .turno.finalizada { background: rgba(34, 197, 94, 0.12); border-left-color: #4ade80; }
        .turno strong { display: block; color: #f8fafc; font-size: 12px; }
        .turno small { color: #94a3b8; font-size: 11px; }
        .turno .pago { font-size: 10px; color: #7dd3fc; }
        .cerrado { color: #64748b; font-style: italic; }
        table.detalle { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; overflow: hidden; }
        table.detalle th, table.detalle td { padding: 9px 12px; text-align: left; border-bottom: 1px solid #334155; font-size: 13px; }
        table.detalle th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.35); }
        .badge.pagado { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34, 197, 94, 0.35); }
        .badge.parcial { background: rgba(249, 115, 22, 0.14); color: #fdba74; border-color: rgba(249, 115, 22, 0.35); }
        .badge.pendiente { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250, 204, 21, 0.35); }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 24px; font-size: 14px; }
        h3.bloque-titulo { color: #38bdf8; font-size: 15px; margin: 0 0 12px; }
        /* Modal de edicion rapida: muestra el detalle del turno sin salir de la agenda */
        .turno.turno-clicable { cursor: pointer; }
        .turno.turno-clicable:hover { background: rgba(56, 189, 248, 0.22); }
        .modal-fondo { position: fixed; inset: 0; background: rgba(2, 7, 14, 0.78); display: none; z-index: 900; padding: 24px; }
        .modal-fondo.abierta { display: flex; align-items: flex-start; justify-content: center; }
        .modal-caja { background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; width: 100%; max-width: 820px; height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 18px 40px rgba(0, 0, 0, 0.5); }
        .modal-cabecera { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 18px; border-bottom: 1px solid #334155; }
        .modal-cabecera strong { color: #38bdf8; font-size: 14px; }
        .modal-cabecera button { background: #334155; color: #cbd5e1; border: none; border-radius: 6px; padding: 7px 13px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .modal-cabecera button:hover { background: #475569; }
        .modal-cuerpo { flex: 1; overflow-y: auto; }
        .modal-cuerpo iframe { width: 100%; height: 100%; min-height: 520px; border: 0; display: block; background: #0b1329; }
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
            <!-- Selector de idioma de la agenda -->
            <div class="language-selector"><a href="agenda_gestor.php?lang=es&fecha=<?php echo $fecha; ?>" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a><a href="agenda_gestor.php?lang=en&fecha=<?php echo $fecha; ?>" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a></div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $texto['listado']; ?> · <?php echo htmlspecialchars(date('d/m/Y', strtotime($fecha))); ?></h2>
            <p><?php echo $texto['resumen']; ?></p>

            <!-- Navegacion entre dias -->
            <div class="navegacion-fecha">
                <a href="agenda_gestor.php?fecha=<?php echo $fecha_anterior; ?>&lang=<?php echo $lang_code; ?>">&larr; <?php echo $texto['anterior']; ?></a>
                <?php if (!$es_hoy): ?>
                    <a class="hoy" href="agenda_gestor.php?fecha=<?php echo date('Y-m-d'); ?>&lang=<?php echo $lang_code; ?>"><?php echo $texto['hoy']; ?></a>
                <?php endif; ?>
                <a href="agenda_gestor.php?fecha=<?php echo $fecha_siguiente; ?>&lang=<?php echo $lang_code; ?>"><?php echo $texto['siguiente']; ?> &rarr;</a>
                <form method="GET" action="agenda_gestor.php" style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="lang" value="<?php echo $lang_code; ?>">
                    <input type="date" name="fecha" value="<?php echo $fecha; ?>">
                    <button type="submit"><?php echo $texto['fecha']; ?></button>
                </form>
            </div>
        </div>

        <!-- Resumen del dia -->
        <div class="resumen-grid">
            <div class="tarjeta">
                <span><?php echo $texto['total']; ?></span>
                <strong><?php echo $resumen['total']; ?></strong>
            </div>
            <div class="tarjeta">
                <span><?php echo $texto['ocupacion']; ?></span>
                <strong><?php echo $resumen['ocupacion']; ?>%</strong>
            </div>
            <div class="tarjeta">
                <span><?php echo $texto['por_cobrar']; ?></span>
                <strong class="<?php echo $resumen['por_cobrar'] > 0.009 ? 'alerta' : ''; ?>"><?php echo htmlspecialchars(number_format($resumen['por_cobrar'], 2, ',', '.')); ?> USD</strong>
            </div>
        </div>

        <?php if (empty($sedes)): ?>
            <div class="welcome-card"><?php echo $texto['sin_sedes']; ?></div>
        <?php else: ?>
            <!-- Grilla: una fila por sede, una columna por franja -->
            <div class="agenda-scroll">
                <table class="agenda">
                    <thead>
                        <tr>
                            <th class="sede-col"><?php echo $texto['sede']; ?></th>
                            <?php
                            // Encabezados de franja: se usan las del primer taller con horario.
                            $franjas_encabezado = [];
                            foreach ($franjas_por_sede as $franjas_sede) {
                                if (!empty($franjas_sede)) {
                                    $franjas_encabezado = $franjas_sede;
                                    break;
                                }
                            }
                            foreach ($franjas_encabezado as $franja): ?>
                                <th><?php echo htmlspecialchars($franja['inicio']); ?><br><small style="color:#64748b; font-weight:400;"><?php echo htmlspecialchars($franja['fin']); ?></small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sedes as $sede):
                            $id_sede = (int) $sede['id_taller'];
                            $franjas = $franjas_por_sede[$id_sede] ?? [];
                            $turnos_sede = agrupar_turnos_por_franja($turnos, $id_sede, $franjas, $fecha);
                        ?>
                            <tr>
                                <td class="sede">
                                    <?php echo htmlspecialchars($sede['nombre'] ?? ''); ?><br>
                                    <small style=" color:#94a3b8; font-weight:400;"><?php echo htmlspecialchars($sede['direccion'] ?? ''); ?></small>
                                </td>
                                <?php if (empty($franjas)): ?>
                                    <td colspan="<?php echo max(1, count($franjas_encabezado)); ?>" class="cerrado"><?php echo $texto['cerrado']; ?></td>
                                <?php else: ?>
                                    <?php foreach ($franjas as $franja):
                                        $turnos_franja = $turnos_sede[$franja['inicio']] ?? [];
                                    ?>
                                        <td class="franja">
                                            <?php if (empty($turnos_franja)): ?>
                                                <span class="franja-libre"><?php echo $texto['libre']; ?></span>
                                            <?php else: ?>
                                                <?php foreach ($turnos_franja as $turno):
                                                    $estado_turno = strtolower(trim((string) ($turno['estado'] ?? 'Pendiente')));
                                                    // La clase CSS no puede tener espacios: "En curso" -> "en-curso".
                                                    $clase_estado = str_replace(' ', '-', $estado_turno);
                                                    $estado_pago = strtolower(trim((string) ($turno['estado_pago'] ?? 'pendiente')));
                                                    $clase_pago = str_replace(' ', '-', $estado_pago);
                                                    $cliente_turno = trim(($turno['nombre'] ?? '') . ' ' . ($turno['apellido'] ?? '')) ?: 'Cliente';
                                                    $vehiculo_turno = trim(($turno['marca'] ?? '') . ' ' . ($turno['modelo'] ?? ''));
                                                ?>
                                                    <div class="turno <?php echo htmlspecialchars($clase_estado); ?> turno-clicable" data-detalle="detalle_reserva.php?id=<?php echo (int) ($turno['id_reserva'] ?? 0); ?>&embed=1">
                                                        <strong><?php echo htmlspecialchars($cliente_turno); ?></strong>
                                                        <small><?php echo htmlspecialchars($vehiculo_turno); ?></small>
                                                        <small><?php echo htmlspecialchars($turno['servicio_nombre'] ?? '-'); ?></small>
                                                        <span class="pago"><?php echo htmlspecialchars($texto['pagos'][$estado_pago] ?? $estado_pago); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Detalle de los turnos del dia -->
        <h3 class="bloque-titulo"><?php echo $texto['turnos_del_dia']; ?></h3>
        <?php if (empty($turnos)): ?>
            <div class="welcome-card estado-vacio"><?php echo $texto['sin_turnos']; ?></div>
        <?php else: ?>
            <div class="agenda-scroll">
                <table class="detalle">
                    <thead>
                        <tr>
                            <th><?php echo $texto['hora']; ?></th>
                            <th><?php echo $texto['sede']; ?></th>
                            <th><?php echo $texto['cliente']; ?></th>
                            <th><?php echo $texto['vehiculo']; ?></th>
                            <th><?php echo $texto['servicio']; ?></th>
                            <th><?php echo $texto['estado']; ?></th>
                            <th><?php echo $texto['pago']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turnos as $turno):
                            $estado_turno = trim((string) ($turno['estado'] ?? 'Pendiente'));
                            $estado_pago = strtolower(trim((string) ($turno['estado_pago'] ?? 'pendiente')));
                            $cliente_turno = trim(($turno['nombre'] ?? '') . ' ' . ($turno['apellido'] ?? '')) ?: 'Cliente';
                            $vehiculo_turno = trim(($turno['marca'] ?? '') . ' ' . ($turno['modelo'] ?? ''));
                            $nombre_sede = '';
                            foreach ($sedes as $sede) {
                                if ((int) $sede['id_taller'] === (int) ($turno['id_taller'] ?? 0)) {
                                    $nombre_sede = (string) $sede['nombre'];
                                    break;
                                }
                            }
                        ?>
                            <tr class="turno-clicable" data-detalle="detalle_reserva.php?id=<?php echo (int) ($turno['id_reserva'] ?? 0); ?>&embed=1">
                                <td style="color: #38bdf8; font-weight: 600;"><?php echo htmlspecialchars(substr((string) ($turno['hora_reserva'] ?? ''), 0, 5)); ?></td>
                                <td><?php echo htmlspecialchars($nombre_sede); ?></td>
                                <td><?php echo htmlspecialchars($cliente_turno); ?></td>
                                <td><?php echo htmlspecialchars($vehiculo_turno); ?></td>
                                <td><?php echo htmlspecialchars($turno['servicio_nombre'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($texto['estados'][$estado_turno] ?? $estado_turno); ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($estado_pago); ?>"><?php echo htmlspecialchars($texto['pagos'][$estado_pago] ?? $estado_pago); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <a href="<?php echo htmlspecialchars($panel_volver); ?>" class="nav-link">&larr; <?php echo $texto['volver']; ?></a>
    </div>

    <!-- Modal de edicion rapida: el detalle del turno se abre aqui mismo -->
    <div class="modal-fondo" id="modal-detalle" role="dialog" aria-modal="true">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <strong><?php echo $lang_code === 'en' ? 'Appointment' : 'Turno'; ?></strong>
                <button type="button" onclick="cerrarModal()"><?php echo $lang_code === 'en' ? 'Close' : 'Cerrar'; ?></button>
            </div>
            <div class="modal-cuerpo">
                <iframe id="iframe-detalle" title="<?php echo $lang_code === 'en' ? 'Appointment detail' : 'Detalle del turno'; ?>" src=""></iframe>
            </div>
        </div>
    </div>

    <script>
        // Abre el detalle de un turno dentro de la agenda (sin salir de la pagina).
        function abrirDetalle(url) {
            document.getElementById('iframe-detalle').src = url;
            document.getElementById('modal-detalle').classList.add('abierta');
        }

        function cerrarModal() {
            document.getElementById('modal-detalle').classList.remove('abierta');
            document.getElementById('iframe-detalle').src = '';
        }

        document.querySelectorAll('.turno-clicable').forEach(function (elemento) {
            elemento.addEventListener('click', function () {
                if (elemento.dataset.detalle) {
                    abrirDetalle(elemento.dataset.detalle);
                }
            });
        });

        // Al pulsar Escape se cierra el modal.
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrarModal();
            }
        });

        // El detalle embebido avisa cuando algo cambio: se recarga la agenda
        // para que la grilla muestre los datos nuevos.
        window.addEventListener('message', function (evento) {
            if (!evento.data || typeof evento.data !== 'object') {
                return;
            }
            if (evento.data.tipo === 'tgs-cerrar') {
                cerrarModal();
            } elseif (evento.data.tipo === 'tgs-actualizar') {
                window.location.reload();
            }
        });
    </script>

    <footer>
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
