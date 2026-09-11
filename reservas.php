<?php
# Inicia la sesión para guardar usuario, idioma y permisos
session_start();

# Incluye la conexión a la base de datos
require_once "conexion.php";

# Si llega un idioma por URL, se guarda en la sesión
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] == 'en') ? 'en' : 'es';
}

# Define el idioma actual
$idioma_actual = $_SESSION['lang'] ?? 'es';

# Carga los textos del idioma correspondiente
if ($idioma_actual == 'en') {
    include_once 'lang/en.php';
} else {
    include_once 'lang/es.php';
}

# Si el usuario no inició sesión, lo redirige al login
if (!isset($_SESSION['cedula'])) {
    header('Location: login.php');
    exit;
}

# Datos del usuario en sesión para mostrar su nombre y rol
$cedula_usuario = $_SESSION['cedula'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$rol_usuario    = $_SESSION['rol'] ?? 'Cliente';
$mensaje_reserva = '';

# Crea un catálogo inicial para que el cliente pueda elegir el servicio de su turno
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS servicio (
        id_servicio INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        descripcion VARCHAR(255) NULL,
        precio DECIMAL(10,2) DEFAULT 0,
        activo TINYINT(1) DEFAULT 1
    )");

    $cantidad_servicios = (int) $conexion->query('SELECT COUNT(*) FROM servicio')->fetchColumn();
    if ($cantidad_servicios === 0) {
        $servicios_iniciales = [
            ['Mantenimiento preventivo', 'Aceite, filtros, fluidos y revisión general'],
            ['Diagnóstico computarizado', 'Revisión electrónica y detección de fallas'],
            ['Mecánica general', 'Frenos, suspensión, transmisión y reparaciones']
        ];
        $insertar_servicio = $conexion->prepare('INSERT INTO servicio (nombre, descripcion) VALUES (?, ?)');
        foreach ($servicios_iniciales as $servicio_inicial) {
            $insertar_servicio->execute($servicio_inicial);
        }
    }
} catch (PDOException $e) {
    # Si la tabla ya existe con otra estructura, la pantalla continúa funcionando
}

# Carga los servicios activos para mostrarlos en el formulario del cliente
$servicios = [];
try {
    $consulta_servicios = $conexion->query('SELECT id_servicio, nombre, descripcion FROM servicio WHERE activo = 1 ORDER BY nombre');
    $servicios = $consulta_servicios ? $consulta_servicios->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $servicios = [];
}

# Actualiza una reserva únicamente si pertenece al usuario autenticado
# También valida que el vehículo y servicio elegidos pertenezcan a opciones válidas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_editar_reserva'])) {
    $id_reserva_editar = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
    $fecha_editar = trim($_POST['fecha_editar'] ?? '');
    $hora_editar = trim($_POST['hora_editar'] ?? '');
    $vehiculo_editar = filter_input(INPUT_POST, 'id_vehiculo_editar', FILTER_VALIDATE_INT);
    $servicio_editar = filter_input(INPUT_POST, 'id_servicio_editar', FILTER_VALIDATE_INT);

    $fecha_valida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_editar);
    $hora_valida = preg_match('/^\d{2}:\d{2}$/', $hora_editar);

    if ($id_reserva_editar && $vehiculo_editar && $servicio_editar && $fecha_valida && $hora_valida) {
        try {
            # Comprueba que la reserva y el vehículo sean del mismo usuario
            $verificar = $conexion->prepare('SELECT r.id_reserva FROM reserva r INNER JOIN vehiculo v ON v.id_vehiculo = :id_vehiculo AND v.cedula_usuario = :cedula INNER JOIN servicio s ON s.id_servicio = :id_servicio AND s.activo = 1 WHERE r.id_reserva = :id_reserva AND r.cedula_usuario = :cedula_reserva LIMIT 1');
            $verificar->execute([
                'id_vehiculo' => $vehiculo_editar,
                'cedula' => $cedula_usuario,
                'id_servicio' => $servicio_editar,
                'id_reserva' => $id_reserva_editar,
                'cedula_reserva' => $cedula_usuario
            ]);

            if ($verificar->fetch(PDO::FETCH_ASSOC)) {
                # Guarda la nueva fecha, hora y vehículo de la reserva
                $actualizar = $conexion->prepare('UPDATE reserva SET fecha_reserva = :fecha, hora_reserva = :hora, id_vehiculo = :id_vehiculo, id_servicio = :id_servicio WHERE id_reserva = :id_reserva AND cedula_usuario = :cedula');
                $actualizar->execute([
                    'fecha' => $fecha_editar,
                    'hora' => $hora_editar,
                    'id_vehiculo' => $vehiculo_editar,
                    'id_servicio' => $servicio_editar,
                    'id_reserva' => $id_reserva_editar,
                    'cedula' => $cedula_usuario
                ]);
                $mensaje_reserva = "<div class='alert success'>" . ($idioma_actual == 'en' ? 'Appointment updated successfully.' : '¡Turno actualizado con éxito!') . "</div>";
            } else {
                $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? 'You can only edit your own appointments and vehicles.' : 'Solo puedes editar tus propios turnos y vehículos.') . "</div>";
            }
        } catch (Exception $e) {
            $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? 'The appointment could not be updated.' : 'No se pudo actualizar el turno.') . "</div>";
        }
    } else {
        $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? 'Enter a valid date, time, and vehicle.' : 'Ingresa una fecha, hora y vehículo válidos.') . "</div>";
    }
}

# Si llega una solicitud para eliminar un vehículo, se borra de la base de datos
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_eliminar_vehiculo'])) {
    $id_vehiculo_eliminar = trim($_POST['id_vehiculo_eliminar'] ?? '');
    if (!empty($id_vehiculo_eliminar)) {
        try {
            $sqlDelVeh = "DELETE FROM vehiculo WHERE id_vehiculo = ? AND cedula_usuario = ?";
            $stmtDel = $conexion->prepare($sqlDelVeh);
            $stmtDel->execute([$id_vehiculo_eliminar, $cedula_usuario]);
            $mensaje_reserva = "<div class='alert success'>" . ($idioma_actual == 'en' ? "Vehicle deleted successfully." : "Vehículo eliminado con éxito.") . "</div>";
        } catch (Exception $e) {
            $mensaje_reserva = "<div class='alert error'><strong>Error real:</strong> " . $e->getMessage() . "</div>";
        }
    }
}

# Si llega una solicitud para guardar una nueva reserva
# Este bloque registra el turno después de validar vehículo, fecha, hora y servicio
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_reserva'])) {
    # Recoge fecha, hora y datos del turno
    $fecha       = trim($_POST['fecha'] ?? '');
    $hora        = trim($_POST['hora'] ?? '');
    $id_servicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);
    $id_taller   = trim($_POST['id_taller'] ?? '1');
    $estado      = 'Pendiente';

    # Selección del vehículo elegible para reservar
    $seleccion_vehiculo = $_POST['id_vehiculo'] ?? '';
    $id_vehiculo = '';

    # Verifica que la fecha y hora existan
    if (!empty($cedula_usuario) && !empty($fecha) && $fecha != '0000-00-00' && !empty($hora) && $id_servicio) {
        try {
            # Si eligió registrar un vehículo nuevo, primero lo crea
            if ($seleccion_vehiculo == 'nuevo') {
                $marca   = trim($_POST['nueva_marca'] ?? '');
                $modelo  = trim($_POST['nuevo_modelo'] ?? '');
                $anio    = trim($_POST['nuevo_anio'] ?? date('Y'));
                $patente = trim($_POST['nueva_patente'] ?? 'SIN-PATENTE');

                if (!empty($marca) && !empty($modelo)) {
                    $sqlVehIns = "INSERT INTO vehiculo (cedula_usuario, patente, marca, modelo, anio) VALUES (?, ?, ?, ?, ?)";
                    $stmtVehIns = $conexion->prepare($sqlVehIns);
                    $stmtVehIns->execute([$cedula_usuario, $patente, $marca, $modelo, $anio]);
                    $id_vehiculo = $conexion->lastInsertId();
                } else {
                    $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "Please complete the brand and model of the new vehicle." : "Por favor completa la marca y modelo del nuevo vehículo.") . "</div>";
                }
            } else {
                # Si escogió un vehículo ya registrado, se usa su id
                $id_vehiculo = $seleccion_vehiculo;
            }

            # Si hay un vehículo válido, se inserta la reserva
            if (!empty($id_vehiculo) && empty($mensaje_reserva)) {
                # Verifica que el servicio elegido esté activo
                $validar_servicio = $conexion->prepare('SELECT id_servicio FROM servicio WHERE id_servicio = ? AND activo = 1 LIMIT 1');
                $validar_servicio->execute([$id_servicio]);
                if (!$validar_servicio->fetchColumn()) {
                    $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "Select a valid service." : "Selecciona un servicio válido.") . "</div>";
                }
            }

            if (!empty($id_vehiculo) && empty($mensaje_reserva)) {
                $sqlIns = "INSERT INTO reserva (cedula_usuario, id_vehiculo, id_taller, id_servicio, fecha_reserva, hora_reserva, estado) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmtIns = $conexion->prepare($sqlIns);
                $stmtIns->execute([$cedula_usuario, $id_vehiculo, $id_taller, $id_servicio, $fecha, $hora, $estado]);

                $mensaje_reserva = "<div class='alert success'>" . ($idioma_actual == 'en' ? "Appointment successfully booked!" : "¡Turno reservado con éxito!") . "</div>";
            } else if (empty($mensaje_reserva)) {
                $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "You must select or enter a valid vehicle." : "Debe seleccionar o ingresar un vehículo válido.") . "</div>";
            }
        } catch (Exception $e) {
            $mensaje_reserva = "<div class='alert error'><strong>Error real:</strong> " . $e->getMessage() . "</div>";
        }
    } else {
        $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "Please select a valid date and fill in all required fields." : "Por favor selecciona una fecha válida y completa todos los campos obligatorios.") . "</div>";
    }
}

# Consulta las reservas del usuario actual
# El filtro por cédula evita mostrar turnos de otros clientes
$reservas = [];
try {
    $sql = "SELECT r.*, v.modelo, v.marca, s.nombre AS servicio_nombre FROM reserva r LEFT JOIN vehiculo v ON r.id_vehiculo = v.id_vehiculo LEFT JOIN servicio s ON r.id_servicio = s.id_servicio WHERE r.cedula_usuario = ? ORDER BY r.id_reserva DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$cedula_usuario]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reservas = [];
}

# Consulta los vehículos del usuario actual
$vehiculos = [];
try {
    $sqlVeh = "SELECT * FROM vehiculo WHERE cedula_usuario = ?";
    $stmtVeh = $conexion->prepare($sqlVeh);
    $stmtVeh->execute([$cedula_usuario]);
    $vehiculos = $stmtVeh->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vehiculos = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_actual; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TGS - <?php echo $lang['mis_turnos']; ?></title>
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
        
        .lang-selector { display: flex; gap: 5px; background: rgba(15, 23, 42, 0.9); padding: 4px; border-radius: 6px; border: 1px solid #475569; }
        .lang-btn { padding: 4px 8px; font-size: 12px; font-weight: bold; text-decoration: none; border-radius: 4px; color: #94a3b8; }
        .lang-btn.active { background-color: #38bdf8; color: #0f172a; }
        
        .main-container { flex: 1; padding: 40px 20px; max-width: 1300px; width: 100%; margin: 0 auto; display: grid; grid-template-columns: 420px 1fr; gap: 30px; }
        @media (max-width: 900px) { .main-container { grid-template-columns: 1fr; } }

        .card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); }
        .card h2 { font-size: 20px; color: #38bdf8; margin-bottom: 20px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; color: #cbd5e1; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px 14px; background-color: rgba(15, 23, 42, 0.9); border: 1px solid #475569; border-radius: 8px; color: #fff; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #38bdf8; }

        .btn-submit { width: 100%; background-color: #2563eb; color: white; padding: 12px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        .btn-submit:hover { background-color: #1d4ed8; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; padding: 12px; font-weight: 600; border-bottom: 1px solid #334155; }
        td { padding: 12px; border-bottom: 1px solid #334155; color: #cbd5e1; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .btn-cancel { background-color: #dc2626; color: white; }
        .btn-cancel:hover { background-color: #b91c1c; }
        .edit-form { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 6px; }
        .edit-form input, .edit-form select { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 6px; padding: 6px 8px; font-size: 12px; }
        .btn-edit { background-color: #2563eb; color: white; }
        .btn-edit:hover { background-color: #1d4ed8; }

        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; text-align: center; word-break: break-all; }
        .alert.success { background-color: rgba(22, 163, 74, 0.2); border: 1px solid #16a34a; color: #4ade80; }
        .alert.error { background-color: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
        .no-data { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; }

        .seccion-nuevo-vehiculo { background-color: rgba(15, 23, 42, 0.5); border: 1px dashed #475569; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .service-description { display: block; color: #94a3b8; font-size: 12px; margin-top: 4px; }

        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <script>
        function verificarVehiculoSeleccionado() {
            const select = document.getElementById('id_vehiculo');
            const divNuevo = document.getElementById('contenedor-nuevo-vehiculo');
            
            if (select.value === 'nuevo') {
                divNuevo.style.display = 'block';
                document.getElementById('nueva_marca').required = true;
                document.getElementById('nuevo_modelo').required = true;
            } else {
                divNuevo.style.display = 'none';
                document.getElementById('nueva_marca').required = false;
                document.getElementById('nuevo_modelo').required = false;
            }
        }
    </script>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TGS - Panel</a>
        <nav>
            <a href="index.php" class="nav-link"><?php echo $lang['inicio']; ?></a>
            <a href="index.php#servicios" class="nav-link"><?php echo $lang['servicios']; ?></a>
            <a href="index.php#sobre-nosotros" class="nav-link"><?php echo $lang['sobre_nosotros']; ?></a>
            <a href="reservas.php" class="nav-link" style="color: #38bdf8; font-weight: bold;"><?php echo $lang['mis_turnos']; ?></a>
            
            <div class="lang-selector">
                <a href="reservas.php?lang=es" class="lang-btn <?php echo ($idioma_actual == 'es') ? 'active' : ''; ?>">ES</a>
                <a href="reservas.php?lang=en" class="lang-btn <?php echo ($idioma_actual == 'en') ? 'active' : ''; ?>">EN</a>
            </div>

            <span><?php echo $lang['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong> <span style="font-size: 11px; color: #38bdf8;">(<?php echo htmlspecialchars($rol_usuario); ?>)</span></span>
            
            <a href="logout.php" class="btn-logout"><?php echo $lang['cerrar_sesion']; ?></a>
        </nav>
    </header>

    <div class="main-container">
        <div class="card">
            <h2><?php echo ($idioma_actual == 'en' ? 'Book Appointment' : 'Reservar Turno'); ?></h2>
            <?php echo $mensaje_reserva; ?>
            <form action="reservas.php" method="POST">
                <input type="hidden" name="accion_reserva" value="1">
                
                <div class="form-group">
                    <label for="id_servicio"><?php echo ($idioma_actual == 'en' ? 'Service' : 'Servicio'); ?></label>
                    <select name="id_servicio" id="id_servicio" required>
                        <option value=""><?php echo ($idioma_actual == 'en' ? 'Select a service' : 'Selecciona un servicio'); ?></option>
                        <?php foreach ($servicios as $servicio): ?>
                            <option value="<?php echo htmlspecialchars($servicio['id_servicio']); ?>">
                                <?php echo htmlspecialchars($idioma_actual == 'en' ? $servicio['nombre'] : $servicio['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="service-description"><?php echo ($idioma_actual == 'en' ? 'Choose the work your vehicle needs.' : 'Elige el trabajo que necesita tu vehículo.'); ?></span>
                </div>

                <div class="form-group">
                    <label for="id_vehiculo"><?php echo ($idioma_actual == 'en' ? 'Vehicle' : 'Vehículo'); ?></label>
                    <select name="id_vehiculo" id="id_vehiculo" required onchange="verificarVehiculoSeleccionado()">
                        <option value=""><?php echo ($idioma_actual == 'en' ? 'Select a vehicle' : 'Selecciona un vehículo'); ?></option>
                        <?php foreach ($vehiculos as $v): 
                            $id_v = $v['id_vehiculo'] ?? '';
                            $mar = $v['marca'] ?? '';
                            $mod = $v['modelo'] ?? '';
                        ?>
                            <option value="<?php echo htmlspecialchars($id_v); ?>">
                                <?php echo htmlspecialchars(($mar ? "$mar" : "") . ($mod ? " $mod" : "Vehículo ID: $id_v")); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="nuevo" style="color: #38bdf8; font-weight: bold;"><?php echo ($idioma_actual == 'en' ? '+ Register a new vehicle...' : '+ Registrar un vehículo nuevo...'); ?></option>
                    </select>
                </div>

                <div id="contenedor-nuevo-vehiculo" class="seccion-nuevo-vehiculo" style="display: none;">
                    <div class="form-group">
                        <label for="nueva_marca"><?php echo ($idioma_actual == 'en' ? 'Brand' : 'Marca'); ?></label>
                        <input type="text" name="nueva_marca" id="nueva_marca" placeholder="Ej: Toyota, Ford...">
                    </div>
                    <div class="form-group">
                        <label for="nuevo_modelo"><?php echo ($idioma_actual == 'en' ? 'Model' : 'Modelo'); ?></label>
                        <input type="text" name="nuevo_modelo" id="nuevo_modelo" placeholder="Ej: Corolla, Focus...">
                    </div>
                    <div class="form-group">
                        <label for="nuevo_anio"><?php echo ($idioma_actual == 'en' ? 'Year' : 'Año'); ?></label>
                        <input type="number" name="nuevo_anio" id="nuevo_anio" value="<?php echo date('Y'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="nueva_patente"><?php echo ($idioma_actual == 'en' ? 'License Plate' : 'Patente'); ?></label>
                        <input type="text" name="nueva_patente" id="nueva_patente" placeholder="Ej: ABC 1234">
                    </div>
                </div>

                <div class="form-group">
                    <label for="fecha"><?php echo ($idioma_actual == 'en' ? 'Date' : 'Fecha'); ?></label>
                    <input type="date" name="fecha" id="fecha" required>
                </div>

                <div class="form-group">
                    <label for="hora"><?php echo ($idioma_actual == 'en' ? 'Time' : 'Hora'); ?></label>
                    <input type="time" name="hora" id="hora" required>
                </div>

                <button type="submit" class="btn-submit"><?php echo ($idioma_actual == 'en' ? 'Confirm Appointment' : 'Confirmar Reserva'); ?></button>
            </form>

            <div style="margin-top: 30px; border-top: 1px solid #334155; padding-top: 20px;">
                <h3 style="font-size: 16px; color: #38bdf8; margin-bottom: 15px;"><?php echo ($idioma_actual == 'en' ? 'My Registered Vehicles' : 'Mis Vehículos Registrados'); ?></h3>
                <?php if (count($vehiculos) > 0): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($vehiculos as $v): ?>
                            <li style="display: flex; justify-content: space-between; align-items: center; background: rgba(15, 23, 42, 0.6); padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; font-size: 13px; border: 1px solid #334155;">
                                <span style="color: #cbd5e1;">🚗 <strong><?php echo htmlspecialchars(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '')); ?></strong> <span style="color: #94a3b8; font-size: 11px;">(<?php echo htmlspecialchars($v['patente'] ?? 'Sin patente'); ?>)</span></span>
                                <form action="reservas.php" method="POST" onsubmit="return confirm('<?php echo ($idioma_actual == 'en' ? 'Do you want to delete this vehicle?' : '¿Deseas eliminar este vehículo?'); ?>');" style="margin:0;">
                                    <input type="hidden" name="accion_eliminar_vehiculo" value="1">
                                    <input type="hidden" name="id_vehiculo_eliminar" value="<?php echo $v['id_vehiculo']; ?>">
                                    <button type="submit" style="background: #dc2626; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                                        <?php echo ($idioma_actual == 'en' ? 'Delete' : 'Eliminar'); ?>
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: #94a3b8; font-size: 13px;"><?php echo ($idioma_actual == 'en' ? 'No vehicles registered.' : 'No tienes vehículos registrados.'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2><?php echo ($idioma_actual == 'en' ? 'My Scheduled Appointments' : 'Mis Turnos Agendados'); ?></h2>
            <div class="table-container">
                <?php if (count($reservas) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php echo ($idioma_actual == 'en' ? 'Date' : 'Fecha'); ?></th>
                                <th><?php echo ($idioma_actual == 'en' ? 'Time' : 'Hora'); ?></th>
                                <th><?php echo ($idioma_actual == 'en' ? 'Vehicle' : 'Vehículo'); ?></th>
                                <th><?php echo ($idioma_actual == 'en' ? 'Service' : 'Servicio'); ?></th>
                                <th><?php echo ($idioma_actual == 'en' ? 'Status' : 'Estado'); ?></th>
                                <th><?php echo ($idioma_actual == 'en' ? 'Action' : 'Acción'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $res): 
                                $id_res = $res['id_reserva'] ?? '';
                                $fec = $res['fecha_reserva'] ?? '';
                                $hor = $res['hora_reserva'] ?? '';
                                $veh = trim(($res['marca'] ?? '') . ' ' . ($res['modelo'] ?? '')) ?: 'Vehículo ID: ' . $res['id_vehiculo'];
                                $est = $res['estado'] ?? 'Pendiente';
                                $servicio_reserva = $res['servicio_nombre'] ?? ($idioma_actual == 'en' ? 'Service not specified' : 'Servicio no especificado');
                            ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($id_res); ?></td>
                                    <td><?php echo htmlspecialchars($fec); ?></td>
                                    <td><?php echo htmlspecialchars($hor); ?></td>
                                    <td><?php echo htmlspecialchars($veh); ?></td>
                                    <td><?php echo htmlspecialchars($servicio_reserva); ?></td>
                                    <td><?php echo htmlspecialchars($est); ?></td>
                                    <td>
                                        <form action="reservas.php" method="POST" class="edit-form" onsubmit="return confirm('<?php echo ($idioma_actual == 'en' ? 'Do you want to save these appointment changes?' : '¿Deseas guardar estos cambios del turno?'); ?>');">
                                            <input type="hidden" name="accion_editar_reserva" value="1">
                                            <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($id_res); ?>">
                                            <input type="date" name="fecha_editar" value="<?php echo htmlspecialchars($fec); ?>" required aria-label="<?php echo $idioma_actual == 'en' ? 'Appointment date' : 'Fecha del turno'; ?>">
                                            <input type="time" name="hora_editar" value="<?php echo htmlspecialchars(substr((string) $hor, 0, 5)); ?>" required aria-label="<?php echo $idioma_actual == 'en' ? 'Appointment time' : 'Hora del turno'; ?>">
                                            <select name="id_vehiculo_editar" required aria-label="<?php echo $idioma_actual == 'en' ? 'Vehicle' : 'Vehículo'; ?>">
                                                <?php foreach ($vehiculos as $vehiculo): ?>
                                                    <?php $id_vehiculo_actual = $vehiculo['id_vehiculo'] ?? ''; ?>
                                                    <option value="<?php echo htmlspecialchars($id_vehiculo_actual); ?>" <?php echo ((string) $id_vehiculo_actual === (string) ($res['id_vehiculo'] ?? '')) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars(trim(($vehiculo['marca'] ?? '') . ' ' . ($vehiculo['modelo'] ?? '')) ?: ('ID ' . $id_vehiculo_actual)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select name="id_servicio_editar" required aria-label="<?php echo $idioma_actual == 'en' ? 'Service' : 'Servicio'; ?>">
                                                <?php foreach ($servicios as $servicio): ?>
                                                    <option value="<?php echo htmlspecialchars($servicio['id_servicio']); ?>" <?php echo ((string) ($servicio['id_servicio'] ?? '') === (string) ($res['id_servicio'] ?? '')) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($servicio['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-action btn-edit"><?php echo $idioma_actual == 'en' ? 'Edit' : 'Editar'; ?></button>
                                        </form>
                                        <form action="cancelar_reserva.php" method="POST" onsubmit="return confirm('<?php echo ($idioma_actual == 'en' ? 'Do you want to cancel this appointment?' : '¿Deseas cancelar este turno?'); ?>');" style="margin: 0;">
                                            <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($id_res); ?>">
                                            <button type="submit" class="btn-action btn-cancel"><?php echo ($idioma_actual == 'en' ? 'Cancel' : 'Cancelar'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data"><?php echo ($idioma_actual == 'en' ? 'You have no scheduled appointments yet. Use the form on the left to book one.' : 'No tienes turnos agendados todavía. Utiliza el formulario de la izquierda para agendar uno.'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>