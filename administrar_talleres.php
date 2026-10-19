<?php
/**
 * TGS - Taller Global de Servicios
 * Gestion de talleres registrados.
 *
 * Lista los talleres en tarjetas con sus datos de contacto y mapa, permite
 * eliminar uno y enlaza con las paginas de alta y edicion.
 *
 * Acceso: solo administradores y gestores (verificarRol).
 *
 * Depende de: auth.php (sesion y permisos) y config.php (conexion mysqli via
 * tgs_mysqli). El alta y la edicion viven en agregar_taller.php y editar_taller.php.
 */

// Autorizacion y configuracion centralizada en vez de credenciales fijas.
require_once 'auth.php';
require_once __DIR__ . '/config.php';
verificarPermiso('administrar_talleres');

// Carga el idioma segun la URL o la sesion actual. El valor se restringe a
// 'es'/'en' igual que en el resto del sitio: cualquier otro valor dejaria a
// $traducciones[$lang] sin definir y la pagina mostraria etiquetas vacias.
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'en' ? 'en' : 'es';
}
$lang = isset($_SESSION['lang']) ? ($_SESSION['lang'] === 'en' ? 'en' : 'es') : 'es';

// Traducciones del panel para Español e Inglés.
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
        'gestion' => 'Gestión y Ubicación de Talleres',
        'nuevo' => 'Añadir Nuevo Taller',
        'telefono' => 'Teléfono',
        'correo' => 'Correo',
        'direccion' => 'Dirección',
        'editar' => 'Editar',
        'eliminar' => 'Eliminar',
        'sin_correo' => 'Sin correo',
        'sin_mapa' => 'No hay un mapa configurado para este taller.',
        'no_talleres' => 'No hay talleres registrados actualmente.',
        'volver' => 'Volver al Panel',
        'buscar' => 'Buscar taller',
        'buscar_placeholder' => 'Nombre, dirección, correo o teléfono',
        'limpiar_filtros' => 'Limpiar filtros',
        'sin_resultados' => 'No hay talleres que coincidan con la búsqueda actual.',
        'capacidad' => 'Capacidad simultánea',
        'horario' => 'Horario',
        'sin_horario' => 'Sin horario configurado',
        'resumen_horario' => '',
        'dias' => [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom']
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
        'gestion' => 'Workshop Management & Location',
        'nuevo' => 'Add New Workshop',
        'telefono' => 'Phone',
        'correo' => 'Email',
        'direccion' => 'Address',
        'editar' => 'Edit',
        'eliminar' => 'Delete',
        'sin_correo' => 'No email',
        'sin_mapa' => 'No map configured for this workshop.',
        'no_talleres' => 'No workshops registered currently.',
        'volver' => 'Back to Panel',
        'buscar' => 'Search workshop',
        'buscar_placeholder' => 'Name, address, email or phone',
        'limpiar_filtros' => 'Clear filters',
        'sin_resultados' => 'No workshops match the current search.',
        'capacidad' => 'Simultaneous capacity',
        'horario' => 'Opening hours',
        'sin_horario' => 'No opening hours configured',
        'resumen_horario' => '',
        'dias' => [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']
    ]
];
$t = $traducciones[$lang];

// Conexión a la base de datos usando mysqli.
$conexion = tgs_mysqli();
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Elimina un taller cuando la URL trae ?eliminar=ID. Es una operacion por GET
// sin token de confirmacion (ver nota de seguridad en el informe). El id se
// acota con intval(), asi que el valor no puede inyectar SQL.
if (isset($_GET['eliminar']) && tienePermiso('eliminar_taller')) {
    $id_eliminar = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM taller WHERE id_taller = $id_eliminar");
    header("Location: administrar_talleres.php");
    exit();
}

// Filtro de busqueda por texto (nombre, direccion, correo o telefono). Llega
// por GET y se escapa para que los comodines % y _ se busquen literales.
$filtro_texto = trim($_GET['q'] ?? '');

// Consulta los talleres para mostrarlos en tarjetas, aplicando el filtro.
// Sin filtro se listan todos los talleres registrados, incluido el mapa.
if ($filtro_texto !== '') {
    $termino_buscado = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtro_texto) . '%';
    $consulta = $conexion->prepare("SELECT * FROM taller WHERE nombre LIKE ? ESCAPE '!' OR direccion LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR telefono LIKE ? ESCAPE '!'");
    $consulta->bind_param('ssss', $termino_buscado, $termino_buscado, $termino_buscado, $termino_buscado);
    $consulta->execute();
    $resultado = $consulta->get_result();
} else {
    $resultado = $conexion->query("SELECT * FROM taller");
}
$total_talleres = $resultado ? $resultado->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['gestion']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(rgba(2, 7, 14, 0.74), rgba(2, 7, 14, 0.86)), url('https://images.unsplash.com/photo-1625047509248-ec889cbff17f?q=80&w=1920&auto=format&fit=crop') center/cover fixed; color: #f8fafc; min-height: 100vh; }
        .navbar-custom { background: rgba(5, 11, 22, 0.96); border-bottom: 1px solid rgba(56, 201, 245, 0.24); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2); }
        .nav-link-custom { color: #adb5bd; text-decoration: none; font-size: 0.95rem; transition: color 0.2s; }
        .nav-link-custom:hover { color: #8ee8ff; }
        .navbar-custom .navbar-brand { color: #9be7ff !important; font-size: 20px; letter-spacing: 0; }
        .navbar-custom .btn { padding: 6px 12px; font-size: 13px; border-radius: 6px !important; }
        .navbar-custom .btn-group .btn-dark { background: rgba(3, 9, 18, 0.88) !important; color: #cbd5e1 !important; border-color: #475569 !important; }
        .navbar-custom .btn-group .btn-primary { background: #38bdf8 !important; color: #0f172a !important; border-color: #38bdf8 !important; }
        .navbar-custom .btn-danger { background: #ef4444 !important; }
        .page-heading { border-bottom: 1px solid rgba(71, 112, 145, 0.62); padding-bottom: 18px; }
        .page-heading h3 { color: #9be7ff !important; letter-spacing: 0.01em; }
        .page-heading p { color: #94a3b8; margin: 5px 0 0; font-size: 0.88rem; }
        .workshop-count { color: #8ee8ff; font-size: 0.8rem; font-weight: 600; border: 1px solid rgba(56, 201, 245, 0.3); background: rgba(56, 201, 245, 0.08); border-radius: 999px; padding: 6px 11px; white-space: nowrap; }
        .page-heading .btn { padding: 10px 16px; border-radius: 8px !important; }
        .card-custom { background: linear-gradient(160deg, rgba(11, 22, 40, 0.96), rgba(3, 9, 18, 0.94)); border: 1px solid rgba(71, 112, 145, 0.62); color: #ffffff; border-radius: 14px; transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s; }
        .card-custom:hover { border-color: #38c9f5; transform: translateY(-3px); box-shadow: 0 12px 26px rgba(0, 0, 0, 0.22); }
        .card-custom::before { content: ''; display: block; height: 3px; width: 48px; margin: -16px 0 18px; background: #38c9f5; border-radius: 4px; }
        .workshop-name { color: #9be7ff; font-size: 1.2rem; letter-spacing: 0.01em; max-width: 70%; }
        .workshop-data { display: grid; gap: 8px; margin-bottom: 20px; padding: 14px 15px; background: rgba(3, 9, 18, 0.42); border: 1px solid rgba(71, 112, 145, 0.36); border-radius: 9px; }
        .workshop-data p { margin: 0; color: #cbd5e1; font-size: 0.9rem; overflow-wrap: anywhere; }
        .workshop-data strong { color: #8ee8ff; font-weight: 600; }
        .map-label { color: #8ee8ff; font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; font-weight: 600; }
        .map-container { 
            border-radius: 10px; 
            overflow: hidden; 
            border: 1px solid rgba(71, 112, 145, 0.62); 
            height: 280px; 
            position: relative;
            background: #050b16;
            box-shadow: inset 0 0 0 1px rgba(56, 201, 245, 0.08);
        }
        /* Asegura que el iframe ocupe todo el espacio y permita moverse en él */
        .map-container iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }
        .empty-workshops { background: linear-gradient(160deg, #0b1628, #060d18); border: 1px solid rgba(71, 112, 145, 0.62); border-radius: 10px; }
        .filtros-bar { display: flex; gap: 10px; align-items: center; }
        .filtros-bar .form-control { background: rgba(3, 9, 18, 0.88); border: 1px solid rgba(71, 112, 145, 0.62); color: #f8fafc; }
        .filtros-bar .form-control:focus { background: rgba(3, 9, 18, 0.95); color: #f8fafc; border-color: #38bdf8; }
        @media (max-width: 768px) {
            .navbar-custom { padding-left: 16px !important; padding-right: 16px !important; }
            .navbar-custom .container-fluid { gap: 14px; flex-direction: column; align-items: stretch; }
            .navbar-custom .navbar-brand { text-align: center; width: 100%; }
            .navbar-custom .d-flex { justify-content: center; flex-wrap: wrap; gap: 8px !important; width: 100%; }
            .navbar-custom .d-flex > a,
            .navbar-custom .d-flex > .btn-group,
            .navbar-custom .d-flex > span { margin: 0 !important; }
            .navbar-custom .d-flex > a.nav-link-custom { padding: 6px 8px; font-size: 0.82rem; }
            .navbar-custom .d-flex > .btn,
            .navbar-custom .d-flex > .btn-group .btn { min-height: 36px; }
            .navbar-custom .d-flex > span { font-size: 0.78rem; padding: 6px 4px; }
            .page-heading { align-items: flex-start !important; flex-direction: column; gap: 14px; }
            .page-heading .btn { width: 100%; }
            .card-custom { padding: 22px !important; }
        }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>

    <!-- Barra de navegación de administración de talleres -->
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
        <div class="d-flex justify-content-between align-items-center mb-4 page-heading">
            <div>
                <h3 class="text-light fw-bold mb-0"><?php echo $t['gestion']; ?></h3>
                <p><?php echo $lang == 'en' ? 'Review contact details and location for every registered workshop.' : 'Revisa los datos de contacto y la ubicación de cada taller registrado.'; ?></p>
            </div>
            <span class="workshop-count"><?php echo $total_talleres; ?> <?php echo $lang == 'en' ? ($total_talleres == 1 ? 'workshop' : 'workshops') : ($total_talleres == 1 ? 'taller' : 'talleres'); ?></span>
            <a href="agregar_taller.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> <?php echo $t['nuevo']; ?>
            </a>
        </div>

        <!-- Filtro de busqueda: se envia por GET para conservar el filtro al
             recargar o compartir el enlace. -->
        <form action="administrar_talleres.php" method="GET" class="filtros-bar mb-4">
            <input type="text" name="q" class="form-control" maxlength="150" value="<?php echo htmlspecialchars($filtro_texto); ?>" placeholder="<?php echo htmlspecialchars($t['buscar_placeholder']); ?>">
            <button type="submit" class="btn btn-primary px-3 fw-semibold">
                <i class="bi bi-search"></i> <?php echo $t['buscar']; ?>
            </button>
            <?php if ($filtro_texto !== ''): ?>
                <a href="administrar_talleres.php" class="btn btn-outline-light"><?php echo $t['limpiar_filtros']; ?></a>
            <?php endif; ?>
        </form>

        <!-- Muestra una tarjeta independiente por cada taller registrado -->
        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <div class="row g-4">
                <?php while($row = $resultado->fetch_assoc()): ?>
                    <?php $id_t = $row['id_taller']; ?>
                    <?php
                    // Resumen del horario semanal de la sede para mostrarlo en la tarjeta.
                    $resumen_horario = $t['sin_horario'];
                    if (!empty($horarios_por_taller[$id_t])) {
                        $resumen_horario = $t['resumen_horario'];
                        foreach ($horarios_por_taller[$id_t] as $dia_horario => $datos_horario) {
                            $resumen_horario .= ' ' . $t['dias'][$dia_horario] . ' ' . substr((string) $datos_horario['hora_inicio'], 0, 5) . '-' . substr((string) $datos_horario['hora_fin'], 0, 5);
                        }
                    }
// Lista los talleres con su horario semanal, para poder mostrarlo en las
// tarjetas y en la gestion.
$horarios_por_taller = [];
$stmt_horarios = $conexion->prepare('SELECT id_taller, dia_semana, hora_inicio, hora_fin, activo FROM taller_horario ORDER BY dia_semana');
$stmt_horarios->execute();
foreach ($stmt_horarios->get_result()->fetch_all(MYSQLI_ASSOC) as $fila_horario) {
    if ((int) $fila_horario['activo'] !== 1) {
        continue;
    }
    $horarios_por_taller[(int) $fila_horario['id_taller']][(int) $fila_horario['dia_semana']] = $fila_horario;
}
$stmt_horarios->close();
?>

                    <div class="col-lg-6">
                        <div class="card card-custom shadow h-100 p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h4 class="workshop-name fw-bold mb-0">
                                    <?php echo htmlspecialchars($row['nombre']); ?>
                                </h4>
                                <div class="btn-group flex-shrink-0">
                                    <a href="editar_taller.php?id=<?php echo $id_t; ?>" class="btn btn-sm btn-primary" title="<?php echo $t['editar']; ?>" style="padding: 7px 10px;">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="administrar_talleres.php?eliminar=<?php echo $id_t; ?>" class="btn btn-sm btn-danger" title="<?php echo $t['eliminar']; ?>" style="padding: 7px 10px;" onclick="return confirm('¿Estás seguro de eliminar este taller?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="workshop-data">
                                <p class="text-muted small">ID: #<?php echo $id_t; ?></p>
                                <p><strong><?php echo $t['telefono']; ?>:</strong> <?php echo htmlspecialchars($row['telefono'] ?? ''); ?></p>
                                <p><strong><?php echo $t['correo']; ?>:</strong> <?php echo htmlspecialchars($row['email'] ?? $t['sin_correo']); ?></p>
                                <p><strong><?php echo $t['direccion']; ?>:</strong> <?php echo htmlspecialchars($row['direccion'] ?? ''); ?></p>
                                <p><strong><?php echo $t['capacidad']; ?>:</strong> <?php echo htmlspecialchars($row['capacidad'] ?? 1); ?></p>
                                <p><strong><?php echo $t['horario']; ?>:</strong> <?php echo htmlspecialchars($resumen_horario); ?></p>
                            </div>

                            <div class="map-label">Ubicación en el mapa</div>
                            <div class="map-container mt-auto">
                                <?php // El mapa se guarda como HTML (iframe) cargado por el admin, por eso se imprime tal cual (ver nota de seguridad).
                                if (!empty($row['mapa'])): ?>
                                    <?php echo $row['mapa']; ?>
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted bg-dark">
                                        <?php echo $t['sin_mapa']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="card card-custom empty-workshops p-5 text-center">
                <p class="text-muted mb-0"><?php echo $filtro_texto !== '' ? $t['sin_resultados'] : $t['no_talleres']; ?></p>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="dashboard_admin.php" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> <?php echo $t['volver']; ?>
            </a>
        </div>
    </div>

</body>
</html>
