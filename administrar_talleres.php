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
 * Depende de: auth.php (sesion y permisos) y config.php (conexion PDO via
 * tgs_pdo). El alta y la edicion viven en agregar_taller.php y editar_taller.php.
 */

// Autorizacion y configuracion centralizada en vez de credenciales fijas.
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/taller_disponibilidad.php';
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

// Conexión a la base de datos (PDO, la misma via que usa el resto del sitio).
$conexion = tgs_pdo();

// Elimina un taller cuando la URL trae ?eliminar=ID. Es una operacion por GET
// sin token de confirmacion (ver nota de seguridad en el informe). El id se
// acota con intval(), asi que el valor no puede inyectar SQL.
if (isset($_GET['eliminar']) && tienePermiso('eliminar_taller')) {
    $id_eliminar = intval($_GET['eliminar']);
    $stmt_eliminar = $conexion->prepare('DELETE FROM taller WHERE id_taller = ?');
    $stmt_eliminar->execute([$id_eliminar]);
    header("Location: administrar_talleres.php");
    exit();
}

// Filtro de busqueda por texto (nombre, direccion, correo o telefono). Llega
// por GET y se escapa para que los comodines % y _ se busquen literales.
$filtro_texto = trim($_GET['q'] ?? '');

// Filtros por marca y por tipo de servicio (requisito 4.2 del enunciado).
$filtro_marca = trim($_GET['marca'] ?? '');
$filtro_servicio = filter_input(INPUT_GET, 'servicio', FILTER_VALIDATE_INT) ?: 0;

// Consulta los talleres para mostrarlos en tarjetas, aplicando los filtros.
// Sin filtro se listan todos los talleres registrados, incluido el mapa.
$condiciones = [];
$parametros = [];

if ($filtro_texto !== '') {
    $termino_buscado = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtro_texto) . '%';
    $condiciones[] = "(nombre LIKE ? ESCAPE '!' OR direccion LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR telefono LIKE ? ESCAPE '!')";
    $parametros[] = $termino_buscado;
    $parametros[] = $termino_buscado;
    $parametros[] = $termino_buscado;
    $parametros[] = $termino_buscado;
}
if ($filtro_marca !== '') {
    $condiciones[] = 'EXISTS (SELECT 1 FROM taller_marca tm WHERE tm.id_taller = taller.id_taller AND tm.marca = ?)';
    $parametros[] = $filtro_marca;
}
if ($filtro_servicio) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM taller_servicio ts WHERE ts.id_taller = taller.id_taller AND ts.id_servicio = ?)';
    $parametros[] = $filtro_servicio;
}

$consulta = 'SELECT * FROM taller';
if (!empty($condiciones)) {
    $consulta .= ' WHERE ' . implode(' AND ', $condiciones);
}
$consulta .= ' ORDER BY id_taller DESC';

$sentencia = $conexion->prepare($consulta);
$sentencia->execute($parametros);
$talleres = $sentencia->fetchAll(PDO::FETCH_ASSOC);
$total_talleres = count($talleres);

// Marcas y tipos de servicio disponibles para los filtros.
$marcas_disponibles = [];
$servicios_disponibles = [];
try {
    $marcas_disponibles = $conexion->query('SELECT DISTINCT marca FROM taller_marca ORDER BY marca')->fetchAll(PDO::FETCH_ASSOC);
    $servicios_disponibles = $conexion->query('SELECT id_servicio, nombre FROM servicio WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $marcas_disponibles = [];
    $servicios_disponibles = [];
}

// Horario semanal de cada sede, para mostrarlo en las tarjetas. La consulta
// va ANTES del listado: si se hiciera dentro del bucle, la primera sede
// se mostraria sin horario.
$horarios_por_taller = [];
$stmt_horarios = $conexion->prepare('SELECT id_taller, dia_semana, hora_inicio, hora_fin, activo FROM taller_horario ORDER BY dia_semana');
$stmt_horarios->execute();
foreach ($stmt_horarios->fetchAll(PDO::FETCH_ASSOC) as $fila_horario) {
    if ((int) $fila_horario['activo'] !== 1) {
        continue;
    }
    $horarios_por_taller[(int) $fila_horario['id_taller']][(int) $fila_horario['dia_semana']] = $fila_horario;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['gestion']; ?> - TGS</title>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/tgs-panel.css">
    <link rel="stylesheet" href="css/base-tgs.css">
    <style>
        /* Rejilla de sedes y mapa: estilos propios de esta pantalla. */
        .tgs-sedes { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .sede { display: flex; flex-direction: column; }
        .sede-cabecera { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
        .sede-nombre { font-size: 17px; color: var(--tgs-acento); font-weight: 700; }
        .sede-datos { background-color: rgba(3, 9, 18, 0.42); border: 1px solid var(--tgs-borde); border-radius: 9px; padding: 13px 15px; margin-bottom: 16px; }
        .sede-datos p { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--tgs-texto-suave); padding: 4px 0; }
        .sede-datos p strong { color: var(--tgs-acento-claro); font-weight: 600; flex-shrink: 0; }
        .sede-datos p span:last-child { text-align: right; }
        .sede-datos .id-sede { color: var(--tgs-texto-tenue); font-size: 11px; }
        .mapa-titulo { font-size: 11px; color: var(--tgs-texto-tenue); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; font-weight: 600; }
        .mapa { border: 1px solid var(--tgs-borde); border-radius: 9px; overflow: hidden; height: 230px; background-color: var(--tgs-superficie-alta); margin-top: auto; }
        .mapa iframe { width: 100%; height: 100%; border: 0; display: block; }
        .mapa-vacio { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--tgs-texto-tenue); font-size: 13px; }
        .buscador { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
        .buscador input[type="text"] { flex: 1; min-width: 220px; background-color: var(--tgs-superficie-alta); color: var(--tgs-texto); border: 1px solid var(--tgs-borde-claro); border-radius: 8px; padding: 10px 13px; font-size: 14px; outline: none; }
        .buscador input[type="text"]:focus { border-color: var(--tgs-acento); }
    </style>
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body class="tgs">

    <!-- Cabecera de los paneles de TGS -->
    <?php
# Cabecera comun del sitio: logo, navegacion del rol, idioma y salir.
$tgs_volver = isset($panel_volver) ? $panel_volver : '';
include 'includes/cabecera.php';
?>

    <main class="tgs-contenedor">
        <div class="tgs-titulo">
            <h2><?php echo $t['gestion']; ?></h2>
            <p><?php echo $lang == 'en' ? 'Review contact details and location for every registered workshop.' : 'Revisa los datos de contacto y la ubicación de cada taller registrado.'; ?></p>
            <div class="tgs-acciones" style="margin-top: 14px;">
                <span class="tgs-badge"><?php echo $total_talleres; ?> <?php echo $lang == 'en' ? ($total_talleres == 1 ? 'workshop' : 'workshops') : ($total_talleres == 1 ? 'taller' : 'talleres'); ?></span>
                <a href="agregar_taller.php" class="tgs-btn">+ <?php echo $t['nuevo']; ?></a>
            </div>
        </div>

        <!-- Filtro de busqueda: se envia por GET para conservar el filtro al
             recargar o compartir el enlace. -->
        <form action="administrar_talleres.php" method="GET" class="buscador">
            <input type="text" name="q" maxlength="150" value="<?php echo htmlspecialchars($filtro_texto); ?>" placeholder="<?php echo htmlspecialchars($t['buscar_placeholder']); ?>">
            <select name="marca" aria-label="<?php echo $lang == 'en' ? 'Brand' : 'Marca'; ?>">
                <option value=""><?php echo $lang == 'en' ? 'All brands' : 'Todas las marcas'; ?></option>
                <?php foreach ($marcas_disponibles as $opcion_marca): ?>
                    <option value="<?php echo htmlspecialchars($opcion_marca['marca']); ?>" <?php echo $filtro_marca === $opcion_marca['marca'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($opcion_marca['marca']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="servicio" aria-label="<?php echo $lang == 'en' ? 'Service type' : 'Tipo de servicio'; ?>">
                <option value="0"><?php echo $lang == 'en' ? 'All services' : 'Todos los servicios'; ?></option>
                <?php foreach ($servicios_disponibles as $opcion_servicio): ?>
                    <option value="<?php echo (int) $opcion_servicio['id_servicio']; ?>" <?php echo $filtro_servicio === (int) $opcion_servicio['id_servicio'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($opcion_servicio['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="tgs-btn"><?php echo $t['buscar']; ?></button>
            <?php if ($filtro_texto !== '' || $filtro_marca !== '' || $filtro_servicio): ?>
                <a href="administrar_talleres.php" class="tgs-btn neutro"><?php echo $t['limpiar_filtros']; ?></a>
            <?php endif; ?>
        </form>

        <!-- Una tarjeta por sede registrada -->
        <?php if (count($talleres) > 0): ?>
            <div class="tgs-sedes">
                <?php foreach ($talleres as $row): ?>
                    <?php $id_t = $row['id_taller']; ?>
                    <?php
                    // Resumen del horario semanal de la sede para mostrarlo en la tarjeta.
                    $resumen_horario = $t['sin_horario'];
                    if (!empty($horarios_por_taller[$id_t])) {
                        $resumen_horario = '';
                        foreach ($horarios_por_taller[$id_t] as $dia_horario => $datos_horario) {
                            $resumen_horario .= $t['dias'][$dia_horario] . ' ' . substr((string) $datos_horario['hora_inicio'], 0, 5) . '-' . substr((string) $datos_horario['hora_fin'], 0, 5) . '  ';
                        }
                        $resumen_horario = trim($resumen_horario);
                    }
                    ?>
                    <div class="tgs-tarjeta sede">
                        <div class="sede-cabecera">
                            <div>
                                <div class="sede-nombre"><?php echo htmlspecialchars($row['nombre']); ?></div>
                                <div class="id-sede">ID #<?php echo $id_t; ?></div>
                            </div>
                            <div style="display: flex; gap: 8px; flex-shrink: 0;">
                                <a href="editar_taller.php?id=<?php echo $id_t; ?>" class="tgs-btn chico" title="<?php echo $t['editar']; ?>"><?php echo $t['editar']; ?></a>
                                <a href="administrar_talleres.php?eliminar=<?php echo $id_t; ?>" class="tgs-btn peligro chico" title="<?php echo $t['eliminar']; ?>" onclick="return confirm('<?php echo ($lang === 'en') ? 'Are you sure you want to delete this workshop?' : '¿Estás seguro de eliminar este taller?'; ?>');"><?php echo $t['eliminar']; ?></a>
                            </div>
                        </div>

                        <div class="sede-datos">
                            <p><strong><?php echo $t['telefono']; ?></strong><span><?php echo htmlspecialchars($row['telefono'] ?? '-'); ?></span></p>
                            <p><strong><?php echo $t['correo']; ?></strong><span><?php echo htmlspecialchars($row['email'] ?? $t['sin_correo']); ?></span></p>
                            <p><strong><?php echo $t['direccion']; ?></strong><span><?php echo htmlspecialchars($row['direccion'] ?? ''); ?></span></p>
                            <p><strong><?php echo $t['capacidad']; ?></strong><span><?php echo htmlspecialchars($row['capacidad'] ?? 1); ?></span></p>
                            <p>
                                <strong><?php echo $lang == 'en' ? 'Free slots today' : 'Lugares libres hoy'; ?>:</strong>
                                <span><?php
                                // Estado de ocupacion: cuantas franjas de hoy quedan
                                // sin reservar en esta sede.
                                $franjas_hoy = tgs_disponibilidad_publica($conexion, (int) $id_t, date('Y-m-d'));
                                $libres_hoy = count($franjas_hoy['libres'] ?? []);
                                $total_hoy = $libres_hoy + count($franjas_hoy['ocupadas'] ?? []);
                                echo $libres_hoy . ' <small style="color:#94a3b8;">(' . ($lang == 'en' ? 'of' : 'de') . ' ' . $total_hoy . ')</small>';
                                ?></span>
                            </p>
                            <p><strong><?php echo $t['horario']; ?></strong><span><?php echo htmlspecialchars($resumen_horario); ?></span></p>
                        </div>

                        <div class="mapa-titulo"><?php echo $lang == 'en' ? 'Location on the map' : 'Ubicación en el mapa'; ?></div>
                        <div class="mapa">
                            <?php // El mapa se guarda como HTML (iframe) cargado por el admin, por eso se imprime tal cual (ver nota de seguridad).
                            if (!empty($row['mapa'])): ?>
                                <?php echo $row['mapa']; ?>
                            <?php else: ?>
                                <div class="mapa-vacio"><?php echo $t['sin_mapa']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="tgs-tarjeta" style="text-align: center; color: var(--tgs-texto-tenue);">
                <?php echo $filtro_texto !== '' ? $t['sin_resultados'] : $t['no_talleres']; ?>
            </div>
        <?php endif; ?>

        <a href="dashboard_admin.php" class="tgs-volver">&larr; <?php echo $t['volver']; ?></a>
    </main>

    <?php include 'includes/pie.php'; ?>

</body>
</html>
