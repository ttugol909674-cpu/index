<?php
###### # Muestra y procesa el formulario para crear un taller.
# Solo administradores y gestores pueden crear talleres
require_once 'auth.php';
require_once __DIR__ . '/config.php';
verificarPermiso('crear_taller');

# Inicia la sesión para manejar idioma del usuario y autenticación

# Si llega idioma por la URL, se guarda en la sesión
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'es';

# Traducciones del formulario para español y inglés
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
        'titulo' => 'Añadir Nuevo Taller',
        'lbl_nombre' => 'Nombre del Taller',
        'lbl_telefono' => 'Teléfono',
        'lbl_email' => 'Correo Electrónico',
        'lbl_direccion' => 'Dirección',
        'lbl_mapa' => 'Código HTML de Google Maps (Iframe)',
        'lbl_mapa_help' => 'Pega el código iframe de Google Maps de la ubicación.',
        'lbl_capacidad' => 'Capacidad (turnos simultáneos)',
        'lbl_capacidad_help' => 'Cuántos vehículos puede atender el taller al mismo tiempo.',
        'lbl_horario' => 'Horario de atención',
        'lbl_dia' => 'Día',
        'lbl_desde' => 'Desde',
        'lbl_hasta' => 'Hasta',
        'lbl_abierto' => 'Atiende',
        'dias' => [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'],
        'error_horario' => 'La hora de cierre debe ser posterior a la de apertura.',
        'guardar' => 'Guardar Taller',
        'volver' => 'Volver',
        'exito' => '¡Taller añadido con éxito!',
        'error_db' => 'Error al guardar: '
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
        'titulo' => 'Add New Workshop',
        'lbl_nombre' => 'Workshop Name',
        'lbl_telefono' => 'Phone',
        'lbl_email' => 'Email',
        'lbl_direccion' => 'Address',
        'lbl_mapa' => 'Google Maps HTML Code (Iframe)',
        'lbl_mapa_help' => 'Paste the Google Maps iframe location code.',
        'lbl_capacidad' => 'Capacity (simultaneous appointments)',
        'lbl_capacidad_help' => 'How many vehicles the workshop can handle at the same time.',
        'lbl_horario' => 'Opening hours',
        'lbl_dia' => 'Day',
        'lbl_desde' => 'From',
        'lbl_hasta' => 'To',
        'lbl_abierto' => 'Open',
        'dias' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
        'error_horario' => 'The closing time must be later than the opening time.',
        'guardar' => 'Save Workshop',
        'volver' => 'Back',
        'exito' => 'Workshop successfully added!',
        'error_db' => 'Error saving: '
    ]
];
$t = $traducciones[$lang];

# Conexión a la base de datos del sistema
$conexion = tgs_mysqli();
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

# Variables para mensajes de éxito o error
$mensaje = "";
$error = "";

# Si el formulario fue enviado por POST, guarda el taller
# El token CSRF se valida antes de tocar la base: sin el, nada se guarda.
if ($_SERVER["REQUEST_METHOD"] == "POST" && !validarCsrf($_POST['csrf_token'] ?? '')) {
    $error = $lang == 'en' ? 'The session expired. Please reload the page and try again.' : 'La sesión expiró. Recargá la página e intentá nuevamente.';
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    # Escapa los valores para evitar inyección SQL
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $telefono = $conexion->real_escape_string($_POST['telefono']);
    $email = $conexion->real_escape_string($_POST['email']);
    $direccion = $conexion->real_escape_string($_POST['direccion']);
    $mapa = $conexion->real_escape_string($_POST['mapa']);

    # Capacidad: cantidad de turnos simultaneos que acepta la sede.
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
        # Insert en la tabla taller con datos del formulario
        $sql_insert = "INSERT INTO taller (nombre, telefono, email, direccion, mapa, capacidad) VALUES ('$nombre', '$telefono', '$email', '$direccion', '$mapa', $capacidad)";

        if ($conexion->query($sql_insert) === TRUE) {
            $id_taller = (int) $conexion->insert_id;

            # Horario de atencion de la sede (tabla taller_horario).
            # bind_param() exige variables (no expresiones) porque las pasa
            # por referencia.
            $sql_horario = "INSERT INTO taller_horario (id_taller, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, ?, ?, 1)";
            $stmt_horario = $conexion->prepare($sql_horario);
            foreach ($horarios as $dia => $horario_dia) {
                $hora_inicio = $horario_dia['desde'] . ':00';
                $hora_fin = $horario_dia['hasta'] . ':00';
                $stmt_horario->bind_param('isss', $id_taller, $dia, $hora_inicio, $hora_fin);
                $stmt_horario->execute();
            }
            $stmt_horario->close();

            $mensaje = $t['exito'];
        } else {
            $error = $t['error_db'] . $conexion->error;
        }
    }
}
# Variables que usa la plantilla del formulario.
$es_edicion = false;
$taller = [];
$items_edicion = [];
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['titulo']; ?> - TGS</title>
    <link rel="stylesheet" href="css/tgs-panel.css">
    <style>
        /* Tabla de horarios: una fila por dia de la semana. */
        .tgs-tabla-horarios { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tgs-tabla-horarios th { background-color: var(--tgs-superficie-alta); color: var(--tgs-acento); padding: 9px 11px; text-align: left; font-weight: 600; }
        .tgs-tabla-horarios td { padding: 7px 11px; border-bottom: 1px solid var(--tgs-borde); color: var(--tgs-texto-suave); }
        .tgs-tabla-horarios tr:last-child td { border-bottom: none; }
        .tgs-tabla-horarios input[type="time"] {
            background-color: var(--tgs-superficie-alta);
            color: var(--tgs-texto);
            border: 1px solid var(--tgs-borde-claro);
            border-radius: 6px;
            padding: 6px 9px;
            font-size: 13px;
            outline: none;
        }
        .tgs-tabla-horarios input[type="time"]:focus { border-color: var(--tgs-acento); }
        .tgs-tabla-horarios input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--tgs-exito); cursor: pointer; }
        .ayuda { font-size: 11px; color: var(--tgs-texto-tenue); margin-top: 4px; }
    </style>
</head>
<body class="tgs">

    <!-- Cabecera de los paneles de TGS -->
    <header class="tgs-header">
        <a href="index.php" class="marca"><?php echo $t['brand']; ?></a>
        <nav class="tgs-nav">
            <a href="index.php" class="enlace"><?php echo $t['home']; ?></a>
            <a href="index.php#services" class="enlace"><?php echo $t['services']; ?></a>
            <a href="index.php#about" class="enlace"><?php echo $t['about']; ?></a>
            <a href="reservas.php" class="enlace"><?php echo $t['appointments']; ?></a>
            <a href="dashboard_admin.php" class="enlace activo"><?php echo $t['admin']; ?></a>
            <div class="tgs-idioma">
                <a href="?lang=es" class="<?php echo $lang == 'es' ? 'activo' : ''; ?>">ES</a>
                <a href="?lang=en" class="<?php echo $lang == 'en' ? 'activo' : ''; ?>">EN</a>
            </div>
            <span class="usuario"><?php echo $t['hello']; ?></span>
            <a href="logout.php" class="tgs-btn peligro chico"><?php echo $t['signout']; ?></a>
        </nav>
    </header>

    <main class="tgs-contenedor">
        <div class="tgs-titulo">
            <h2><?php echo $t['titulo']; ?></h2>
            <p><?php echo $t['ayuda_taller'] ?? ($lang == 'en' ? 'Set the contact details, the workshop capacity and the opening hours.' : 'Carga los datos de contacto, la capacidad y el horario de atención de la sede.'); ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="tgs-aviso"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="tgs-aviso error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="tgs-tarjeta">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <?php if ($es_edicion): ?>
                    <input type="hidden" name="id_taller" value="<?php echo (int) $id_taller; ?>">
                <?php endif; ?>

                <div class="tgs-rejilla-campos">
                    <div class="tgs-campo">
                        <label for="nombre"><?php echo $t['lbl_nombre']; ?></label>
                        <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($taller['nombre'] ?? ''); ?>">
                    </div>
                    <div class="tgs-campo">
                        <label for="telefono"><?php echo $t['lbl_telefono']; ?></label>
                        <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($taller['telefono'] ?? ''); ?>">
                    </div>
                    <div class="tgs-campo">
                        <label for="email"><?php echo $t['lbl_email']; ?></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($taller['email'] ?? ''); ?>">
                    </div>
                    <div class="tgs-campo">
                        <label for="capacidad"><?php echo $t['lbl_capacidad']; ?></label>
                        <input type="number" id="capacidad" name="capacidad" min="1" max="20" value="<?php echo htmlspecialchars($taller['capacidad'] ?? 1); ?>">
                        <span class="ayuda"><?php echo $t['lbl_capacidad_help']; ?></span>
                    </div>
                </div>

                <div class="tgs-campo">
                    <label for="direccion"><?php echo $t['lbl_direccion']; ?></label>
                    <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($taller['direccion'] ?? ''); ?>">
                </div>

                <div class="tgs-campo">
                    <label for="mapa"><?php echo $t['lbl_mapa']; ?></label>
                    <textarea id="mapa" name="mapa" rows="3"><?php echo htmlspecialchars($taller['mapa'] ?? ''); ?></textarea>
                    <span class="ayuda"><?php echo $t['lbl_mapa_help']; ?></span>
                </div>

                <!-- Horario de atencion: una fila por dia de la semana -->
                <h3 style="font-size: 15px; color: var(--tgs-acento); margin: 22px 0 10px;"><?php echo $t['lbl_horario']; ?></h3>
                <table class="tgs-tabla-horarios">
                    <thead>
                        <tr>
                            <th><?php echo $t['lbl_dia']; ?></th>
                            <th><?php echo $t['lbl_desde']; ?></th>
                            <th><?php echo $t['lbl_hasta']; ?></th>
                            <th><?php echo $t['lbl_abierto']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($dia = 1; $dia <= 7; $dia++):
                            $horario_dia = $items_edicion[$dia] ?? null;
                            $abierto = $horario_dia ? ((int) $horario_dia['activo'] === 1) : ($dia <= 6);
                            $desde = $horario_dia ? substr((string) $horario_dia['hora_inicio'], 0, 5) : '08:30';
                            $hasta = $horario_dia ? substr((string) $horario_dia['hora_fin'], 0, 5) : '18:30';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['dias'][$dia]); ?></td>
                                <td>
                                    <input type="time" name="horario_desde[<?php echo $dia; ?>]" value="<?php echo $desde; ?>">
                                </td>
                                <td>
                                    <input type="time" name="horario_hasta[<?php echo $dia; ?>]" value="<?php echo $hasta; ?>">
                                </td>
                                <td>
                                    <input type="checkbox" name="horario_activo[]" value="<?php echo $dia; ?>" <?php echo $abierto ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <div class="tgs-acciones" style="margin-top: 20px;">
                    <button type="submit" class="tgs-btn"><?php echo $t['guardar']; ?></button>
                    <a href="administrar_talleres.php" class="tgs-btn neutro"><?php echo $t['volver']; ?></a>
                </div>
            </form>
        </div>
    </main>

    <footer class="tgs-pie">
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>

</body>
</html>
