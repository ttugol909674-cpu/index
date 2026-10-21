<?php
###### # Presenta el panel principal y los accesos del administrador.
# Usa la protección centralizada y permite únicamente el rol admin
require_once 'auth.php';
verificarPermiso('ver_panel_admin');

# Guarda el nombre del administrador y el idioma actual para mostrar la interfaz
$nombre_usuario = $_SESSION['nombre'] ?? 'Administrador';
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'en' ? 'en' : 'es';
}
$idioma_actual = $_SESSION['lang'] ?? 'es';

# Textos traducidos del panel de administración
$texto = $idioma_actual === 'en' ? [
    'panel' => 'Admin Panel', 'inicio' => 'Back to Home', 'hola' => 'Hello', 'rol' => 'Admin', 'salir' => 'Sign Out',
    'perfil' => 'My Profile', 'pagos' => 'Manage Payments', 'presupuestos' => 'Manage Budgets', 'permisos' => 'Permission Matrix', 'notificaciones' => 'Customer Notices', 'agenda' => 'Workshop Agenda', 'datos_pago' => 'Bank Details',
    'titulo' => 'General Control Panel', 'resumen' => 'Choose an option below to manage reservations, users, or workshops registered in the database.',
    'reservas' => 'Manage Reservations', 'usuarios' => 'Manage Users', 'talleres' => 'Manage Workshops', 'servicios' => 'Manage Services',
    'marca' => 'Global Workshop Services', 'derechos' => 'All rights reserved.'
] : [
    'panel' => 'Panel Admin', 'inicio' => 'Volver al Inicio', 'hola' => 'Hola', 'rol' => 'Admin', 'salir' => 'Cerrar Sesión',
    'perfil' => 'Mi Perfil', 'pagos' => 'Administrar Pagos', 'presupuestos' => 'Administrar Presupuestos', 'permisos' => 'Matriz de Permisos', 'notificaciones' => 'Avisos al Cliente', 'agenda' => 'Agenda de Talleres', 'datos_pago' => 'Datos de Pago',
    'titulo' => 'Panel de Control General', 'resumen' => 'Seleccioná una de las opciones a continuación para administrar las reservas, los usuarios o los talleres registrados en la base de datos.',
    'reservas' => 'Administrar Reservas', 'usuarios' => 'Administrar Usuarios', 'talleres' => 'Administrar Talleres', 'servicios' => 'Administrar Servicios',
    'marca' => 'Taller Global de Servicios', 'derechos' => 'Todos los derechos reservados.'
];
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_actual; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TGS - <?php echo $texto['panel']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; width: 100%; z-index: 1000; flex-wrap: wrap; gap: 15px; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        nav span { color: #94a3b8; font-size: 14px; }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.3s; }
        nav a.btn-logout:hover { background-color: #dc2626; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; }
        .language-selector a:hover, .language-selector a.active { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        
        .main-container { flex: 1; padding: 40px 20px; max-width: 1200px; width: 100%; margin: 0 auto; }
        .welcome-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); margin-bottom: 30px; }
        .welcome-card h2 { font-size: 24px; color: #38bdf8; margin-bottom: 10px; }
        .welcome-card p { color: #94a3b8; font-size: 14px; }

        .admin-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .admin-card-btn {
            background-color: rgba(30, 41, 59, 0.95);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            color: #f8fafc;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            transition: transform 0.2s, border-color 0.2s, background-color 0.2s;
        }
        .admin-card-btn:hover {
            transform: translateY(-4px);
            border-color: #38bdf8;
            background-color: rgba(30, 41, 59, 1);
        }
        .admin-card-btn i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: block;
            color: #38bdf8;
        }
        .admin-card-btn span {
            font-size: 1.2rem;
            font-weight: bold;
            color: #fff;
        }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <header>
        <a href="dashboard_admin.php" class="logo">TGS - <?php echo $texto['panel']; ?></a>
        <nav>
            <!-- Botón para volver al inicio del sitio -->
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> <?php echo $texto['inicio']; ?></a>
            <a href="dashboard_admin.php" class="nav-link" style="color: #38bdf8; font-weight: bold;"><?php echo $texto['panel']; ?></a>
<a href="perfil.php" class="nav-link"><?php echo $texto['perfil']; ?></a>
            <!-- Selector para cambiar el idioma del panel -->
            <div class="language-selector" aria-label="Language selector">
                <a href="dashboard_admin.php?lang=es" class="<?php echo $idioma_actual === 'es' ? 'active' : ''; ?>">ES</a>
                <a href="dashboard_admin.php?lang=en" class="<?php echo $idioma_actual === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong> <span style="font-size: 11px; color: #38bdf8;">(<?php echo $texto['rol']; ?>)</span></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <div class="main-container">
        <!-- Resumen general de las herramientas disponibles para el administrador -->
        <div class="welcome-card">
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <!-- Accesos a reservas, usuarios, talleres y servicios -->
        <div class="admin-buttons-grid">
            <!-- Botón Administrar Reservas -->
            <a href="administrar_reservas.php" class="admin-card-btn">
                <i class="fas fa-calendar-check"></i>
                <span><?php echo $texto['reservas']; ?></span>
            </a>

            <!-- Botón Administrar Usuarios -->
            <a href="administrar_usuarios.php" class="admin-card-btn">
                <i class="fas fa-users-cog"></i>
                <span><?php echo $texto['usuarios']; ?></span>
            </a>

            <!-- Botón Administrar Talleres -->
            <a href="administrar_talleres.php" class="admin-card-btn">
                <i class="fas fa-tools"></i>
                <span><?php echo $texto['talleres']; ?></span>
            </a>

            <!-- Botón para ver la agenda diaria de los talleres -->
            <a href="agenda_gestor.php" class="admin-card-btn">
                <i class="fas fa-calendar-days"></i>
                <span><?php echo $texto['agenda']; ?></span>
            </a>

            <!-- Botón para cargar los datos bancarios que ve el cliente -->
            <a href="datos_pago.php" class="admin-card-btn">
                <i class="fas fa-qrcode"></i>
                <span><?php echo $texto['datos_pago']; ?></span>
            </a>

            <!-- Botón para ver los avisos enviados a los clientes -->
            <a href="notificaciones.php" class="admin-card-btn">
                <i class="fas fa-envelope-open-text"></i>
                <span><?php echo $texto['notificaciones']; ?></span>
            </a>

            <!-- Botón para ver la matriz de permisos por rol -->
            <?php if (tienePermiso('editar_matriz_permisos')): ?>
                <a href="permisos.php" class="admin-card-btn">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo $texto['permisos']; ?></span>
                </a>
            <?php endif; ?>

            <!-- Botón para administrar los presupuestos -->
            <a href="administrar_presupuestos.php" class="admin-card-btn">
                <i class="fas fa-file-invoice-dollar"></i>
                <span><?php echo $texto['presupuestos']; ?></span>
            </a>

            <!-- Botón para administrar los cobros y la facturación -->
            <a href="administrar_pagos.php" class="admin-card-btn">
                <i class="fas fa-cash-register"></i>
                <span><?php echo $texto['pagos']; ?></span>
            </a>

            <!-- Botón para administrar el catálogo de servicios -->
            <a href="administrar_servicios.php" class="admin-card-btn">
                <i class="fas fa-wrench"></i>
                <span><?php echo $texto['servicios']; ?></span>
            </a>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $texto['marca']; ?>. <?php echo $texto['derechos']; ?></p>
    </footer>
</body>
</html>
