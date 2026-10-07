<?php
/**
 * TGS - Taller Global de Servicios
 * Pagina principal publica.
 *
 * Muestra la portada institucional, los servicios destacados y la ubicacion
 * de los talleres registrados. Es la puerta de entrada al sitio: segun el
 * estado de sesion y el rol decide que accesos mostrar (login, mis turnos,
 * panel gestor o panel administrador).
 *
 * Acceso: publico, tanto para visitantes anonimos como para usuarios logueados.
 *
 * Depende de: config.php (entorno y conexion mysqli a la base de datos) y de
 * los archivos de textos lang/es.php o lang/en.php.
 */

// Inicia la sesión para guardar usuario, rol e idioma entre peticiones.
session_start();

// Carga la configuracion centralizada (.env + .env.local).
require_once __DIR__ . '/config.php';

// Si la URL trae ?lang=en o ?lang=es, guarda ese idioma en la sesión.
// Solo se aceptan esos dos valores: cualquier otra cosa cae en español.
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] == 'en') ? 'en' : 'es';
}

// Determina el idioma actual, por defecto español.
$idioma_actual = $_SESSION['lang'] ?? 'es';

// Carga el archivo de textos del idioma correspondiente.
if ($idioma_actual == 'en') {
    include_once 'lang/en.php';
} else {
    include_once 'lang/es.php';
}

// La sesion se considera iniciada si existe cedula (usuarios de la base vieja).
$sesion_iniciada = isset($_SESSION['cedula']);

// Guarda el nombre del usuario si existe en la sesión.
$nombre_usuario = $_SESSION['nombre'] ?? '';

// Normaliza el rol para compararlo sin depender de mayusculas ni espacios.
// El administrador ve todo; gestor y admin comparten el panel gestor.
$rol_usuario = strtolower(trim($_SESSION['rol'] ?? ''));
$es_admin = $rol_usuario === 'admin';
$puede_ver_panel_gestor = in_array($rol_usuario, ['admin', 'gestor'], true);

// Lista los talleres para pintar su direccion y mapa reales en la portada.
// El orden DESC deja primero los talleres mas recientes.
$conexion_talleres = tgs_mysqli();
$talleres = [];
if (!$conexion_talleres->connect_error) {
    $resultado_talleres = $conexion_talleres->query('SELECT * FROM taller ORDER BY id_taller DESC');
    if ($resultado_talleres) {
        $talleres = $resultado_talleres->fetch_all(MYSQLI_ASSOC);
    }
    $conexion_talleres->close();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_actual; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    <title>TGS - <?php echo $lang['hero_titulo']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; scroll-behavior: smooth; }
        body { 
            background: linear-gradient(rgba(2, 7, 14, 0.28), rgba(2, 7, 14, 0.7)), url('https://images.unsplash.com/photo-1625047509248-ec889cbff17f?q=80&w=1920&auto=format&fit=crop') no-repeat center center fixed; 
            background-size: cover;
            background-position: center 58%;
            color: #f8fafc; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
        }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 28px; display: flex; justify-content: space-between; align-items: center; width: 100%; position: fixed; top: 0; z-index: 1000; gap: 10px; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 10px; align-items: center; white-space: nowrap; }
        nav span { color: #94a3b8; font-size: 14px; }
        .btn-top-gestor {
            background-color: #0ea5e9;
            color: white;
            padding: 7px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-top-gestor:hover { background-color: #0284c7; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(14, 165, 233, 0.24); }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav a.nav-link:hover { color: #38bdf8; }
        
        /* Estilo para el botón de Administración Global */
        nav a.btn-admin { background-color: #0284c7; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.3s; }
        nav a.btn-admin:hover { background-color: #0369a1; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(2, 132, 199, 0.22); }

        nav a.btn-login { background-color: #2563eb; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.3s; }
        nav a.btn-login:hover { background-color: #1d4ed8; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(37, 99, 235, 0.22); }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.3s; }
        nav a.btn-logout:hover { background-color: #dc2626; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(239, 68, 68, 0.22); }
        .lang-selector { display: flex; gap: 5px; background: rgba(15, 23, 42, 0.9); padding: 4px; border-radius: 6px; border: 1px solid #475569; }
        .lang-btn { padding: 4px 8px; font-size: 12px; font-weight: bold; text-decoration: none; border-radius: 4px; color: #94a3b8; }
        .lang-btn.active { background-color: #38bdf8; color: #0f172a; }
        .hero { padding: 180px 20px 100px 20px; text-align: center; max-width: 900px; margin: 0 auto; }
        .hero h1 { font-size: 42px; color: #9be7ff; margin-bottom: 20px; font-weight: 700; }
        .hero p { font-size: 18px; color: #94a3b8; margin-bottom: 30px; line-height: 1.6; }
        .hero-buttons { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .info-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-top: 32px;
            width: 100%;
        }
        .info-pill {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 14px;
            padding: 20px 18px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .info-pill span {
            display: block;
            color: #38bdf8;
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .info-pill strong {
            display: block;
            font-size: 14px;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .info-pill small {
            font-size: 12px;
            color: #cbd5e1;
            line-height: 1.5;
        }
        .btn-main { background-color: #2563eb; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 16px; font-weight: 600; transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease; }
        .btn-main:hover { background-color: #1d4ed8; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(37, 99, 235, 0.26); }
        .btn-gestor {
            background-color: #0ea5e9;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-gestor:hover { background-color: #0284c7; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(14, 165, 233, 0.24); }
        .container { max-width: 1200px; margin: 0 auto; padding: 80px 20px; width: 100%; }
        .section-title { font-size: 28px; color: #38bdf8; text-align: center; margin-bottom: 50px; font-weight: 600; }
        .showcase {
            display: grid;
            grid-template-columns: 1.4fr 0.8fr;
            gap: 28px;
            align-items: stretch;
            margin-bottom: 50px;
        }
        .showcase-copy,
        .location-card {
            background: rgba(15, 23, 42, 0.78);
            border: 1px solid #334155;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }
        .eyebrow {
            font-size: 12px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #38bdf8;
            margin-bottom: 18px;
            font-weight: 700;
        }
        .showcase-copy h2 {
            font-size: 32px;
            color: #f8fafc;
            margin-bottom: 18px;
        }
        .showcase-copy p {
            color: #cbd5e1;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        .feature-list {
            list-style: none;
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }
        .feature-list li {
            color: #e2e8f0;
            background: rgba(30, 41, 59, 0.75);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 10px;
            padding: 12px 14px;
        }
        .location-card h3 {
            color: #38bdf8;
            margin-bottom: 12px;
            font-size: 22px;
        }
        .location-card p {
            color: #cbd5e1;
            margin-bottom: 20px;
            line-height: 1.7;
        }
        .workshop-list { display: grid; gap: 18px; }
        .workshop-item {
            background: rgba(30, 41, 59, 0.75);
            border: 1px solid #475569;
            border-radius: 12px;
            padding: 18px;
        }
        .workshop-item h3 { color: #f8fafc; font-size: 18px; margin-bottom: 12px; }
        .workshop-item p { color: #cbd5e1; font-size: 14px; margin-bottom: 8px; }
        .workshop-item strong { color: #38bdf8; }
        .workshop-map {
            height: 240px;
            margin-top: 14px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #334155;
            background: #0f172a;
        }
        .workshop-map iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }
        .map-help {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 8px;
            margin-bottom: 0;
        }
        .no-workshops { color: #94a3b8; font-size: 14px; }
        .map-box {
            border-radius: 14px;
            height: 180px;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(15, 23, 42, 0.9)), url('https://images.unsplash.com/photo-1519501025264-65ba15a82390?q=80&w=1200&auto=format&fit=crop') center/cover no-repeat;
            display: flex;
            align-items: end;
            padding: 18px;
            border: 1px solid rgba(56, 189, 248, 0.25);
            color: #f8fafc;
            font-weight: 600;
        }
        .services-heading { max-width: 650px; margin: 0 auto 28px; text-align: center; }
        .services-heading p { color: #cbd5e1; line-height: 1.7; font-size: 15px; }
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .service-card { background: linear-gradient(160deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.88)); border: 1px solid #334155; border-radius: 14px; padding: 26px 24px; transition: transform 0.3s, border-color 0.3s, box-shadow 0.3s; position: relative; overflow: hidden; }
        .service-card::before { content: ''; display: block; width: 34px; height: 4px; background: #38bdf8; border-radius: 4px; margin-bottom: 22px; }
        .service-card::after { content: ''; position: absolute; right: -30px; bottom: -45px; width: 120px; height: 120px; border: 1px solid rgba(56, 189, 248, 0.16); border-radius: 50%; }
        .service-card:hover { transform: translateY(-5px); border-color: #38bdf8; box-shadow: 0 14px 28px rgba(14, 165, 233, 0.13); }
        .service-card h3 { font-size: 19px; color: #f8fafc; margin-bottom: 12px; }
        .service-card p { font-size: 14px; color: #94a3b8; line-height: 1.7; }
        .about-section { background: linear-gradient(120deg, rgba(15, 23, 42, 0.92), rgba(17, 45, 70, 0.82)); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 16px; padding: 38px; max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 34px; align-items: center; }
        .about-copy .section-title { text-align: left; margin-bottom: 22px; }
        .about-section p { font-size: 15px; color: #cbd5e1; line-height: 1.8; margin-bottom: 16px; }
        .about-points { display: grid; gap: 10px; }
        .about-point { border-left: 3px solid #38bdf8; background: rgba(30, 41, 59, 0.65); padding: 13px 15px; color: #e2e8f0; font-size: 13px; line-height: 1.5; }
        .about-point strong { display: block; color: #f8fafc; margin-bottom: 3px; }
        footer { text-align: center; padding: 25px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }

        @media (max-width: 820px) {
            .showcase {
                grid-template-columns: 1fr;
            }
            .about-section { grid-template-columns: 1fr; padding: 28px 22px; }
            .about-copy .section-title { text-align: center; }
        }
        @media (max-width: 700px) {
            header { position: relative; padding: 14px 16px; align-items: flex-start; flex-wrap: wrap; }
            header .logo { width: 100%; text-align: center; }
            header nav { width: 100%; justify-content: center; gap: 9px; }
            nav a.nav-link { font-size: 13px; }
            nav span { font-size: 12px; }
            .hero { padding: 58px 16px 58px; }
            .hero h1 { font-size: 32px; line-height: 1.15; }
            .hero p { font-size: 16px; }
            .container { padding: 52px 16px; }
            .section-title { font-size: 24px; margin-bottom: 30px; }
            .showcase-copy,
            .location-card { padding: 24px 20px; }
            .showcase-copy h2 { font-size: 26px; }
            .services-grid { grid-template-columns: 1fr; }
            .info-strip { grid-template-columns: 1fr; gap: 12px; }
            .about-section { padding: 24px 18px; }
        }
    </style>
</head>
<body class="notranslate">
    <header>
        <a href="index.php" class="logo">TGS - Blue Tech Uruguay</a>
        <nav>
            <a href="index.php" class="nav-link"><?php echo $lang['inicio']; ?></a>
            <a href="#servicios" class="nav-link"><?php echo $lang['servicios']; ?></a>
            <a href="#sobre-nosotros" class="nav-link"><?php echo $lang['sobre_nosotros']; ?></a>
            <?php if ($sesion_iniciada): ?>
                <a href="reservas.php" class="nav-link"><?php echo $lang['mis_turnos']; ?></a>
            <?php endif; ?>

            <!-- Acceso restringido: solo el administrador ve el panel global -->
            <?php if ($es_admin): ?>
                <a href="dashboard_admin.php" class="btn-admin"><?php echo $lang['admin_panel']; ?></a>
            <?php endif; ?>
            <!-- El panel gestor lo comparten administradores y gestores -->
            <?php if ($puede_ver_panel_gestor): ?>
                <a href="dashboard_gestor.php" class="btn-top-gestor"><?php echo $lang['manager_panel']; ?></a>
            <?php endif; ?>

            <div class="lang-selector">
                <a href="index.php?lang=es" class="lang-btn <?php echo ($idioma_actual == 'es') ? 'active' : ''; ?>">ES</a>
                <a href="index.php?lang=en" class="lang-btn <?php echo ($idioma_actual == 'en') ? 'active' : ''; ?>">EN</a>
            </div>
            <?php if ($sesion_iniciada): ?>
                <span><?php echo $lang['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
                <a href="logout.php" class="btn-logout"><?php echo $lang['cerrar_sesion']; ?></a>
            <?php else: ?>
                <a href="login.php" class="btn-login"><?php echo $lang['login']; ?></a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Presentación principal del taller y acceso para reservar -->
    <section class="hero">
        <h1><?php echo $lang['hero_titulo']; ?></h1>
        <p><?php echo $lang['hero_subtitulo']; ?></p>
        <div class="hero-buttons">
            <a href="<?php echo $sesion_iniciada ? 'reservas.php' : 'login.php'; ?>" class="btn-main"><?php echo $lang['btn_agendar_ahora']; ?></a>
        </div>

        <div class="info-strip">
            <div class="info-pill">
                <span><?php echo $lang['info_label']; ?></span>
                <div>
                    <strong><?php echo $lang['workshop_location']; ?></strong>
                    <small><?php echo $lang['location_summary']; ?></small>
                </div>
            </div>
            <div class="info-pill">
                <span><?php echo $lang['availability']; ?></span>
                <div>
                    <strong><?php echo $lang['schedule']; ?></strong>
                    <small><?php echo $lang['schedule_summary']; ?></small>
                </div>
            </div>
            <div class="info-pill">
                <span><?php echo $lang['experience']; ?></span>
                <div>
                    <strong><?php echo $lang['specialists']; ?></strong>
                    <small><?php echo $lang['specialists_summary']; ?></small>
                </div>
            </div>
        </div>
    </section>

    <!-- Servicios, presentación institucional y ubicación de los talleres -->
    <div id="servicios" class="container">
        <div class="showcase">
            <div class="showcase-copy">
                <div class="eyebrow"><?php echo $lang['hero_titulo']; ?></div>
                <h2><?php echo $lang['showcase_heading']; ?></h2>
                <p><?php echo $lang['showcase_text']; ?></p>
                <ul class="feature-list">
                    <li><?php echo $lang['feature_one']; ?></li>
                    <li><?php echo $lang['feature_two']; ?></li>
                    <li><?php echo $lang['feature_three']; ?></li>
                </ul>
            </div>

            <div class="location-card">
                <h3><?php echo $lang['workshops_heading']; ?></h3>
                <p><?php echo $lang['workshops_text']; ?></p>
                <div class="workshop-list">
                    <?php if (empty($talleres)): ?>
                        <p class="no-workshops"><?php echo $lang['no_workshops']; ?></p>
                    <?php else: ?>
                        <?php // Los datos de contacto se escapan para no inyectar HTML desde la base.
                        foreach ($talleres as $taller): ?>
                            <div class="workshop-item">
                                <h3><?php echo htmlspecialchars($taller['nombre'] ?? 'TGS Workshop'); ?></h3>
                                <p><strong><?php echo $lang['address']; ?>:</strong> <?php echo htmlspecialchars($taller['direccion'] ?? $lang['no_address']); ?></p>
                                <p><strong><?php echo $lang['phone']; ?>:</strong> <?php echo htmlspecialchars($taller['telefono'] ?? $lang['no_phone']); ?></p>
                                <p><strong><?php echo $lang['email_label']; ?>:</strong> <?php echo htmlspecialchars($taller['email'] ?? $lang['no_email']); ?></p>
                                <?php // El mapa se guarda como iframe HTML ya generado, por eso se imprime sin escapar (ver nota de seguridad).
                                if (!empty($taller['mapa'])): ?>
                                    <div class="workshop-map">
                                        <?php echo $taller['mapa']; ?>
                                    </div>
                                    <p class="map-help"><?php echo $lang['map_help']; ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="services-heading">
            <h2 class="section-title" style="margin-bottom: 14px;"><?php echo $lang['titulo_servicios']; ?></h2>
            <p><?php echo $lang['services_intro']; ?></p>
        </div>
        <div class="services-grid">
            <div class="service-card">
                <h3><?php echo $lang['serv_1_titulo']; ?></h3>
                <p><?php echo $lang['serv_1_desc']; ?></p>
            </div>
            <div class="service-card">
                <h3><?php echo $lang['serv_2_titulo']; ?></h3>
                <p><?php echo $lang['serv_2_desc']; ?></p>
            </div>
            <div class="service-card">
                <h3><?php echo $lang['serv_3_titulo']; ?></h3>
                <p><?php echo $lang['serv_3_desc']; ?></p>
            </div>
        </div>
    </div>

    <div id="sobre-nosotros" class="container">
        <div class="about-section">
            <div class="about-copy">
                <h2 class="section-title"><?php echo $lang['titulo_sobre_nosotros']; ?></h2>
                <p><?php echo $lang['nosotros_texto_1']; ?></p>
                <p><?php echo $lang['nosotros_texto_2']; ?></p>
            </div>
            <div class="about-points">
                <div class="about-point">
                    <strong><?php echo $idioma_actual == 'en' ? 'Professional attention' : 'Atención profesional'; ?></strong>
                    <?php echo $idioma_actual == 'en' ? 'We listen to every need before starting the work.' : 'Escuchamos cada necesidad antes de comenzar el trabajo.'; ?>
                </div>
                <div class="about-point">
                    <strong><?php echo $idioma_actual == 'en' ? 'Clear estimates' : 'Presupuestos claros'; ?></strong>
                    <?php echo $idioma_actual == 'en' ? 'You know what your vehicle needs and why.' : 'Sabés qué necesita tu vehículo y por qué.'; ?>
                </div>
                <div class="about-point">
                    <strong><?php echo $idioma_actual == 'en' ? 'Quality work' : 'Trabajo de calidad'; ?></strong>
                    <?php echo $idioma_actual == 'en' ? 'Careful service focused on safety and reliability.' : 'Servicio cuidadoso, enfocado en seguridad y confiabilidad.'; ?>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - Blue Tech Uruguay. <?php echo $lang['footer_rights']; ?></p>
    </footer>
</body>
</html>
