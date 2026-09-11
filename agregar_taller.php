<?php
# Solo administradores y gestores pueden crear talleres
require_once 'auth.php';
verificarRol(['admin', 'gestor']);

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
        'guardar' => 'Save Workshop',
        'volver' => 'Back',
        'exito' => 'Workshop successfully added!',
        'error_db' => 'Error saving: '
    ]
];
$t = $traducciones[$lang];

# Conexión a la base de datos del sistema
$conexion = new mysqli("localhost", "root", "", "bluetech_base");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

# Variables para mensajes de éxito o error
$mensaje = "";
$error = "";

# Si el formulario fue enviado por POST, guarda el taller
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    # Escapa los valores para evitar inyección SQL
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $telefono = $conexion->real_escape_string($_POST['telefono']);
    $email = $conexion->real_escape_string($_POST['email']);
    $direccion = $conexion->real_escape_string($_POST['direccion']);
    $mapa = $conexion->real_escape_string($_POST['mapa']);

    # Insert en la tabla taller con datos del formulario
    $sql_insert = "INSERT INTO taller (nombre, telefono, email, direccion, mapa) VALUES ('$nombre', '$telefono', '$email', '$direccion', '$mapa')";

    if ($conexion->query($sql_insert) === TRUE) {
        $mensaje = $t['exito'];
    } else {
        $error = $t['error_db'] . $conexion->error;
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
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
                    <a href="?lang=es" class="btn btn-sm <?php echo $lang == 'es' ? 'btn-primary' : 'btn-dark border-secondary'; ?>">ES</a>
                    <a href="?lang=en" class="btn btn-sm <?php echo $lang == 'en' ? 'btn-primary' : 'btn-dark border-secondary'; ?>">EN</a>
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
                    <div class="card-header bg-success text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
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
                            <div class="mb-3">
                                <label for="nombre" class="form-label"><?php echo $t['lbl_nombre']; ?></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>

                            <div class="mb-3">
                                <label for="telefono" class="form-label"><?php echo $t['lbl_telefono']; ?></label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label"><?php echo $t['lbl_email']; ?></label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>

                            <div class="mb-3">
                                <label for="direccion" class="form-label"><?php echo $t['lbl_direccion']; ?></label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="2"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="mapa" class="form-label"><?php echo $t['lbl_mapa']; ?></label>
                                <textarea class="form-control" id="mapa" name="mapa" rows="3"></textarea>
                                <div class="form-text text-muted small mt-1"><?php echo $t['lbl_mapa_help']; ?></div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="submit" class="btn btn-success">
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