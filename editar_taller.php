<?php
# Solo administradores y gestores pueden editar talleres
require_once 'auth.php';
verificarRol(['admin', 'gestor']);

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
        'guardar' => 'Save Changes',
        'volver' => 'Back',
        'exito' => 'Workshop and location successfully updated!',
        'error_db' => 'Error updating: '
    ]
];
$t = $traducciones[$lang];

# Conexión a la base de datos
$conexion = new mysqli("localhost", "root", "", "bluetech_base");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

# Variables para mostrar mensajes de éxito o error
$mensaje = "";
$error = "";

# Si el formulario fue enviado por POST, actualiza el taller
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    # Escapa los valores para prevenir inyección SQL
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $telefono = $conexion->real_escape_string($_POST['telefono']);
    $email = $conexion->real_escape_string($_POST['email']);
    $direccion = $conexion->real_escape_string($_POST['direccion']);

    # Se permite el iframe del mapa para mostrar la ubicación exacta
    $mapa = $conexion->real_escape_string($_POST['mapa']);

    # Actualiza los datos del taller en la base de datos
    $sql_update = "UPDATE taller SET nombre='$nombre', telefono='$telefono', email='$email', direccion='$direccion', mapa='$mapa' LIMIT 1";

    if ($conexion->query($sql_update) === TRUE) {
        $mensaje = $t['exito'];
    } else {
        $error = $t['error_db'] . $conexion->error;
    }
}

# Consulta el taller para cargar sus datos en el formulario
$resultado = $conexion->query("SELECT * FROM taller LIMIT 1");
$taller = ($resultado && $resultado->num_rows > 0) ? $resultado->fetch_assoc() : die("No hay talleres registrados.");
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