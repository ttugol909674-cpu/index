<?php
/**
 * TGS - Taller Global de Servicios
 * Vista detallada de un taller.
 *
 * Pagina publica de una sede: informacion de contacto, mapa, marcas y tipos
 * de servicio que atiende, horario semanal, lugares disponibles y las
 * opciones para reservar o cancelar una reserva en esa sede.
 *
 * Es el destino del listado de talleres (requisito 5.2 del enunciado).
 *
 * Dependencias: auth.php, conexion.php, taller_disponibilidad.php
 */
require_once 'auth.php';
require_once 'conexion.php';
require_once 'taller_disponibilidad.php';

$lang_code = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : ($_SESSION['lang'] ?? 'es');
$_SESSION['lang'] = $lang_code;

$cedula_usuario = $_SESSION['cedula'] ?? '';
$es_cliente = (strtolower(trim($_SESSION['rol'] ?? '')) === 'cliente');

$texto = $lang_code === 'en' ? [
    'titulo' => 'Workshop', 'inicio' => 'Home', 'volver' => 'Back to the list', 'contacto' => 'Contact',
    'telefono' => 'Phone', 'correo' => 'Email', 'direccion' => 'Address', 'marcas' => 'Brands we service',
    'servicios' => 'Services offered', 'horario' => 'Opening hours', 'disponibilidad' => 'Availability',
    'lugares_libres' => 'free slots', 'sin_horario' => 'This workshop has no published schedule.',
    'sin_marca' => 'No brands registered yet.', 'sin_servicio' => 'No services registered yet.',
    'reservar' => 'Book an appointment here', 'mis_reservas' => 'Your appointments at this workshop',
    'cancelar' => 'Cancel appointment', 'sin_reservas' => 'You have no appointments at this workshop.',
    'cerrado' => 'Closed', 'hoy' => 'today', 'sin_mapa' => 'No map configured for this workshop.',
    'estado' => 'Status', 'servicio' => 'Service', 'fecha' => 'Date', 'hora' => 'Time', 'turno' => 'Appointment'
] : [
    'titulo' => 'Taller', 'inicio' => 'Inicio', 'volver' => 'Volver al listado', 'contacto' => 'Contacto',
    'telefono' => 'Teléfono', 'correo' => 'Correo', 'direccion' => 'Dirección', 'marcas' => 'Marcas que atendemos',
    'servicios' => 'Servicios que ofrecemos', 'horario' => 'Horario de atención', 'disponibilidad' => 'Disponibilidad',
    'lugares_libres' => 'lugares libres', 'sin_horario' => 'Este taller todavía no tiene horario publicado.',
    'sin_marca' => 'Todavía no se registraron marcas.', 'sin_servicio' => 'Todavía no se registraron servicios.',
    'reservar' => 'Reservar un turno acá', 'mis_reservas' => 'Tus turnos en este taller',
    'cancelar' => 'Cancelar turno', 'sin_reservas' => 'No tenés turnos en este taller.',
    'cerrado' => 'Cerrado', 'hoy' => 'hoy', 'sin_mapa' => 'Este taller no tiene un mapa configurado.',
    'estado' => 'Estado', 'servicio' => 'Servicio', 'fecha' => 'Fecha', 'hora' => 'Hora', 'turno' => 'Turno'
];

$id_taller = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$taller = null;
$marcas = [];
$servicios = [];
$horarios = [];
$reservas_cliente = [];
$disponibilidad_hoy = [];

if ($id_taller) {
    try {
        $stmt = $conexion->prepare('SELECT * FROM taller WHERE id_taller = :id_taller LIMIT 1');
        $stmt->execute(['id_taller' => $id_taller]);
        $taller = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($taller) {
            $stmt = $conexion->prepare('SELECT marca FROM taller_marca WHERE id_taller = :id_taller ORDER BY marca');
            $stmt->execute(['id_taller' => $id_taller]);
            $marcas = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $conexion->prepare(
                'SELECT s.id_servicio, s.nombre, s.precio, s.activo FROM taller_servicio ts
                 JOIN servicio s ON s.id_servicio = ts.id_servicio
                 WHERE ts.id_taller = :id_taller ORDER BY s.nombre'
            );
            $stmt->execute(['id_taller' => $id_taller]);
            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $conexion->prepare('SELECT dia_semana, hora_inicio, hora_fin, activo FROM taller_horario WHERE id_taller = :id_taller ORDER BY dia_semana');
            $stmt->execute(['id_taller' => $id_taller]);
            $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Turnos del cliente en esta sede.
            if ($es_cliente && $cedula_usuario !== '') {
                $stmt = $conexion->prepare(
                    'SELECT r.id_reserva, r.fecha_reserva, r.hora_reserva, r.estado, s.nombre AS servicio_nombre
                     FROM reserva r LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
                     WHERE r.id_taller = :id_taller AND r.cedula_usuario = :cedula_usuario AND r.estado <> :cancelada
                     ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC'
                );
                $stmt->execute([
                    'id_taller' => $id_taller,
                    'cedula_usuario' => $cedula_usuario,
                    'cancelada' => 'Cancelada'
                ]);
                $reservas_cliente = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Lugares libres de hoy: cuantas franjas quedan sin reservar.
            $hoy = date('Y-m-d');
            $franjas = tgs_disponibilidad_publica($conexion, (int) $id_taller, $hoy);
            $disponibilidad_hoy = count($franjas['libres'] ?? []);
        }
    } catch (PDOException $e) {
        $taller = null;
    }
}

$nombre_dias = $lang_code === 'en'
    ? [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday']
    : [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($taller['nombre'] ?? $texto['titulo']); ?> - TGS</title>
    <link rel="stylesheet" href="css/tgs-panel.css">
    <style>
        .rejilla-detalle { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .ficha-dato { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid var(--tgs-borde); font-size: 13px; color: var(--tgs-texto-suave); }
        .ficha-dato:last-child { border-bottom: none; }
        .ficha-dato span:first-child { color: var(--tgs-texto-tenue); }
        .etiquetas { display: flex; flex-wrap: wrap; gap: 6px; }
        .mapa { border: 1px solid var(--tgs-borde); border-radius: 9px; overflow: hidden; height: 260px; background: var(--tgs-superficie-alta); }
        .mapa iframe { width: 100%; height: 100%; border: 0; display: block; }
        .mapa-vacio { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--tgs-texto-tenue); font-size: 13px; }
        .dia { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--tgs-borde); font-size: 13px; color: var(--tgs-texto-suave); }
        .dia:last-child { border-bottom: none; }
        .dia.cerrado { color: var(--tgs-texto-tenue); }
    </style>
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body class="tgs">
    <header class="tgs-header">
        <a href="index.php" class="marca">TGS - Blue Tech Uruguay</a>
        <nav class="tgs-nav">
            <a href="index.php" class="enlace"><?php echo $texto['inicio']; ?></a>
            <div class="tgs-idioma">
                <a href="taller.php?id=<?php echo (int) $id_taller; ?>&lang=es" class="<?php echo $lang_code === 'es' ? 'activo' : ''; ?>">ES</a>
                <a href="taller.php?id=<?php echo (int) $id_taller; ?>&lang=en" class="<?php echo $lang_code === 'en' ? 'activo' : ''; ?>">EN</a>
            </div>
            <?php if ($cedula_usuario !== ''): ?>
                <a href="panel_cliente.php" class="enlace"><?php echo $lang_code === 'en' ? 'My panel' : 'Mi panel'; ?></a>
            <?php else: ?>
                <a href="login.php" class="enlace"><?php echo $lang_code === 'en' ? 'Sign In' : 'Iniciar Sesión'; ?></a>
            <?php endif; ?>
            <a href="logout.php" class="tgs-btn peligro chico"><?php echo $lang_code === 'en' ? 'Sign Out' : 'Cerrar Sesión'; ?></a>
        </nav>
    </header>

    <main class="tgs-contenedor">
        <?php if (!$taller): ?>
            <div class="tgs-tarjeta" style="text-align:center; color:var(--tgs-texto-tenue);">
                <?php echo $lang_code === 'en' ? 'The workshop does not exist.' : 'El taller no existe.'; ?>
            </div>
        <?php else: ?>
            <div class="tgs-titulo">
                <h2><?php echo htmlspecialchars($taller['nombre']); ?></h2>
                <p><?php echo htmlspecialchars($taller['direccion'] ?? ''); ?></p>
                <div class="tgs-acciones" style="margin-top: 14px;">
                    <a href="reservas.php" class="tgs-btn"><?php echo $texto['reservar']; ?></a>
                    <a href="index.php#talleres" class="tgs-btn neutro"><?php echo $texto['volver']; ?></a>
                </div>
            </div>

            <div class="rejilla-detalle">
                <!-- Informacion de contacto -->
                <div class="tgs-tarjeta">
                    <h3><?php echo $texto['contacto']; ?></h3>
                    <div class="ficha-dato"><span><?php echo $texto['telefono']; ?></span><span><?php echo htmlspecialchars($taller['telefono'] ?? '-'); ?></span></div>
                    <div class="ficha-dato"><span><?php echo $texto['correo']; ?></span><span><?php echo htmlspecialchars($taller['email'] ?? '-'); ?></span></div>
                    <div class="ficha-dato"><span><?php echo $texto['direccion']; ?></span><span><?php echo htmlspecialchars($taller['direccion'] ?? '-'); ?></span></div>
                    <div class="ficha-dato"><span><?php echo $lang_code === 'en' ? 'Capacity' : 'Capacidad'; ?></span><span><?php echo (int) ($taller['capacidad'] ?? 1); ?></span></div>
                    <div class="ficha-dato">
                        <span><?php echo $lang_code === 'en' ? 'Free slots' : 'Lugares libres'; ?></span>
                        <span><?php echo $disponibilidad_hoy; ?> <?php echo $texto['hoy']; ?></span>
                    </div>
                </div>

                <!-- Marcas y servicios -->
                <div class="tgs-tarjeta">
                    <h3><?php echo $texto['marcas']; ?></h3>
                    <?php if (empty($marcas)): ?>
                        <p style="color:var(--tgs-texto-tenue); font-size:13px;"><?php echo $texto['sin_marca']; ?></p>
                    <?php else: ?>
                        <div class="etiquetas">
                            <?php foreach ($marcas as $marca): ?>
                                <span class="tgs-badge"><?php echo htmlspecialchars($marca); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <h3 style="margin-top: 18px;"><?php echo $texto['servicios']; ?></h3>
                    <?php if (empty($servicios)): ?>
                        <p style="color:var(--tgs-texto-tenue); font-size:13px;"><?php echo $texto['sin_servicio']; ?></p>
                    <?php else: ?>
                        <?php foreach ($servicios as $servicio): ?>
                            <div class="ficha-dato">
                                <span><?php echo htmlspecialchars($servicio['nombre']); ?></span>
                                <span><?php echo htmlspecialchars(number_format((float) $servicio['precio'], 2, ',', '.')); ?> USD</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Horario -->
                <div class="tgs-tarjeta">
                    <h3><?php echo $texto['horario']; ?></h3>
                    <?php if (empty($horarios)): ?>
                        <p style="color:var(--tgs-texto-tenue); font-size:13px;"><?php echo $texto['sin_horario']; ?></p>
                    <?php else: ?>
                        <?php foreach ($horarios as $horario): ?>
                            <div class="dia <?php echo (int) $horario['activo'] !== 1 ? 'cerrado' : ''; ?>">
                                <span><?php echo htmlspecialchars($nombre_dias[(int) $horario['dia_semana']] ?? ''); ?></span>
                                <span>
                                    <?php if ((int) $horario['activo'] !== 1): ?>
                                        <?php echo $texto['cerrado']; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(substr((string) $horario['hora_inicio'], 0, 5)); ?> - <?php echo htmlspecialchars(substr((string) $horario['hora_fin'], 0, 5)); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Mapa -->
                <div class="tgs-tarjeta">
                    <h3><?php echo $lang_code === 'en' ? 'Location' : 'Ubicación'; ?></h3>
                    <div class="mapa">
                        <?php if (!empty($taller['mapa'])): ?>
                            <?php echo $taller['mapa']; ?>
                        <?php else: ?>
                            <div class="mapa-vacio"><?php echo $texto['sin_mapa']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Turnos del cliente en esta sede: puede cancelarlos -->
            <?php if ($es_cliente): ?>
                <div class="tgs-tarjeta" style="margin-top: 20px;">
                    <h3><?php echo $texto['mis_reservas']; ?></h3>
                    <?php if (empty($reservas_cliente)): ?>
                        <p style="color:var(--tgs-texto-tenue); font-size:13px;"><?php echo $texto['sin_reservas']; ?></p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo $texto['fecha']; ?></th>
                                    <th><?php echo $texto['hora']; ?></th>
                                    <th><?php echo $texto['servicio']; ?></th>
                                    <th><?php echo $texto['estado']; ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas_cliente as $reserva): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reserva['fecha_reserva'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(substr((string) ($reserva['hora_reserva'] ?? ''), 0, 5)); ?></td>
                                        <td><?php echo htmlspecialchars($reserva['servicio_nombre'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($reserva['estado'] ?? '-'); ?></td>
                                        <td>
                                            <form method="POST" action="cancelar_reserva.php" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Cancel this appointment?' : '¿Cancelar este turno?'; ?>');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                                <input type="hidden" name="id_reserva" value="<?php echo (int) ($reserva['id_reserva'] ?? 0); ?>">
                                                <button type="submit" class="tgs-btn peligro chico"><?php echo $texto['cancelar']; ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="tgs-pie">
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
