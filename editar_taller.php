<?php
/**
 * TGS - Edicion de un taller.
 *
 * Formulario para modificar la informacion de un taller existente
 * (nombre, telefono, email, direccion y el codigo de mapa de Google Maps).
 * Recibe el id del taller por GET/POST; solo admin y gestor pueden editar.
 *
 * Depende de: auth.php (verificarRol) y config.php (conexion PDO).
 */
require_once 'auth.php';
require_once __DIR__ . '/config.php';
verificarPermiso('editar_taller');

# Inicia la sesión para manejar idioma y estado del usuario

# Guarda el idioma según la URL o, si no viene, usa la sesión
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'es';

# Traducciones del panel para Español e Inglés
$traducciones = [
    'es' => [
        'brand' => 'TGS - Blue Tech Uruguay',
        'home' => 'Inicio',
        'services' => 'Servicios',
        'about' => 'Sobre nosotros',
        'appointments' => 'Mis Turnos',
        'admin' => 'Administración',
        'hello' => 'Hola, Administrador',
        'signout' => 'Cerrar Sesión',
        'titulo' => 'Editar Información y Ubicación del Taller',
        'lbl_nombre' => 'Nombre del Taller',
        'lbl_telefono' => 'Teléfono',
        'lbl_email' => 'Correo Electrónico',
        'lbl_direccion' => 'Dirección',
        'lbl_mapa' => 'Código HTML de Google Maps (Iframe)',
        'lbl_mapa_help' => 'Ve a Google Maps, comparte tu ubicación, selecciona "Insertar mapa" y pega el código aquí.',
        'lbl_capacidad' => 'Capacidad (turnos simultáneos)',
        'lbl_capacidad_help' => 'Cuántos vehículos puede atender el taller al mismo tiempo.',
        'lbl_horario' => 'Horario de atención',
        'lbl_dia' => 'Día',
        'lbl_desde' => 'Desde',
        'lbl_hasta' => 'Hasta',
        'lbl_abierto' => 'Atiende',
        'dias' => [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'],
        'error_horario' => 'La hora de cierre debe ser posterior a la de apertura.',
        'guardar' => 'Guardar Cambios',
        'volver' => 'Volver',
        'exito' => '¡Taller y ubicación actualizados con éxito!',
        'error_db' => 'Error al actualizar: '
    ],
    'en' => [
        'brand' => 'TGS - Blue Tech Uruguay',
        'home' => 'Home',
        'services' => 'Services',
        'about' => 'About us',
        'appointments' => 'My Appointments',
        'admin' => 'Administration',
        'hello' => 'Hello, Administrator',
        'signout' => 'Sign Out',
        'titulo' => 'Edit Workshop Information & Location',
        'lbl_nombre' => 'Workshop Name',
        'lbl_telefono' => 'Phone',
        'lbl_email' => 'Email',
        'lbl_direccion' => 'Address',
        'lbl_mapa' => 'Google Maps HTML Code (Iframe)',
        'lbl_mapa_help' => 'Go to Google Maps, share your location, select "Embed a map" and paste the code here.',
        'lbl_capacidad' => 'Capacity (simultaneous appointments)',
        'lbl_capacidad_help' => 'How many vehicles the workshop can handle at the same time.',
        'lbl_horario' => 'Opening hours',
        'lbl_dia' => 'Day',
        'lbl_desde' => 'From',
        'lbl_hasta' => 'To',
        'lbl_abierto' => 'Open',
        'dias' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
        'error_horario' => 'The closing time must be later than the opening time.',
        'guardar' => 'Save Changes',
        'volver' => 'Back',
        'exito' => 'Workshop and location successfully updated!',
        'error_db' => 'Error updating: '
    ]
];
$t = $traducciones[$lang];

# Conexión a la base de datos
$conexion = tgs_mysqli();
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

# Variables para mostrar mensajes de éxito o error
$mensaje = "";
$error = "";

$id_taller = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_taller = filter_input(INPUT_POST, 'id_taller', FILTER_VALIDATE_INT);
}

if (!$id_taller) {
    header('Location: administrar_talleres.php');
    exit;
}

# Si el formulario fue enviado por POST, actualiza el taller
# El token CSRF se valida antes de tocar la base: sin el, nada se guarda.
if ($_SERVER["REQUEST_METHOD"] == "POST" && !validarCsrf($_POST['csrf_token'] ?? '')) {
    $error = $lang == 'en' ? 'The session expired. Please reload the page and try again.' : 'La sesión expiró. Recargá la página e intentá nuevamente.';
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    # Escapa los valores para prevenir inyección SQL
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    # Se permite el iframe del mapa para mostrar la ubicación exacta
    $mapa = trim($_POST['mapa'] ?? '');

    # Capacidad: turnos simultaneos que acepta la sede.
    $capacidad = isset($_POST['capacidad']) ? (int) $_POST['capacidad'] : 1;
    if ($capacidad < 1) {
        $capacidad = 1;
    }

    # Horario semanal: solo se guardan los dias marcados como abiertos.
    $dias_abiertos = (array) ($_POST['horario_activo'] ?? []);
    $horarios = [];
    $errores_horario = false;
    for ($dia = 1; $dia <= 7; $dia++) {
        if (!in_array((string) $dia, array_map('strval', $dias_abiertos), true)) {
            continue;
        }
        $desde = trim((string) ($_POST['horario_desde'][$dia] ?? '08:30'));
        $hasta = trim((string) ($_POST['horario_hasta'][$dia] ?? '18:30'));
        if (!preg_match('/^\d{2}:\d{2}$/', $desde) || !preg_match('/^\d{2}:\d{2}$/', $hasta)) {
            continue;
        }
        if (strtotime($desde) >= strtotime($hasta)) {
            $errores_horario = true;
            break;
        }
        $horarios[$dia] = ['desde' => $desde, 'hasta' => $hasta];
    }

    if ($errores_horario) {
        $error = $t['error_horario'];
    } else {
        # Actualiza los datos del taller en la base de datos
        $stmt_update = $conexion->prepare('UPDATE taller SET nombre = ?, telefono = ?, email = ?, direccion = ?, mapa = ?, capacidad = ? WHERE id_taller = ?');

        if ($stmt_update && $stmt_update->bind_param('sssssii', $nombre, $telefono, $email, $direccion, $mapa, $capacidad, $id_taller) && $stmt_update->execute()) {
            # Reemplaza el horario semanal de la sede. Se hace dentro de una
            # transaccion para que no quede el taller sin horario si algo
            # falla a mitad de camino.
            $conexion->begin_transaction();

            $borrar_horario = $conexion->prepare('DELETE FROM taller_horario WHERE id_taller = ?');
            $borrar_horario->bind_param('i', $id_taller);
            $borrar_horario->execute();
            $borrar_horario->close();

            $sql_horario = "INSERT INTO taller_horario (id_taller, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, ?, ?, 1)";
            $stmt_horario = $conexion->prepare($sql_horario);
            foreach ($horarios as $dia => $horario_dia) {
                # bind_param() exige variables (no expresiones) porque las
                # pasa por referencia.
                $hora_inicio = $horario_dia['desde'] . ':00';
                $hora_fin = $horario_dia['hasta'] . ':00';
                $stmt_horario->bind_param('isss', $id_taller, $dia, $hora_inicio, $hora_fin);
                $stmt_horario->execute();
            }
            $stmt_horario->close();

            $conexion->commit();
            $mensaje = $t['exito'];
        } else {
            $error = $t['error_db'] . $conexion->error;
        }
    }
}

# Consulta el taller para cargar sus datos en el formulario
$stmt_taller = $conexion->prepare('SELECT * FROM taller WHERE id_taller = ? LIMIT 1');
$stmt_taller->bind_param('i', $id_taller);
$stmt_taller->execute();
$resultado = $stmt_taller->get_result();
$taller = ($resultado && $resultado->num_rows > 0) ? $resultado->fetch_assoc() : die("No hay talleres registrados.");

# Horario actual de la sede, para precargar el formulario.
$horarios_actuales = [];
$stmt_horarios = $conexion->prepare('SELECT dia_semana, hora_inicio, hora_fin, activo FROM taller_horario WHERE id_taller = ? ORDER BY dia_semana');
$stmt_horarios->bind_param('i', $id_taller);
$stmt_horarios->execute();
$filas_horario = $stmt_horarios->get_result();
while ($fila_horario = $filas_horario->fetch_assoc()) {
    $horarios_actuales[(int) $fila_horario['dia_semana']] = $fila_horario;
}
$stmt_horarios->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <title><?php echo $t['titulo']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #0b1329; color: #ffffff; font-family: sans-serif; }
        .navbar-custom { background-color: #0b1329; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .nav-link-custom { color: #adb5bd; text-decoration: none; font-size: 0.95rem; transition: color 0.2s; }
        .nav-link-custom:hover { color: #ffffff; }
        .card-custom { background-color: #111c38; border: 1px solid #1e293b; color: #ffffff; border-radius: 12px; }
        .form-control, .form-control:focus { background-color: #0b1329; border-color: #1e293b; color: #ffffff; }
        label { color: #cbd5e1; }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4 py-3">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-light" href="index.php"><?php echo $t['brand']; ?></a>
            <div class="d-flex align-items-center ms-auto gap-3">
                <a href="index.php" class="nav-link-custom"><?php echo $t['home']; ?></a>
                <a href="index.php#services" class="nav-link-custom"><?php echo $t['services']; ?></a>
                <a href="index.php#about" class="nav-link-custom"><?php echo $t['about']; ?></a>
                <a href="reservas.php" class="nav-link-custom"><?php echo $t['appointments']; ?></a>
                <a href="dashboard_admin.php" class="btn btn-primary btn-sm px-3 fw-semibold"><?php echo $t['admin']; ?></a>
                
                <div class="btn-group" role="group">
                    <a href="?id=<?php echo $id_taller; ?>&lang=es" class="btn btn-sm <?php echo $lang == 'es' ? 'btn-primary' : 'btn-dark border-secondary'; ?>">ES</a>
                    <a href="?id=<?php echo $id_taller; ?>&lang=en" class="btn btn-sm <?php echo $lang == 'en' ? 'btn-primary' : 'btn-dark border-secondary'; ?>">EN</a>
                </div>

                <span class="text-light small"><?php echo $t['hello']; ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm px-3"><?php echo $t['signout']; ?></a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card card-custom shadow">
                    <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                        <h4 class="mb-0"><?php echo $t['titulo']; ?></h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if (!empty($mensaje)): ?>
                            <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="id_taller" value="<?php echo $id_taller; ?>">
                            <div class="mb-3">
                                <label for="nombre" class="form-label"><?php echo $t['lbl_nombre']; ?></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($taller['nombre'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="telefono" class="form-label"><?php echo $t['lbl_telefono']; ?></label>
                                <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($taller['telefono'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label"><?php echo $t['lbl_email']; ?></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($taller['email'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="direccion" class="form-label"><?php echo $t['lbl_direccion']; ?></label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="2"><?php echo htmlspecialchars($taller['direccion'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="mapa" class="form-label"><?php echo $t['lbl_mapa']; ?></label>
                                <textarea class="form-control" id="mapa" name="mapa" rows="3"><?php echo htmlspecialchars($taller['mapa'] ?? ''); ?></textarea>
                                <div class="form-text text-muted small mt-1"><?php echo $t['lbl_mapa_help']; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="capacidad" class="form-label"><?php echo $t['lbl_capacidad']; ?></label>
                                <input type="number" class="form-control" id="capacidad" name="capacidad" min="1" max="20" value="<?php echo htmlspecialchars($taller['capacidad'] ?? 1); ?>">
                                <div class="form-text text-muted small mt-1"><?php echo $t['lbl_capacidad_help']; ?></div>
                            </div>

                            <!-- Horario de atencion: una fila por dia de la semana -->
                            <div class="mb-3">
                                <label class="form-label"><?php echo $t['lbl_horario']; ?></label>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle" style="background-color: rgba(15,23,42,0.4);">
                                        <thead>
                                            <tr>
                                                <th style="background-color: transparent; border-bottom: 1px solid #1e293b;"><?php echo $t['lbl_dia']; ?></th>
                                                <th style="background-color: transparent; border-bottom: 1px solid #1e293b;"><?php echo $t['lbl_desde']; ?></th>
                                                <th style="background-color: transparent; border-bottom: 1px solid #1e293b;"><?php echo $t['lbl_hasta']; ?></th>
                                                <th style="background-color: transparent; border-bottom: 1px solid #1e293b;"><?php echo $t['lbl_abierto']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($dia = 1; $dia <= 7; $dia++):
                                                $horario_dia = $horarios_actuales[$dia] ?? null;
                                                $abierto = $horario_dia ? ((int) $horario_dia['activo'] === 1) : ($dia <= 6);
                                                $desde = $horario_dia ? substr((string) $horario_dia['hora_inicio'], 0, 5) : '08:30';
                                                $hasta = $horario_dia ? substr((string) $horario_dia['hora_fin'], 0, 5) : '18:30';
                                            ?>
                                                <tr>
                                                    <td style="background-color: transparent;"><?php echo htmlspecialchars($t['dias'][$dia]); ?></td>
                                                    <td style="background-color: transparent;">
                                                        <input type="time" class="form-control form-control-sm" name="horario_desde[<?php echo $dia; ?>]" value="<?php echo $desde; ?>">
                                                    </td>
                                                    <td style="background-color: transparent;">
                                                        <input type="time" class="form-control form-control-sm" name="horario_hasta[<?php echo $dia; ?>]" value="<?php echo $hasta; ?>">
                                                    </td>
                                                    <td style="background-color: transparent;">
                                                        <input class="form-check-input" type="checkbox" name="horario_activo[]" value="<?php echo $dia; ?>" <?php echo $abierto ? 'checked' : ''; ?>>
                                                    </td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?php echo $t['guardar']; ?>
                                </button>
                                <a href="administrar_talleres.php" class="btn btn-outline-light">
                                    <i class="bi bi-arrow-left"></i> <?php echo $t['volver']; ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
