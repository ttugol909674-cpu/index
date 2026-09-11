<?php
require_once 'auth.php';
verificarRol(['admin', 'gestor']);
require_once 'conexion.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
$panel_volver = ($rol_actual === 'admin') ? 'dashboard_admin.php' : 'dashboard_gestor.php';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
# Textos traducidos de la administración de reservas
$texto = $lang_code === 'en' ? [
    'titulo' => 'Reservation Administration', 'inicio' => 'Back to Home', 'panel' => 'Panel', 'hola' => 'Hello', 'salir' => 'Sign Out',
    'listado' => 'General Reservation List', 'resumen' => 'Manage each appointment status, reschedule appointments, and cancel reservations when necessary.',
    'usuario' => 'User', 'cliente' => 'Customer', 'correo' => 'Email', 'estado' => 'Status', 'fecha' => 'Date', 'hora' => 'Time', 'acciones' => 'Actions',
    'sin_reservas' => 'No reservations registered in the system.', 'guardar' => 'Save', 'reprogramar' => 'Reschedule', 'cancelar' => 'Cancel',
    'historial' => 'Change history', 'reserva' => 'Reservation', 'cambio' => 'Change', 'anterior' => 'Previous', 'nuevo' => 'New', 'sin_cambios' => 'No changes recorded yet.'
] : [
    'titulo' => 'Administración de Reservas', 'inicio' => 'Volver al Inicio', 'panel' => 'Panel', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'listado' => 'Listado General de Reservas', 'resumen' => 'Gestioná el estado de cada turno, reprogramá citas y cancelá reservas cuando sea necesario.',
    'usuario' => 'Usuario', 'cliente' => 'Cliente', 'correo' => 'Correo', 'estado' => 'Estado', 'fecha' => 'Fecha', 'hora' => 'Hora', 'acciones' => 'Acciones',
    'sin_reservas' => 'No hay reservas registradas en el sistema.', 'guardar' => 'Guardar', 'reprogramar' => 'Reprogramar', 'cancelar' => 'Cancelar',
    'historial' => 'Historial de cambios', 'reserva' => 'Reserva', 'cambio' => 'Cambio', 'anterior' => 'Anterior', 'nuevo' => 'Nuevo', 'sin_cambios' => 'No hay cambios registrados aún.'
];
$mensaje = '';

try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS reserva_historial (
        id_historial INT AUTO_INCREMENT PRIMARY KEY,
        id_reserva INT NOT NULL,
        campo VARCHAR(50) NOT NULL,
        valor_anterior TEXT NULL,
        valor_nuevo TEXT NULL,
        fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
        usuario VARCHAR(100) NULL
    )");
} catch (PDOException $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reserva'])) {
    $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);

    if ($id_reserva) {
        if (isset($_POST['accion']) && $_POST['accion'] === 'reprogramar_reserva') {
            $nueva_fecha = trim((string) ($_POST['nueva_fecha_reserva'] ?? ''));
            $nueva_hora = trim((string) ($_POST['nueva_hora_reserva'] ?? ''));

            $columnas_reserva = [];
            try {
                $colStmt = $conexion->query('SHOW COLUMNS FROM reserva');
                if ($colStmt) {
                    $columnas_reserva = $colStmt->fetchAll(PDO::FETCH_COLUMN);
                }
            } catch (PDOException $e) {
                $columnas_reserva = [];
            }

            $fecha_col = null;
            foreach (['fecha_reserva', 'fecha', 'Fecha', 'fecha_creacion', 'created_at'] as $campo) {
                if (in_array($campo, $columnas_reserva, true)) {
                    $fecha_col = $campo;
                    break;
                }
            }

            $hora_col = null;
            foreach (['hora_reserva', 'hora', 'Hora', 'horario', 'hora_turno'] as $campo) {
                if (in_array($campo, $columnas_reserva, true)) {
                    $hora_col = $campo;
                    break;
                }
            }

            $reserva_actual = $conexion->prepare('SELECT * FROM reserva WHERE id_reserva = :id_reserva LIMIT 1');
            $reserva_actual->execute(['id_reserva' => $id_reserva]);
            $reserva_actual = $reserva_actual->fetch(PDO::FETCH_ASSOC);

            $valida_fecha = $nueva_fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nueva_fecha);
            $valida_hora = $nueva_hora === '' || preg_match('/^\d{2}:\d{2}$/', $nueva_hora);

            if ($valida_fecha && $valida_hora) {
                $updates = [];
                $params = ['id_reserva' => $id_reserva];

                if ($fecha_col && !empty($nueva_fecha)) {
                    $valor_actual = (string) ($reserva_actual[$fecha_col] ?? '');
                    if ($valor_actual !== $nueva_fecha) {
                        $updates[] = "`$fecha_col` = :nueva_fecha";
                        $params['nueva_fecha'] = $nueva_fecha;
                    }
                }

                if ($hora_col && $nueva_hora !== '') {
                    $valor_actual = (string) ($reserva_actual[$hora_col] ?? '');
                    if ($valor_actual !== $nueva_hora) {
                        $updates[] = "`$hora_col` = :nueva_hora";
                        $params['nueva_hora'] = $nueva_hora;
                    }
                }

                if (!empty($updates)) {
                    try {
                        $sql_update = 'UPDATE reserva SET ' . implode(', ', $updates) . ' WHERE id_reserva = :id_reserva';
                        $stmt_update = $conexion->prepare($sql_update);
                        $stmt_update->execute($params);

                        $clienteStmt = $conexion->prepare('SELECT u.email, u.nombre, u.apellido FROM reserva r LEFT JOIN usuario u ON u.cedula = r.cedula_usuario WHERE r.id_reserva = :id_reserva LIMIT 1');
                        $clienteStmt->execute(['id_reserva' => $id_reserva]);
                        $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);

                        $email_destino = $cliente['email'] ?? null;
                        $nombre_cliente = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')) ?: 'Cliente';
                        $fecha_final = !empty($nueva_fecha) ? $nueva_fecha : ($reserva_actual[$fecha_col] ?? 'fecha no disponible');
                        $hora_final = !empty($nueva_hora) ? $nueva_hora : ($reserva_actual[$hora_col] ?? 'hora no disponible');

                        $historial_insert = $conexion->prepare('INSERT INTO reserva_historial (id_reserva, campo, valor_anterior, valor_nuevo, usuario) VALUES (:id_reserva, :campo, :valor_anterior, :valor_nuevo, :usuario)');
                        foreach (['fecha', 'hora'] as $campo) {
                            if ($campo === 'fecha' && $fecha_col && !empty($nueva_fecha) && ($reserva_actual[$fecha_col] ?? '') !== $nueva_fecha) {
                                $historial_insert->execute([
                                    'id_reserva' => $id_reserva,
                                    'campo' => 'fecha_reserva',
                                    'valor_anterior' => (string) ($reserva_actual[$fecha_col] ?? ''),
                                    'valor_nuevo' => $nueva_fecha,
                                    'usuario' => $nombre_usuario,
                                ]);
                            }

                            if ($campo === 'hora' && $hora_col && $nueva_hora !== '' && ($reserva_actual[$hora_col] ?? '') !== $nueva_hora) {
                                $historial_insert->execute([
                                    'id_reserva' => $id_reserva,
                                    'campo' => 'hora_reserva',
                                    'valor_anterior' => (string) ($reserva_actual[$hora_col] ?? ''),
                                    'valor_nuevo' => $nueva_hora,
                                    'usuario' => $nombre_usuario,
                                ]);
                            }
                        }

                        if ($email_destino && filter_var($email_destino, FILTER_VALIDATE_EMAIL)) {
                            $asunto = 'Tu reserva fue reprogramada';
                            $mensaje_email = "Hola $nombre_cliente,\n\nTu reserva fue reprogramada para el $fecha_final a las $hora_final.\n\nSaludos,\nTGS";
                            $headers = "From: no-reply@tgs.local\r\nReply-To: no-reply@tgs.local\r\nX-Mailer: PHP/" . phpversion();
                            @mail($email_destino, $asunto, $mensaje_email, $headers);
                        }

                        $mensaje = 'La reserva fue reprogramada correctamente y el cliente fue avisado.';
                    } catch (PDOException $e) {
                        $mensaje = 'No se pudo reprogramar la reserva.';
                    }
                } else {
                    $mensaje = 'No hubo cambios en la fecha o la hora.';
                }
            } else {
                $mensaje = 'La fecha o la hora ingresadas no son válidas.';
            }
        }

        if (isset($_POST['estado_reserva'])) {
            $estado = trim((string) $_POST['estado_reserva']);
            $permitidos = ['Pendiente', 'En curso', 'Finalizada', 'Cancelada'];

            if (in_array($estado, $permitidos, true)) {
                $columnas_reserva = [];
                try {
                    $colStmt = $conexion->query('SHOW COLUMNS FROM reserva');
                    if ($colStmt) {
                        $columnas_reserva = $colStmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                } catch (PDOException $e) {
                    $columnas_reserva = [];
                }

                $estado_col = null;
                foreach (['estado', 'Estado', 'status', 'Status'] as $campo) {
                    if (in_array($campo, $columnas_reserva, true)) {
                        $estado_col = $campo;
                        break;
                    }
                }

                if ($estado_col) {
                    try {
                        $stmt = $conexion->prepare("UPDATE reserva SET `$estado_col` = :estado WHERE id_reserva = :id_reserva");
                        $stmt->execute([
                            'estado' => $estado,
                            'id_reserva' => $id_reserva
                        ]);
                        $mensaje = 'Estado actualizado correctamente.';
                    } catch (PDOException $e) {
                        $mensaje = 'No se pudo actualizar el estado.';
                    }
                }
            }
        }

        if (isset($_POST['accion']) && $_POST['accion'] === 'cancelar') {
            try {
                $stmt = $conexion->prepare('DELETE FROM reserva WHERE id_reserva = :id_reserva');
                $stmt->execute(['id_reserva' => $id_reserva]);
                $mensaje = 'La reserva fue cancelada correctamente.';
            } catch (PDOException $e) {
                $mensaje = 'No se pudo cancelar la reserva.';
            }
        }
    }
}

$columnas_reserva = [];
try {
    $colStmt = $conexion->query('SHOW COLUMNS FROM reserva');
    if ($colStmt) {
        $columnas_reserva = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $columnas_reserva = [];
}

$estado_col = null;
foreach (['estado', 'Estado', 'status', 'Status'] as $campo) {
    if (in_array($campo, $columnas_reserva, true)) {
        $estado_col = $campo;
        break;
    }
}

$fecha_col = null;
foreach (['fecha_reserva', 'fecha', 'Fecha', 'fecha_creacion', 'created_at'] as $campo) {
    if (in_array($campo, $columnas_reserva, true)) {
        $fecha_col = $campo;
        break;
    }
}

$hora_col = null;
foreach (['hora_reserva', 'hora', 'Hora', 'horario', 'hora_turno'] as $campo) {
    if (in_array($campo, $columnas_reserva, true)) {
        $hora_col = $campo;
        break;
    }
}

$reservas = [];
try {
    $sql = 'SELECT r.*, u.nombre, u.apellido, u.email FROM reserva r LEFT JOIN usuario u ON u.cedula = r.cedula_usuario ORDER BY r.id_reserva DESC';
    $stmt = $conexion->query($sql);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservas = [];
}

foreach ($reservas as $idx => $reserva) {
    $estado_actual = $estado_col ? ($reservas[$idx][$estado_col] ?? 'Sin estado') : 'Sin estado';
    $reservas[$idx]['_estado'] = $estado_actual;
    $reservas[$idx]['_fecha'] = $fecha_col ? ($reservas[$idx][$fecha_col] ?? 'Sin fecha') : 'Sin fecha';
    $reservas[$idx]['_hora'] = $hora_col ? ($reservas[$idx][$hora_col] ?? 'Sin hora') : 'Sin hora';
    $reservas[$idx]['_cliente'] = trim((($reservas[$idx]['nombre'] ?? '') . ' ' . ($reservas[$idx]['apellido'] ?? '')));
    if ($reservas[$idx]['_cliente'] === '') {
        $reservas[$idx]['_cliente'] = 'Cliente sin nombre';
    }
    $reservas[$idx]['_email'] = $reservas[$idx]['email'] ?? 'Sin correo';
}

$historial = [];
try {
    $historial_stmt = $conexion->query('SELECT * FROM reserva_historial ORDER BY fecha_cambio DESC LIMIT 12');
    $historial = $historial_stmt ? $historial_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $historial = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
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
        .welcome-card {
            background-color: rgba(30, 41, 59, 0.95);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            margin-bottom: 24px;
        }
        .welcome-card h2 { font-size: 24px; color: #38bdf8; margin-bottom: 10px; }
        .welcome-card p { color: #94a3b8; font-size: 14px; }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            border: 1px solid #334155;
            background: rgba(34, 197, 94, 0.1);
            color: #bbf7d0;
        }
        table { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #334155; font-size: 14px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; }
        .history-box {
            margin-top: 26px;
            background-color: rgba(30, 41, 59, 0.95);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 22px 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }
        .history-box h3 { margin-bottom: 14px; color: #38bdf8; }
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
        .status-form { display: flex; align-items: center; gap: 8px; margin: 0; }
        .status-form select {
            background: rgba(15, 23, 42, 0.9);
            color: white;
            border: 1px solid #475569;
            border-radius: 8px;
            padding: 7px 10px;
            min-width: 120px;
        }
        .status-form button,
        .cancel-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .status-form button { background: #2563eb; }
        .status-form button:hover { background: #1d4ed8; }
        .cancel-btn:hover { background: #dc2626; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>
    <header>
        <a href="<?php echo htmlspecialchars($panel_volver); ?>" class="logo">TGS · <?php echo $texto['titulo']; ?></a>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> <?php echo $texto['inicio']; ?></a>
            <a href="<?php echo htmlspecialchars($panel_volver); ?>" class="nav-link" style="color: #38bdf8; font-weight: bold;"><?php echo $texto['panel']; ?></a>
            <!-- Selector de idioma de la administración de reservas -->
            <div class="language-selector"><a href="administrar_reservas.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a><a href="administrar_reservas.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a></div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong> <span style="font-size: 11px; color: #38bdf8;">(<?php echo ucfirst($rol_actual); ?>)</span></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $texto['listado']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php echo $texto['usuario']; ?></th>
                    <th><?php echo $texto['cliente']; ?></th>
                    <th><?php echo $texto['correo']; ?></th>
                    <th><?php echo $texto['estado']; ?></th>
                    <th><?php echo $texto['fecha']; ?></th>
                    <th><?php echo $texto['hora']; ?></th>
                    <th><?php echo $texto['acciones']; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservas)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #94a3b8;"><?php echo $texto['sin_reservas']; ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reservas as $reserva): ?>
                        <?php
                            $estado_actual = (string) ($reserva['_estado'] ?? 'Sin estado');
                            $estado_norm = strtolower(trim($estado_actual));
                            $badge_class = 'pending';
                            if ($estado_norm === 'finalizada' || $estado_norm === 'completed' || $estado_norm === 'realizada' || $estado_norm === 'done') {
                                $badge_class = 'done';
                            } elseif ($estado_norm === 'cancelada' || $estado_norm === 'cancelled') {
                                $badge_class = 'cancel';
                            }
                        ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($reserva['id_reserva'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($reserva['cedula_usuario'] ?? $reserva['id_usuario'] ?? 'Sin documento'); ?></td>
                            <td><?php echo htmlspecialchars($reserva['_cliente'] ?? 'Cliente sin nombre'); ?></td>
                            <td><?php echo htmlspecialchars($reserva['_email'] ?? 'Sin correo'); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($estado_actual ?: 'Sin estado'); ?></span></td>
                            <td><?php echo htmlspecialchars($reserva['_fecha'] ?? 'Sin fecha'); ?></td>
                            <td><?php echo htmlspecialchars($reserva['_hora'] ?? 'Sin hora'); ?></td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                                    <form method="POST" class="status-form" style="display:inline-flex;">
                                        <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($reserva['id_reserva'] ?? ''); ?>">
                                        <select name="estado_reserva">
                                            <option value="Pendiente" <?php echo ($estado_norm === 'pendiente' || $estado_norm === 'pending') ? 'selected' : ''; ?>>Pendiente</option>
                                            <option value="En curso" <?php echo ($estado_norm === 'en curso' || $estado_norm === 'in progress' || $estado_norm === 'en_curso') ? 'selected' : ''; ?>>En curso</option>
                                            <option value="Finalizada" <?php echo ($estado_norm === 'finalizada' || $estado_norm === 'completed' || $estado_norm === 'realizada' || $estado_norm === 'done') ? 'selected' : ''; ?>>Finalizada</option>
                                            <option value="Cancelada" <?php echo ($estado_norm === 'cancelada' || $estado_norm === 'cancelled') ? 'selected' : ''; ?>>Cancelada</option>
                                        </select>
                                        <button type="submit"><?php echo $texto['guardar']; ?></button>
                                    </form>

                                    <form method="POST" style="display:inline-flex; align-items:center; gap:6px;" onsubmit="return confirm('¿Deseas cambiar la fecha y hora y avisar al cliente?');">
                                        <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($reserva['id_reserva'] ?? ''); ?>">
                                        <input type="hidden" name="accion" value="reprogramar_reserva">
                                        <input type="date" name="nueva_fecha_reserva" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime((string) ($reserva['_fecha'] ?? date('Y-m-d'))))); ?>" style="background: rgba(15,23,42,0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 7px 10px;">
                                        <input type="time" name="nueva_hora_reserva" value="<?php echo htmlspecialchars($reserva['_hora'] !== 'Sin hora' ? substr((string) $reserva['_hora'], 0, 5) : '09:00'); ?>" style="background: rgba(15,23,42,0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 7px 10px;">
                                        <button type="submit" class="cancel-btn" style="background:#2563eb;"><?php echo $texto['reprogramar']; ?></button>
                                    </form>

                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de cancelar esta reserva?');">
                                        <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($reserva['id_reserva'] ?? ''); ?>">
                                        <input type="hidden" name="accion" value="cancelar">
                                        <button type="submit" class="cancel-btn"><?php echo $texto['cancelar']; ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="history-box">
            <h3><?php echo $texto['historial']; ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?php echo $texto['reserva']; ?></th>
                        <th><?php echo $texto['cambio']; ?></th>
                        <th><?php echo $texto['anterior']; ?></th>
                        <th><?php echo $texto['nuevo']; ?></th>
                        <th><?php echo $texto['usuario']; ?></th>
                        <th><?php echo $texto['fecha']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:#94a3b8;"><?php echo $texto['sin_cambios']; ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historial as $item): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($item['id_reserva'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['campo'] ?? 'Cambio'); ?></td>
                                <td><?php echo htmlspecialchars($item['valor_anterior'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($item['valor_nuevo'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($item['usuario'] ?? 'Sistema'); ?></td>
                                <td><?php echo htmlspecialchars($item['fecha_cambio'] ?? ''); ?></td>
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
