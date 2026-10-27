<?php
/**
 * TGS - Taller Global de Servicios
 * Cabecera compartida de las pantallas internas.
 *
 * Todas las paginas internas del sitio usan esta cabecera, para que se
 * vean igual: logo a la izquierda y, a la derecha, la navegacion de las
 * pantallas del rol, el selector de idioma, el saludo y el boton de
 * salir.
 *
 * Variables que la pagina puede definir ANTES de incluir este archivo:
 *   $tgs_volver    -> url del enlace "Volver" (si se deja vacio, no sale)
 *   $tgs_activo    -> clave del enlace que queda resaltado
 *   $tgs_extra_nav -> array de enlaces extra: array(array('url'=>..,'texto'=>..))
 *
 * Variables que se usan de la pagina: $lang_code, $nombre_usuario.
 * Si la pagina no trae $texto, se usan los textos de aqui.
 */

$tgs_idioma = isset($lang_code) ? $lang_code : (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'es');
if ($tgs_idioma !== 'en') {
    $tgs_idioma = 'es';
}

$tgs_textos = array(
    'es' => array(
        'hola' => 'Hola', 'salir' => 'Cerrar Sesión', 'volver' => 'Volver', 'inicio' => 'Inicio',
        'turnos' => 'Mis turnos', 'presupuestos' => 'Mis presupuestos', 'pagos' => 'Mis pagos',
        'historial' => 'Historial de servicios', 'perfil' => 'Mi perfil', 'panel' => 'Panel',
        'agenda' => 'Agenda', 'reservas_admin' => 'Turnos', 'talleres' => 'Talleres',
        'servicios' => 'Servicios', 'usuarios' => 'Usuarios', 'avisos' => 'Avisos',
        'permisos' => 'Permisos', 'datos_pago' => 'Datos de pago', 'nuevo_taller' => 'Nuevo taller',
    ),
    'en' => array(
        'hola' => 'Hello', 'salir' => 'Sign Out', 'volver' => 'Back', 'inicio' => 'Home',
        'turnos' => 'My appointments', 'presupuestos' => 'My budgets', 'pagos' => 'My payments',
        'historial' => 'Service history', 'perfil' => 'My profile', 'panel' => 'Panel',
        'agenda' => 'Agenda', 'reservas_admin' => 'Appointments', 'talleres' => 'Workshops',
        'servicios' => 'Services', 'usuarios' => 'Users', 'avisos' => 'Notices',
        'permisos' => 'Permissions', 'datos_pago' => 'Bank details', 'nuevo_taller' => 'New workshop',
    ),
);
$tgs_txt = $tgs_textos[$tgs_idioma];

// Si la pagina trae sus propios textos, se respetan (por ejemplo, el
// nombre del proyecto o el saludo ya traducido).
if (!empty($texto) && is_array($texto)) {
    foreach (array('hola', 'salir', 'volver') as $clave) {
        if (!empty($texto[$clave])) {
            $tgs_txt[$clave] = $texto[$clave];
        }
    }
}

$tgs_usuario = isset($nombre_usuario) ? $nombre_usuario : (isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '');

// Rol del visitante: define que pantallas aparecen en la navegacion.
$tgs_rol = function_exists('rolActual') ? rolActual() : 'cliente';

// El enlace "Volver" puede venir de la pagina ($tgs_volver) o ser el
// que la pagina ya calculo ($panel_volver).
if (!isset($tgs_volver) && isset($panel_volver)) {
    $tgs_volver = $panel_volver;
}
$tgs_volver = isset($tgs_volver) ? (string) $tgs_volver : '';

$tgs_enlaces = array();
if ($tgs_rol === 'cliente') {
    $tgs_enlaces = array(
        'turnos'      => array('reservas.php', $tgs_txt['turnos']),
        'presupuestos' => array('presupuestos.php', $tgs_txt['presupuestos']),
        'pagos'       => array('pagos_cliente.php', $tgs_txt['pagos']),
        'historial'   => array('historial_cliente.php', $tgs_txt['historial']),
        'perfil'      => array('perfil.php', $tgs_txt['perfil']),
    );
} elseif ($tgs_rol === 'gestor') {
    $tgs_enlaces = array(
        'panel'        => array('dashboard_gestor.php', $tgs_txt['panel']),
        'agenda'       => array('agenda_gestor.php', $tgs_txt['agenda']),
        'reservas_admin' => array('administrar_reservas.php', $tgs_txt['reservas_admin']),
        'pagos'       => array('administrar_pagos.php', $tgs_txt['pagos']),
        'talleres'    => array('administrar_talleres.php', $tgs_txt['talleres']),
        'perfil'      => array('perfil.php', $tgs_txt['perfil']),
    );
} else {
    $tgs_enlaces = array(
        'panel'        => array('dashboard_admin.php', $tgs_txt['panel']),
        'reservas_admin' => array('administrar_reservas.php', $tgs_txt['reservas_admin']),
        'pagos'       => array('administrar_pagos.php', $tgs_txt['pagos']),
        'presupuestos' => array('administrar_presupuestos.php', $tgs_txt['presupuestos']),
        'talleres'    => array('administrar_talleres.php', $tgs_txt['talleres']),
        'usuarios'    => array('administrar_usuarios.php', $tgs_txt['usuarios']),
        'perfil'      => array('perfil.php', $tgs_txt['perfil']),
    );
}

// La navegacion de la pagina actual se resalta. Se deduce del nombre
// del archivo que se esta ejecutando.
$tgs_actual = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
$tgs_activo = isset($tgs_activo) ? $tgs_activo : '';

// Enlaces extra que la pagina quiera anadir (opcional).
$tgs_extra = isset($tgs_extra_nav) && is_array($tgs_extra_nav) ? $tgs_extra_nav : array();

/**
 * URL de la pagina actual con el idioma pedido. Se conservan los demas
 * parametros (por ejemplo, el id de una reserva).
 */
if (!function_exists('tgs_url_idioma')) {
    function tgs_url_idioma(string $idioma): string
    {
        $script = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'index.php');
        $query = $_GET;
        $query['lang'] = $idioma;
        return $script . '?' . http_build_query($query);
    }
}
?>
<header>
    <a href="index.php" class="logo">TGS - Blue Tech Uruguay</a>
    <nav>
        <?php if (!empty($tgs_volver)): ?>
            <a href="<?php echo htmlspecialchars($tgs_volver); ?>" class="nav-link"><?php echo $tgs_txt['volver']; ?></a>
        <?php endif; ?>

        <?php foreach ($tgs_enlaces as $clave => $enlace): ?>
            <?php if ($tgs_actual === $enlace[0]) { $tgs_activo = $clave; } ?>
            <a href="<?php echo htmlspecialchars($enlace[0]); ?>" class="nav-link<?php echo $tgs_activo === $clave ? ' activo' : ''; ?>"><?php echo htmlspecialchars($enlace[1]); ?></a>
        <?php endforeach; ?>

        <?php foreach ($tgs_extra as $enlace): ?>
            <a href="<?php echo htmlspecialchars($enlace['url']); ?>" class="nav-link<?php echo !empty($enlace['activo']) ? ' activo' : ''; ?>"><?php echo htmlspecialchars($enlace['texto']); ?></a>
        <?php endforeach; ?>

        <div class="language-selector" aria-label="Language selector">
            <a href="<?php echo htmlspecialchars(tgs_url_idioma('es')); ?>" class="<?php echo $tgs_idioma === 'es' ? 'active' : ''; ?>">ES</a>
            <a href="<?php echo htmlspecialchars(tgs_url_idioma('en')); ?>" class="<?php echo $tgs_idioma === 'en' ? 'active' : ''; ?>">EN</a>
        </div>

        <span class="usuario"><?php echo $tgs_txt['hola']; ?>, <strong><?php echo htmlspecialchars($tgs_usuario); ?></strong></span>
        <a href="logout.php" class="btn-logout"><?php echo $tgs_txt['salir']; ?></a>
    </nav>
</header>
