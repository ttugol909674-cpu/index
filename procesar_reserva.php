<?php
/**
 * TGS - Taller Global de Servicios
 * Punto de entrada legado de reservas.
 *
 * HISTORICO: este archivo era el que validaba y guardaba una reserva. Hoy la
 * logica vive en reservas.php y este script solo delega en esa vista. Por eso
 * TODO el codigo que sigue despues del require/exit es codigo muerto: se
 * conserva solo como referencia y nunca se ejecuta.
 *
 * Acceso: el que aplique reservas.php (usuario logueado). Aqui no se valida
 * rol porque la ejecucion se delega de inmediato.
 *
 * Depende de: reservas.php, que a su vez carga conexion.php y la sesion.
 */

// Inicia la sesión para identificar al usuario que está reservando.
session_start();

// Conserva el idioma elegido y reenvia a la vista de reservas vigente,
// que ya incluye textos en ambos idiomas.
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'en' ? 'en' : 'es';
}
require 'reservas.php';
exit; // Corta aqui: nada de lo que sigue se ejecuta (codigo legado).

// ---------------------------------------------------------------------------
// A partir de este punto: codigo legado inalcanzable, conservado solo como
// referencia de la antigua logica de reservas.
// ---------------------------------------------------------------------------

// Conexión a la base de datos para guardar la reserva y manejar vehículos.
require_once "conexion.php";

// Seguridad: si no hay usuario logueado, se va al login.
if (!isset($_SESSION['cedula'])) {
    header('Location: login.php');
    exit;
}

// Datos básicos del usuario para mostrar en la pantalla.
$cedula_usuario = $_SESSION['cedula'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$mensaje_reserva = '';

// Procesa el formulario de reserva y guarda la información en la base de datos.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_reserva'])) {
    $matriculaOption = trim($_POST['matricula_option'] ?? '');
    $fecha           = trim($_POST['fecha'] ?? '');
    $hora            = trim($_POST['hora'] ?? '');
    $matriculaFinal  = "";

    // Si el usuario eligio registrar un vehiculo nuevo se hace upsert;
    // si no, se reutiliza la matricula ya seleccionada del listado.
    if ($matriculaOption === "nuevo") {
        $nuevaMatricula = strtoupper(trim($_POST['nueva_matricula'] ?? ''));
        $marca          = trim($_POST['marca'] ?? '');
        $modelo         = trim($_POST['modelo'] ?? '');

        if (!empty($nuevaMatricula)) {
            // Inserta el vehiculo o, si la matricula ya existia, actualiza marca
            // y modelo. Asi el usuario no duplica vehiculos al reservar.
            $sqlAuto = "INSERT INTO vehiculo (Matricula, Marca, Modelo, Cedula_Usuario)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE Marca = VALUES(Marca), Modelo = VALUES(Modelo)";
            $stmtAuto = $conexion->prepare($sqlAuto);
            if ($stmtAuto) {
                $stmtAuto->execute([$nuevaMatricula, $marca, $modelo, $cedula_usuario]);
            }
            $matriculaFinal = $nuevaMatricula;
        } else {
            $mensaje_reserva = "<div class='alert error'>Debes especificar la matrícula del vehículo.</div>";
        }
    } else {
        $matriculaFinal = $matriculaOption;
    }

    // Solo guarda la reserva si no hubo error y estan todos los datos.
    if (empty($mensaje_reserva) && !empty($cedula_usuario) && !empty($matriculaFinal) && !empty($fecha) && !empty($hora)) {
        // Inserta la reserva asociada al usuario de la sesion.
        $sqlIns = "INSERT INTO reserva (Fecha, Hora, Matricula_Vehiculo, Cedula_Usuario) VALUES (?, ?, ?, ?)";
        $stmtIns = $conexion->prepare($sqlIns);
        if ($stmtIns) {
            $stmtIns->execute([$fecha, $hora, $matriculaFinal, $cedula_usuario]);
            $mensaje_reserva = "<div class='alert success'>¡Turno reservado con éxito en la base de datos!</div>";
        }
    }
}

// Consulta las reservas del usuario. Se prueban las dos variantes de nombre de
// columna porque el esquema antiguo y el nuevo no coinciden en mayusculas.
$sql = "SELECT * FROM reserva WHERE Cedula_Usuario = :cedula OR cedula_usuario = :cedula ORDER BY id_reserva DESC";
$stmt = $conexion->prepare($sql);
$stmt->execute(['cedula' => $cedula_usuario]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta los vehículos del usuario, con la misma tolerancia de mayusculas.
$sqlVeh = "SELECT * FROM vehiculo WHERE Cedula_Usuario = :cedula OR cedula_usuario = :cedula";
$stmtVeh = $conexion->prepare($sqlVeh);
$stmtVeh->execute(['cedula' => $cedula_usuario]);
$vehiculos = $stmtVeh->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TGS - Panel de Turnos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; width: 100%; z-index: 1000; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 20px; align-items: center; }
        nav span { color: #94a3b8; font-size: 14px; }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.3s; }
        nav a.btn-logout:hover { background-color: #dc2626; }
        
        .main-container { flex: 1; padding: 40px 20px; max-width: 1300px; width: 100%; margin: 0 auto; display: grid; grid-template-columns: 400px 1fr; gap: 30px; }
        @media (max-width: 900px) { .main-container { grid-template-columns: 1fr; } }

        .card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); }
        .card h2 { font-size: 20px; color: #38bdf8; margin-bottom: 15px; }

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

        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; text-align: center; }
        .alert.success { background-color: rgba(22, 163, 74, 0.2); border: 1px solid #16a34a; color: #4ade80; }
        .alert.error { background-color: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
        .no-data { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; }

        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <script>
        function toggleNuevoVehiculo(val) {
            document.getElementById('campos_nuevo_vehiculo').style.display = (val === 'nuevo') ? 'block' : 'none';
        }
    </script>
</head>
<body>
    <header>
        <!-- Reemplaza 'index.php' por el nombre exacto de tu archivo de inicio si se llama diferente -->
        <a href="index.php" class="logo">TGS - Panel</a>
        <nav>
            <a href="index.php" class="nav-link">Inicio</a>
            <!-- Ajusta estos nombres según cómo se llamen tus archivos reales en la carpeta tgs (ej: servicios.php o index.php#servicios) -->
            <a href="index.php" class="nav-link">Servicios</a>
            <a href="index.php" class="nav-link">Sobre nosotros</a>
            <a href="reservas.php" class="nav-link" style="color: #38bdf8; font-weight: bold;">Mis Turnos</a>
            <span>Hola, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <div class="main-container">
        <!-- COLUMNA IZQUIERDA: Agendar Turno Nuevo -->
        <div class="card">
            <h2>Reservar Turno</h2>
            <?php echo $mensaje_reserva; ?>
            <form action="reservas.php" method="POST">
                <input type="hidden" name="accion_reserva" value="1">
                
                <div class="form-group">
                    <label for="matricula_option">Vehículo</label>
                    <select name="matricula_option" id="matricula_option" onchange="toggleNuevoVehiculo(this.value)" required>
                        <option value="">Selecciona un vehículo</option>
                        <?php foreach ($vehiculos as $v): 
                            $mat = $v['Matricula'] ?? $v['matricula'] ?? '';
                            $mar = $v['Marca'] ?? $v['marca'] ?? '';
                            $mod = $v['Modelo'] ?? $v['modelo'] ?? '';
                        ?>
                            <option value="<?php echo htmlspecialchars($mat); ?>">
                                <?php echo htmlspecialchars($mat . ($mar ? ' - ' . $mar : '') . ($mod ? ' ' . $mod : '')); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="nuevo">+ Registrar nuevo vehículo</option>
                    </select>
                </div>

                <div id="campos_nuevo_vehiculo" style="display: none;">
                    <div class="form-group">
                        <label for="nueva_matricula">Matrícula</label>
                        <input type="text" name="nueva_matricula" id="nueva_matricula" placeholder="Ej: ABC1234">
                    </div>
                    <div class="form-group">
                        <label for="marca">Marca</label>
                        <input type="text" name="marca" id="marca" placeholder="Ej: Toyota">
                    </div>
                    <div class="form-group">
                        <label for="modelo">Modelo</label>
                        <input type="text" name="modelo" id="modelo" placeholder="Ej: Corolla">
                    </div>
                </div>

                <div class="form-group">
                    <label for="fecha">Fecha</label>
                    <input type="date" name="fecha" id="fecha" required>
                </div>

                <div class="form-group">
                    <label for="hora">Hora</label>
                    <input type="time" name="hora" id="hora" required>
                </div>

                <button type="submit" class="btn-submit">Confirmar Reserva</button>
            </form>
        </div>

        <!-- COLUMNA DERECHA: Listado de Turnos y Cancelación -->
        <div class="card">
            <h2>Mis Turnos Agendados</h2>
            <div class="table-container">
                <?php if (count($reservas) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Vehículo</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $res): 
                                $id_res = $res['id_reserva'] ?? $res['ID_Reserva'] ?? '';
                                $fec = $res['Fecha'] ?? $res['fecha'] ?? '';
                                $hor = $res['Hora'] ?? $res['hora'] ?? '';
                                $mat_veh = $res['Matricula_Vehiculo'] ?? $res['matricula_vehiculo'] ?? '';
                            ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($id_res); ?></td>
                                    <td><?php echo htmlspecialchars($fec); ?></td>
                                    <td><?php echo htmlspecialchars($hor); ?></td>
                                    <td><?php echo htmlspecialchars($mat_veh); ?></td>
                                    <td>
                                        <form action="cancelar_reserva.php" method="POST" onsubmit="return confirm('<?php echo ($lang_code === 'en') ? 'Do you want to cancel this appointment and delete it from the database?' : '¿Deseas cancelar este turno y eliminarlo de la base de datos?'; ?>');" style="margin: 0;">
                                            <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($id_res); ?>">
                                            <button type="submit" class="btn-action btn-cancel">Cancelar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No tienes turnos agendados todavía. Utiliza el formulario de la izquierda para agendar uno.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
