<?php
# Protección de acceso: gestor y administrador pueden entrar
require_once 'auth.php';
verificarRol(['gestor', 'admin']);

# Conexión a la base de datos
require_once 'conexion.php';

# Idioma actual para la interfaz del panel
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'en' ? 'en' : 'es';
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'es';
}
$lang = $_SESSION['lang'];

# Textos traducidos que utiliza todo el panel del gestor
$traducciones = [
    'es' => [
        'inicio' => 'Volver al Inicio', 'panel' => 'Panel Gestor', 'hola' => 'Hola', 'rol' => 'Gestor',
        'salir' => 'Cerrar Sesión', 'titulo' => 'Panel de Control del Gestor',
        'resumen' => 'Resumen de reservas, talleres y desempeño del mes. Desde aquí podés revisar actividad, cambiar estados y administrar la operación.',
        'reservas' => 'Administrar Reservas', 'talleres' => 'Administrar Talleres', 'servicios' => 'Administrar Servicios', 'nuevo_taller' => 'Nuevo Taller',
        'total' => 'Reservas totales', 'pendientes' => 'Pendientes', 'curso' => 'En curso', 'finalizadas' => 'Finalizadas',
        'canceladas' => 'Canceladas', 'ganancia' => 'Ganancia del mes', 'recientes' => 'Reservas recientes',
        'usuario' => 'Usuario', 'estado' => 'Estado', 'fecha' => 'Fecha', 'accion' => 'Acción', 'guardar' => 'Guardar',
        'sin_reservas' => 'No hay reservas registradas.', 'gestion_talleres' => 'Gestión de talleres',
        'sin_talleres' => 'No hay talleres cargados.', 'editar' => 'Editar', 'ver' => 'Ver',
        'historial' => 'Historial del mes', 'sin_actividad' => 'No hay actividad registrada este mes.',
        'estado_actualizado' => 'Estado actualizado correctamente.', 'error_reserva' => 'No se pudo actualizar la reserva.',
        'sin_estado' => 'Sin estado', 'sin_fecha' => 'Sin fecha', 'administracion' => 'Administración', 'marca' => 'Taller Global de Servicios', 'derechos' => 'Todos los derechos reservados.'
    ],
    'en' => [
        'inicio' => 'Back to Home', 'panel' => 'Manager Panel', 'hola' => 'Hello', 'rol' => 'Manager',
        'salir' => 'Sign Out', 'titulo' => 'Manager Control Panel',
        'resumen' => 'Overview of reservations, workshops, and monthly performance. Review activity, change statuses, and manage operations here.',
        'reservas' => 'Manage Reservations', 'talleres' => 'Manage Workshops', 'servicios' => 'Manage Services', 'nuevo_taller' => 'New Workshop',
        'total' => 'Total reservations', 'pendientes' => 'Pending', 'curso' => 'In progress', 'finalizadas' => 'Completed',
        'canceladas' => 'Cancelled', 'ganancia' => 'Monthly revenue', 'recientes' => 'Recent reservations',
        'usuario' => 'User', 'estado' => 'Status', 'fecha' => 'Date', 'accion' => 'Action', 'guardar' => 'Save',
        'sin_reservas' => 'No reservations registered.', 'gestion_talleres' => 'Workshop management',
        'sin_talleres' => 'No workshops loaded.', 'editar' => 'Edit', 'ver' => 'View',
        'historial' => 'Monthly history', 'sin_actividad' => 'No activity recorded this month.',
        'estado_actualizado' => 'Status updated successfully.', 'error_reserva' => 'The reservation could not be updated.',
        'sin_estado' => 'No status', 'sin_fecha' => 'No date', 'administracion' => 'Administration', 'marca' => 'Global Workshop Services', 'derechos' => 'All rights reserved.'
    ]
];
$texto = $traducciones[$lang];

# Traduce los estados guardados en la base de datos para mostrarlos en el idioma elegido
function traducirEstado($estado, $idioma, $sin_estado)
{
    $estado_normalizado = strtolower(trim((string) $estado));
    if ($idioma === 'en') {
        $traducciones_estado = [
            'pendiente' => 'Pending', 'pending' => 'Pending',
            'en curso' => 'In progress', 'in progress' => 'In progress', 'en_curso' => 'In progress', 'proceso' => 'In progress',
            'finalizada' => 'Completed', 'completed' => 'Completed', 'realizada' => 'Completed', 'done' => 'Completed',
            'cancelada' => 'Cancelled', 'cancelled' => 'Cancelled'
        ];
        return $traducciones_estado[$estado_normalizado] ?? ($estado ?: $sin_estado);
    }

    return $estado ?: $sin_estado;
}
$nombre_usuario = $_SESSION['nombre'] ?? 'Gestor';
$mensaje = '';

# Cambia el estado de una reserva desde el panel del gestor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reserva'], $_POST['estado_reserva'])) {
    $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
    $estado = trim($_POST['estado_reserva'] ?? '');
    $permitidos = ['Pendiente', 'En curso', 'Finalizada', 'Cancelada'];

    if ($id_reserva && in_array($estado, $permitidos, true)) {
        try {
            $stmt = $conexion->prepare("UPDATE reserva SET estado = :estado WHERE id_reserva = :id_reserva");
            $stmt->execute([
                'estado' => $estado,
                'id_reserva' => $id_reserva
            ]);
            $mensaje = $texto['estado_actualizado'];
        } catch (PDOException $e) {
            $mensaje = $texto['error_reserva'];
        }
    }
}

# Detecta columnas reales de la tabla reserva para no romper la app si hay nombres distintos
$columnas_reserva = [];
try {
    $colStmt = $conexion->query("SHOW COLUMNS FROM reserva");
    $columnas_reserva = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
} catch (PDOException $e) {
    $columnas_reserva = [];
}

# Busca si existe una columna de estado para reservas
$estado_col = null;
foreach (['estado', 'Estado', 'status', 'Status'] as $c) {
    if (in_array($c, $columnas_reserva, true)) {
        $estado_col = $c;
        break;
    }
}

# Consulta de reservas recientes
$reservas = [];
try {
    $sql_reservas = "SELECT * FROM reserva ORDER BY id_reserva DESC LIMIT 12";
    $stmt = $conexion->query($sql_reservas);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservas = [];
}

# Totales para el resumen del gestor
$total_reservas = count($reservas);
$pendientes = 0;
$finalizadas = 0;
$canceladas = 0;
$en_curso = 0;
$ganancia_mes = 0;
$historial_mes = [];
$mes_actual = date('Y-m');

foreach ($reservas as $res) {
    $estado_actual = $estado_col ? ($res[$estado_col] ?? '') : '';
    $estado_normalizado = strtolower(trim((string)$estado_actual));

    if ($estado_normalizado === 'pendiente' || $estado_normalizado === 'pending') {
        $pendientes++;
    } elseif ($estado_normalizado === 'en curso' || $estado_normalizado === 'in progress' || $estado_normalizado === 'en_curso' || $estado_normalizado === 'proceso') {
        $en_curso++;
    } elseif ($estado_normalizado === 'finalizada' || $estado_normalizado === 'completed' || $estado_normalizado === 'realizada' || $estado_normalizado === 'done') {
        $finalizadas++;
    } elseif ($estado_normalizado === 'cancelada' || $estado_normalizado === 'cancelled') {
        $canceladas++;
    }

    $fecha_reserva = $res['fecha_reserva'] ?? $res['fecha'] ?? $res['Fecha'] ?? $res['fecha_creacion'] ?? $res['created_at'] ?? null;
    $fecha_valida = null;
    if (!empty($fecha_reserva) && strtotime((string)$fecha_reserva) !== false) {
        $fecha_valida = date('Y-m', strtotime((string)$fecha_reserva));
    }

    if ($fecha_valida === $mes_actual) {
        $historial_mes[] = $res;
    }

    $es_finalizada = in_array($estado_normalizado, ['finalizada', 'completed', 'realizada', 'done'], true);
    if ($es_finalizada && $fecha_valida === $mes_actual) {
        $precio = 0;
        foreach (['precio', 'precio_total', 'monto', 'total', 'valor', 'importe'] as $campo) {
            if (isset($res[$campo]) && is_numeric($res[$campo])) {
                $precio = (float) $res[$campo];
                break;
            }
        }

        if ($precio <= 0) {
            $precio = 1500;
        }

        $ganancia_mes += $precio;
    }
}

$historial_mes = array_slice($historial_mes, 0, 5);

# Consulta de talleres para gestión rápida
$talleres = [];
try {
    $stmt_talleres = $conexion->query("SELECT * FROM taller ORDER BY id_taller DESC LIMIT 5");
    $talleres = $stmt_talleres->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $talleres = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['panel']; ?> - TGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/tema-tgs.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            background: radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 32%), #0b1329;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        a { text-decoration: none; }
        header {
            background: rgba(17, 28, 56, 0.96);
            border-bottom: 1px solid rgba(56, 189, 248, 0.22);
            padding: 13px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            z-index: 1000;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        nav span { color: #94a3b8; font-size: 14px; }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.3s; }
        nav a.btn-logout:hover { background-color: #dc2626; }
        .language-selector { display: flex; gap: 4px; align-items: center; }
        .language-selector a {
            color: #cbd5e1;
            border: 1px solid #475569;
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 12px;
            font-weight: 700;
        }
        .language-selector a:hover,
        .language-selector a.active { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .main-container { flex: 1; padding: 30px 20px 42px; max-width: 1200px; width: 100%; margin: 0 auto; }
        .welcome-card {
            background: linear-gradient(120deg, rgba(17, 28, 56, 0.98), rgba(15, 52, 80, 0.92));
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 12px;
            padding: 26px 28px;
            margin-bottom: 18px;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
            position: relative;
            overflow: hidden;
        }
        .welcome-card::after { content: ''; position: absolute; width: 180px; height: 180px; right: -70px; top: -90px; border: 1px solid rgba(125, 211, 252, 0.25); border-radius: 50%; }
        .welcome-card h2 { font-size: 22px; color: #38bdf8; margin-bottom: 8px; }
        .welcome-card p { color: #94a3b8; font-size: 13px; line-height: 1.6; }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .admin-card-btn {
            background: linear-gradient(160deg, #111c38, #101a31);
            border: 1px solid rgba(71, 85, 105, 0.7);
            border-radius: 10px;
            padding: 20px 16px;
            text-align: center;
            text-decoration: none;
            color: #f8fafc;
            transition: transform 0.2s, border-color 0.2s, background-color 0.2s;
        }
        .admin-card-btn:hover {
            transform: translateY(-4px);
            border-color: #38bdf8;
            background: linear-gradient(160deg, #162b4b, #111c38);
            box-shadow: 0 12px 24px rgba(14, 165, 233, 0.12);
        }
        .admin-card-btn i {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: block;
            color: #7dd3fc;
        }
        .admin-card-btn span {
            font-size: 0.98rem;
            font-weight: bold;
            color: #fff;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(160deg, #111c38, #101a31);
            border: 1px solid rgba(71, 85, 105, 0.7);
            border-radius: 10px;
            padding: 18px;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before { content: ''; position: absolute; left: 0; top: 0; width: 100%; height: 3px; background: #38bdf8; opacity: 0.75; }
        .stat-card i {
            font-size: 1.7rem;
            color: #38bdf8;
            margin-bottom: 10px;
            display: inline-block;
        }
        .stat-card .label {
            color: #94a3b8;
            display: block;
            font-size: 0.82rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-card .value { font-size: 1.75rem; font-weight: 700; }
        .panel-grid {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 24px;
            margin-bottom: 26px;
        }
        .card {
            background: #111c38;
            border: 1px solid rgba(71, 85, 105, 0.7);
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.16);
        }
        .card h3 { margin-top: 0; color: #7dd3fc; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid rgba(71, 85, 105, 0.55); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 9px; border-bottom: 1px solid #334155; text-align: left; font-size: 0.86rem; }
        th { color: #7dd3fc; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: rgba(56, 189, 248, 0.15);
            color: #7dd3fc;
            border: 1px solid rgba(125, 211, 252, 0.35);
        }
        .badge.pending { background: rgba(250, 204, 21, 0.12); color: #facc15; border-color: rgba(250,204,21,0.35); }
        .badge.done { background: rgba(34, 197, 94, 0.12); color: #4ade80; border-color: rgba(34,197,94,0.35); }
        .badge.cancel { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border-color: rgba(239,68,68,0.35); }
        select, button { font: inherit; }
        .status-form { display: flex; align-items: center; gap: 8px; margin: 0; }
        .status-form select {
            background: rgba(15, 23, 42, 0.9);
            color: white;
            border: 1px solid #475569;
            border-radius: 8px;
            padding: 7px 10px;
            min-width: 120px;
        }
        .status-form button {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
        }
        .taller-list { display: grid; gap: 12px; }
        .taller-item {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .taller-item strong { display: block; margin-bottom: 4px; color: #f8fafc; }
        .taller-item span { color: #cbd5e1; font-size: 0.85rem; }
        .small-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .small-actions a {
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            border: 1px solid #334155;
            background: rgba(34, 197, 94, 0.1);
            color: #bbf7d0;
        }
        footer { text-align: center; padding: 16px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
        @media (max-width: 980px) {
            .panel-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header>
        <a href="dashboard_gestor.php" class="logo">TGS · <?php echo $texto['panel']; ?></a>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> <?php echo $texto['inicio']; ?></a>
            <a href="dashboard_gestor.php" class="nav-link" style="color: #38bdf8; font-weight: bold;"><?php echo $texto['panel']; ?></a>
            <div class="language-selector" aria-label="Selector de idioma">
                <a href="dashboard_gestor.php?lang=es" class="<?php echo $lang === 'es' ? 'active' : ''; ?>">ES</a>
                <a href="dashboard_gestor.php?lang=en" class="<?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong> <span style="font-size: 11px; color: #38bdf8;">(<?php echo $texto['rol']; ?>)</span></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <div class="main-container">
        <!-- Resumen operativo del gestor -->
        <div class="welcome-card">
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Acciones rápidas para administrar la operación del taller -->
        <div class="quick-actions-grid">
            <a href="administrar_reservas.php" class="admin-card-btn">
                <i class="fas fa-calendar-check"></i>
                <span><?php echo $texto['reservas']; ?></span>
            </a>
            <a href="administrar_talleres.php" class="admin-card-btn">
                <i class="fas fa-tools"></i>
                <span><?php echo $texto['talleres']; ?></span>
            </a>
            <a href="agregar_taller.php" class="admin-card-btn">
                <i class="fas fa-square-plus"></i>
                <span><?php echo $texto['nuevo_taller']; ?></span>
            </a>
            <a href="administrar_servicios.php" class="admin-card-btn">
                <i class="fas fa-wrench"></i>
                <span><?php echo $texto['servicios']; ?></span>
            </a>
        </div>

        <!-- Indicadores de reservas y ganancias del mes -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fa-solid fa-calendar-days"></i>
                <span class="label"><?php echo $texto['total']; ?></span>
                <span class="value"><?php echo $total_reservas; ?></span>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-clock"></i>
                <span class="label"><?php echo $texto['pendientes']; ?></span>
                <span class="value"><?php echo $pendientes; ?></span>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-spinner"></i>
                <span class="label"><?php echo $texto['curso']; ?></span>
                <span class="value"><?php echo $en_curso; ?></span>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-circle-check"></i>
                <span class="label"><?php echo $texto['finalizadas']; ?></span>
                <span class="value"><?php echo $finalizadas; ?></span>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-ban"></i>
                <span class="label"><?php echo $texto['canceladas']; ?></span>
                <span class="value"><?php echo $canceladas; ?></span>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-dollar-sign"></i>
                <span class="label"><?php echo $texto['ganancia']; ?></span>
                <span class="value">$<?php echo number_format($ganancia_mes, 0, ',', '.'); ?></span>
            </div>
        </div>

        <div class="panel-grid">
            <div class="card">
                <h3><?php echo $texto['recientes']; ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo $texto['usuario']; ?></th>
                            <th><?php echo $texto['estado']; ?></th>
                            <th><?php echo $texto['fecha']; ?></th>
                            <th><?php echo $texto['accion']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservas)): ?>
                            <tr>
                                <td colspan="5" style="color: #94a3b8; text-align: center;"><?php echo $texto['sin_reservas']; ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservas as $reserva): ?>
                                <?php
                                    $estado_actual = $estado_col ? ($reserva[$estado_col] ?? 'Sin estado') : 'Sin estado';
                                    $estado_norm = strtolower(trim((string)$estado_actual));
                                    $badge_class = 'pending';
                                    if ($estado_norm === 'finalizada' || $estado_norm === 'completed' || $estado_norm === 'realizada' || $estado_norm === 'done') {
                                        $badge_class = 'done';
                                    } elseif ($estado_norm === 'cancelada' || $estado_norm === 'cancelled') {
                                        $badge_class = 'cancel';
                                    }
                                ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($reserva['id_reserva'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cedula_usuario'] ?? $texto['usuario']); ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(traducirEstado($estado_actual, $lang, $texto['sin_estado'])); ?></span></td>
                                    <td><?php echo htmlspecialchars($reserva['fecha_reserva'] ?? $reserva['Fecha'] ?? $reserva['fecha'] ?? $texto['sin_fecha']); ?></td>
                                    <td>
                                        <form class="status-form" method="POST">
                                            <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($reserva['id_reserva'] ?? ''); ?>">
                                            <select name="estado_reserva">
                                                <option value="Pendiente" <?php echo ($estado_norm === 'pendiente' || $estado_norm === 'pending') ? 'selected' : ''; ?>><?php echo $lang === 'en' ? 'Pending' : 'Pendiente'; ?></option>
                                                <option value="En curso" <?php echo ($estado_norm === 'en curso' || $estado_norm === 'in progress' || $estado_norm === 'en_curso') ? 'selected' : ''; ?>><?php echo $lang === 'en' ? 'In progress' : 'En curso'; ?></option>
                                                <option value="Finalizada" <?php echo ($estado_norm === 'finalizada' || $estado_norm === 'completed' || $estado_norm === 'realizada' || $estado_norm === 'done') ? 'selected' : ''; ?>><?php echo $lang === 'en' ? 'Completed' : 'Finalizada'; ?></option>
                                                <option value="Cancelada" <?php echo ($estado_norm === 'cancelada' || $estado_norm === 'cancelled') ? 'selected' : ''; ?>><?php echo $lang === 'en' ? 'Cancelled' : 'Cancelada'; ?></option>
                                            </select>
                                            <button type="submit"><?php echo $texto['guardar']; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3><?php echo $texto['gestion_talleres']; ?></h3>
                <div class="taller-list">
                    <?php if (empty($talleres)): ?>
                        <p style="color: #94a3b8; margin: 0;"><?php echo $texto['sin_talleres']; ?></p>
                    <?php else: ?>
                        <?php foreach ($talleres as $taller): ?>
                            <div class="taller-item">
                                <strong><?php echo htmlspecialchars($taller['nombre'] ?? $texto['talleres']); ?></strong>
                                <span><?php echo htmlspecialchars($taller['direccion'] ?? ($lang === 'en' ? 'No address' : 'Sin dirección')); ?></span>
                                <div class="small-actions">
                                    <a href="editar_taller.php" class="btn-primary" style="color: white;"><?php echo $texto['editar']; ?></a>
                                    <a href="administrar_talleres.php" class="btn-success" style="color: white;"><?php echo $texto['ver']; ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 24px;">
            <h3><?php echo $texto['historial']; ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo $texto['usuario']; ?></th>
                        <th><?php echo $texto['estado']; ?></th>
                        <th><?php echo $texto['fecha']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial_mes)): ?>
                        <tr>
                            <td colspan="4" style="color: #94a3b8; text-align: center;"><?php echo $texto['sin_actividad']; ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historial_mes as $item): ?>
                            <?php
                                $estado_item = $estado_col ? ($item[$estado_col] ?? 'Sin estado') : 'Sin estado';
                                $estado_norm_item = strtolower(trim((string)$estado_item));
                                $badge_class_item = 'pending';
                                if ($estado_norm_item === 'finalizada' || $estado_norm_item === 'completed' || $estado_norm_item === 'realizada' || $estado_norm_item === 'done') {
                                    $badge_class_item = 'done';
                                } elseif ($estado_norm_item === 'cancelada' || $estado_norm_item === 'cancelled') {
                                    $badge_class_item = 'cancel';
                                }
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($item['id_reserva'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['cedula_usuario'] ?? $texto['usuario']); ?></td>
                                <td><span class="badge <?php echo $badge_class_item; ?>"><?php echo htmlspecialchars(traducirEstado($estado_item, $lang, $texto['sin_estado'])); ?></span></td>
                                <td><?php echo htmlspecialchars($item['fecha_reserva'] ?? $item['Fecha'] ?? $item['fecha'] ?? $texto['sin_fecha']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $texto['marca']; ?>. <?php echo $texto['derechos']; ?></p>
    </footer>
</body>
</html>
