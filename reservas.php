<?php
/**
 * TGS - Taller Global de Servicios
 * Gestión de reservas del cliente.
 *
 * Permite al usuario registrado crear, editar y gestionar sus propias
 * reservas y vehículos. También inicializa catálogo básico de servicios
 * si no existe.
 *
 * Reglas de negocio:
 * - Solo usuarios autenticados (cedula en sesión).
 * - Un cliente solo puede editar/eliminar sus propios vehículos y reservas.
 * - Validación cruzada: al editar, verifica reserva+vehículo+servicio
 *   corresponden al mismo usuario.
 * - Servicios deben estar activos para ser utilizados.
 *
 * Seguridad:
 * - Requiere login.
 * - Consultas preparadas para proteger contra SQL injection.
 * - Validación de formatos (fecha/hora).
 * - Sanitización de salida (htmlspecialchars) en vistas.
 *
 * Dependencias:
 * - conexion.php
 * - lang/es.php y lang/en.php
 */
// Inicio de sesion con cookies protegidas (httponly + SameSite) y aplicacion
// de la matriz de permisos.
require_once 'auth.php';
require_once 'formato.php';
require_once "conexion.php";
require_once 'taller_disponibilidad.php';

// Gestión de idioma: acepta cambio vía GET y persiste en sesión
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] == 'en') ? 'en' : 'es';
}

$idioma_actual = $_SESSION['lang'] ?? 'es';

// Carga traducciones
if ($idioma_actual == 'en') {
    include_once 'lang/en.php';
} else {
    include_once 'lang/es.php';
}

// Protección de acceso: el usuario debe tener el permiso de ver sus turnos.
verificarPermiso('ver_turnos_propios');

// Datos del usuario en sesión
$cedula_usuario = $_SESSION['cedula'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$rol_usuario    = $_SESSION['rol'] ?? 'Cliente';
$mensaje_reserva = '';

// Crea catálogo inicial de servicios si no existe. Esto garantiza opciones
// para reservar aunque el módulo de administración no haya cargado servicios.
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
    // Si la tabla existe con otra estructura, continuamos; la consulta puede fallar
}

// Carga servicios activos para formulario de reserva
$servicios = [];
try {
    $consulta_servicios = $conexion->query('SELECT id_servicio, nombre, descripcion FROM servicio WHERE activo = 1 ORDER BY nombre');
    $servicios = $consulta_servicios ? $consulta_servicios->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $servicios = [];
}

// Control CSRF: se valida una sola vez el token de sesion y todas las
// acciones POST de esta pagina lo exigen. Si el token no es valido, ninguna
// accion se ejecuta y se avisa que la sesion expiro.
$csrf_valido = validarCsrf($_POST['csrf_token'] ?? '');
$error_csrf = $idioma_actual == 'en'
    ? 'The session expired. Please reload the page and try again.'
    : 'La sesión expiró. Recargá la página e intentá nuevamente.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_valido) {
    $mensaje_reserva = "<div class='alert error'>" . $error_csrf . "</div>";
}

// Acción POST: editar reserva propia. Verifica pertenencia de reserva/vehículo
// y que el servicio esté activo para prevenir manipulación de IDs.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_editar_reserva']) && $csrf_valido) {
    $id_reserva_editar = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
    $fecha_editar = trim($_POST['fecha_editar'] ?? '');
    $hora_editar = trim($_POST['hora_editar'] ?? '');
    $vehiculo_editar = filter_input(INPUT_POST, 'id_vehiculo_editar', FILTER_VALIDATE_INT);
    $servicio_editar = filter_input(INPUT_POST, 'id_servicio_editar', FILTER_VALIDATE_INT);
    // Sede: si no se envia, la reserva sigue en la misma.
    $taller_editar = filter_input(INPUT_POST, 'id_taller_editar', FILTER_VALIDATE_INT);

    $fecha_valida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_editar);
    $hora_valida = preg_match('/^\d{2}:\d{2}$/', $hora_editar);

    if ($id_reserva_editar && $vehiculo_editar && $servicio_editar && $fecha_valida && $hora_valida) {
        try {
            // Verificación de propiedad e integridad: reserva, vehículo y servicio
            // deben pertenecer al usuario y servicio debe estar activo.
            $verificar = $conexion->prepare('SELECT r.id_reserva, r.id_taller FROM reserva r INNER JOIN vehiculo v ON v.id_vehiculo = :id_vehiculo AND v.cedula_usuario = :cedula INNER JOIN servicio s ON s.id_servicio = :id_servicio AND s.activo = 1 WHERE r.id_reserva = :id_reserva AND r.cedula_usuario = :cedula_reserva LIMIT 1');
            $verificar->execute([
                'id_vehiculo' => $vehiculo_editar,
                'cedula' => $cedula_usuario,
                'id_servicio' => $servicio_editar,
                'id_reserva' => $id_reserva_editar,
                'cedula_reserva' => $cedula_usuario
            ]);
            $reserva_editar = $verificar->fetch(PDO::FETCH_ASSOC);

            if ($reserva_editar) {
                $sede_destino = $taller_editar ?: (int) ($reserva_editar['id_taller'] ?? 0);

                // La nueva franja tiene que estar disponible en esa sede. La
                // propia reserva no cuenta: se está moviendo, no ocupa dos
                // lugares a la vez.
                $disponibilidad = tgs_comprobar_disponibilidad($conexion, (int) $sede_destino, $fecha_editar, $hora_editar, (int) $id_reserva_editar);

                if (!$disponibilidad['ok']) {
                    $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en'
                        ? 'That time is not available: ' . $disponibilidad['mensaje']
                        : 'Ese horario no está disponible: ' . $disponibilidad['mensaje']) . "</div>";
                } else {
                    # Guarda la nueva fecha, hora, sede, vehículo y servicio
                    $actualizar = $conexion->prepare('UPDATE reserva SET fecha_reserva = :fecha, hora_reserva = :hora, id_vehiculo = :id_vehiculo, id_servicio = :id_servicio, id_taller = :id_taller WHERE id_reserva = :id_reserva AND cedula_usuario = :cedula');
                    $actualizar->execute([
                        'fecha' => $fecha_editar,
                        'hora' => $hora_editar,
                        'id_vehiculo' => $vehiculo_editar,
                        'id_servicio' => $servicio_editar,
                        'id_taller' => (int) $sede_destino,
                        'id_reserva' => $id_reserva_editar,
                        'cedula' => $cedula_usuario
                    ]);
                    $mensaje_reserva = "<div class='alert success'>" . ($idioma_actual == 'en' ? 'Appointment updated successfully.' : '¡Turno actualizado con éxito!') . "</div>";
                }
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

// Acción POST: eliminar vehículo propio
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_eliminar_vehiculo']) && $csrf_valido) {
    $id_vehiculo_eliminar = trim($_POST['id_vehiculo_eliminar'] ?? '');
    if (!empty($id_vehiculo_eliminar)) {
        try {
            $sqlDelVeh = "DELETE FROM vehiculo WHERE id_vehiculo = ? AND cedula_usuario = ?";
            // Borrado restringido a vehículo del usuario autenticado
            $stmtDel = $conexion->prepare($sqlDelVeh);
            $stmtDel->execute([$id_vehiculo_eliminar, $cedula_usuario]);
            $mensaje_reserva = "<div class='alert success'>" . ($idioma_actual == 'en' ? "Vehicle deleted successfully." : "Vehículo eliminado con éxito.") . "</div>";
        } catch (Exception $e) {
            // Mensaje generico, sin $e->getMessage(): el detalle de la
            // excepcion suele incluir SQL o rutas del servidor.
            $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "An error occurred while processing your request." : "Ocurrió un error al procesar tu solicitud.") . "</div>";
        }
    }
}

// Acción POST: editar vehículo propio. Primero se verifica que el vehículo
// pertenezca al usuario de la sesión; recién entonces se actualiza. El WHERE
// del UPDATE también lleva la cédula para impedir editar vehículos ajenos
// manipulando el id desde el formulario.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_editar_vehiculo']) && $csrf_valido) {
    $id_vehiculo_editar = filter_input(INPUT_POST, 'id_vehiculo_editar', FILTER_VALIDATE_INT);
    $marca_editar       = trim($_POST['edit_marca'] ?? '');
    $modelo_editar      = trim($_POST['edit_modelo'] ?? '');
    $anio_editar        = trim($_POST['edit_anio'] ?? '');
    $patente_editar     = trim($_POST['edit_patente'] ?? '');

    if ($id_vehiculo_editar && $marca_editar !== '' && $modelo_editar !== '') {
        try {
            $propietario = $conexion->prepare('SELECT id_vehiculo FROM vehiculo WHERE id_vehiculo = :id_vehiculo AND cedula_usuario = :cedula_usuario LIMIT 1');
            $propietario->execute([
                'id_vehiculo' => $id_vehiculo_editar,
                'cedula_usuario' => $cedula_usuario
            ]);

            if ($propietario->fetchColumn()) {
                $anio_valor = ctype_digit($anio_editar) ? (int) $anio_editar : null;
                $actualizar_vehiculo = $conexion->prepare('UPDATE vehiculo SET patente = :patente, marca = :marca, modelo = :modelo, anio = :anio WHERE id_vehiculo = :id_vehiculo AND cedula_usuario = :cedula_usuario');
                $actualizar_vehiculo->execute([
                    'patente' => $patente_editar !== '' ? $patente_editar : 'SIN-PATENTE',
                    'marca' => $marca_editar,
                    'modelo' => $modelo_editar,
                    'anio' => $anio_valor,
                    'id_vehiculo' => $id_vehiculo_editar,
                    'cedula_usuario' => $cedula_usuario
                ]);
                $mensaje_reserva = "<div class='alert success'>" . ($idioma_actual == 'en' ? 'Vehicle updated successfully.' : 'Vehículo actualizado con éxito.') . "</div>";
            } else {
                $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? 'You can only edit your own vehicles.' : 'Solo puedes editar tus propios vehículos.') . "</div>";
            }
        } catch (Exception $e) {
            // Mensaje generico: no se expone el detalle interno de la excepcion.
            $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? 'The vehicle could not be updated.' : 'No se pudo actualizar el vehículo.') . "</div>";
        }
    } else {
        $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? 'Please complete the brand and model.' : 'Por favor completa la marca y modelo.') . "</div>";
    }
}

// Acción POST: crear nueva reserva. Maneja vehículo nuevo o existente,
// valida servicio activo y campos obligatorios.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_reserva']) && $csrf_valido) {
    $fecha       = trim($_POST['fecha'] ?? '');
    $hora        = trim($_POST['hora'] ?? '');
    $id_servicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);
    // Sede donde se va a hacer el trabajo. Antes era siempre la primera;
    // ahora el cliente elige y se valida la disponibilidad de esa sede.
    $id_taller = filter_input(INPUT_POST, 'id_taller', FILTER_VALIDATE_INT) ?: 1;
    $estado      = 'Pendiente';

    $seleccion_vehiculo = $_POST['id_vehiculo'] ?? '';
    $id_vehiculo = '';

    if (!empty($cedula_usuario) && !empty($fecha) && $fecha != '0000-00-00' && !empty($hora) && $id_servicio) {
        try {
            // Opción: registrar vehículo nuevo antes de crear la reserva
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

            // Verifica que el servicio elegido esté activo antes de insertar reserva.
            // Se recupera tambien el precio para congelarlo en la reserva
            // (precio_total): si el catalogo cambia despues, el cliente
            // sigue debiendo lo que acepto al reservar.
            $precio_servicio = null;
            if (!empty($id_vehiculo) && empty($mensaje_reserva)) {
                $validar_servicio = $conexion->prepare('SELECT id_servicio, precio FROM servicio WHERE id_servicio = ? AND activo = 1 LIMIT 1');
                $validar_servicio->execute([$id_servicio]);
                $servicio_elegido = $validar_servicio->fetch(PDO::FETCH_ASSOC);
                if (!$servicio_elegido) {
                    $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "Select a valid service." : "Selecciona un servicio válido.") . "</div>";
                } else {
                    $precio_servicio = (float) ($servicio_elegido['precio'] ?? 0);
                }
            }

            // Comprueba que la sede atienda ese dia y que la franja tenga
            // lugar libre. Se valida siempre en el servidor: el JavaScript
            // solo informa al cliente, no decide.
            if (!empty($id_vehiculo) && empty($mensaje_reserva)) {
                $comprobar_disponibilidad = tgs_comprobar_disponibilidad($conexion, (int) $id_taller, $fecha, $hora);
                if (!$comprobar_disponibilidad['ok']) {
                    $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en'
                        ? 'That slot is not available: ' . $comprobar_disponibilidad['mensaje']
                        : 'Ese horario no está disponible: ' . $comprobar_disponibilidad['mensaje']) . "</div>";
                }
            }

            if (!empty($id_vehiculo) && empty($mensaje_reserva)) {
                // La reserva nace con el precio congelado y el cobro pendiente.
                $sqlIns = "INSERT INTO reserva (cedula_usuario, id_vehiculo, id_taller, id_servicio, fecha_reserva, hora_reserva, estado, precio_total, estado_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtIns = $conexion->prepare($sqlIns);
                $stmtIns->execute([$cedula_usuario, $id_vehiculo, $id_taller, $id_servicio, $fecha, $hora, $estado, $precio_servicio, 'pendiente']);

                $mensaje_reserva = "<div class='alert success'>" . ($idioma_actual == 'en' ? "Appointment successfully booked!" : "¡Turno reservado con éxito!") . "</div>";
            } else if (empty($mensaje_reserva)) {
                $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "You must select or enter a valid vehicle." : "Debe seleccionar o ingresar un vehículo válido.") . "</div>";
            }
        } catch (Exception $e) {
            // Mensaje generico, sin $e->getMessage(): el detalle de la
            // excepcion suele incluir SQL o rutas del servidor.
            $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "An error occurred while processing your request." : "Ocurrió un error al procesar tu solicitud.") . "</div>";
        }
    } else {
        $mensaje_reserva = "<div class='alert error'>" . ($idioma_actual == 'en' ? "Please select a valid date and fill in all required fields." : "Por favor selecciona una fecha válida y completa todos los campos obligatorios.") . "</div>";
    }
}

// Carga reservas del usuario actual (solo propias). JOIN con vehículo y servicio.
// El total pagado se calcula con una subconsulta sobre la tabla de cobros.
$reservas = [];
try {
    $sql = "SELECT r.*, v.modelo, v.marca, s.nombre AS servicio_nombre, (SELECT COALESCE(SUM(p.monto), 0) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado') AS total_pagado FROM reserva r LEFT JOIN vehiculo v ON r.id_vehiculo = v.id_vehiculo LEFT JOIN servicio s ON r.id_servicio = s.id_servicio WHERE r.cedula_usuario = ? ORDER BY r.id_reserva DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$cedula_usuario]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reservas = [];
}

// Carga vehículos del usuario actual
$vehiculos = [];
try {
    $sqlVeh = "SELECT * FROM vehiculo WHERE cedula_usuario = ?";
    $stmtVeh = $conexion->prepare($sqlVeh);
    $stmtVeh->execute([$cedula_usuario]);
    $vehiculos = $stmtVeh->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vehiculos = [];
}

// Sedes disponibles para reservar, con su horario semanal y capacidad.
$talleres_disponibles = [];
try {
    $sqlTall = "SELECT id_taller, nombre, direccion, capacidad FROM taller ORDER BY nombre";
    $stmtTall = $conexion->query($sqlTall);
    $talleres_disponibles = $stmtTall ? $stmtTall->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $talleres_disponibles = [];
}

// Horarios de atencion de cada sede (para mostrarlos junto al selector).
$horarios_por_taller = [];
try {
    $stmtHor = $conexion->query('SELECT id_taller, dia_semana, hora_inicio, hora_fin, activo FROM taller_horario ORDER BY dia_semana');
    foreach ($stmtHor->fetchAll(PDO::FETCH_ASSOC) as $fila_horario) {
        $horarios_por_taller[(int) $fila_horario['id_taller']][(int) $fila_horario['dia_semana']] = $fila_horario;
    }
} catch (Exception $e) {
    $horarios_por_taller = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_actual; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
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
    <link rel="stylesheet" href="css/base-tgs.css">
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
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
    <script src="js/fecha.js" defer></script>
</head>
<body>
    <?php
# Cabecera comun del sitio: logo, navegacion del rol, idioma y salir.
$tgs_volver = 'panel_cliente.php';
include 'includes/cabecera.php';
?>

    <div class="main-container">
        <div class="card">
            <h2><?php echo ($idioma_actual == 'en' ? 'Book Appointment' : 'Reservar Turno'); ?></h2>
            <?php echo $mensaje_reserva; ?>
            <form action="reservas.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="accion_reserva" value="1">
                
                <div class="form-group">
                    <label for="id_servicio"><?php echo ($idioma_actual == 'en' ? 'Service' : 'Servicio'); ?></label>
                    <select name="id_servicio" id="id_servicio" required>
                        <option value=""><?php echo ($idioma_actual == 'en' ? 'Select a service' : 'Selecciona un servicio'); ?></option>
                        <?php foreach ($servicios as $servicio): ?>
                            <option value="<?php echo htmlspecialchars($servicio['id_servicio']); ?>">
                                <?php echo htmlspecialchars(tgs_traducir($servicio['nombre'])); ?>
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
                    <label for="id_taller"><?php echo ($idioma_actual == 'en' ? 'Workshop' : 'Sede'); ?></label>
                    <select name="id_taller" id="id_taller" onchange="cargarDisponibilidad()">
                        <?php foreach ($talleres_disponibles as $taller_opcion):
                            $taller_id = (int) ($taller_opcion['id_taller'] ?? 0);
                            $taller_horario = $horarios_por_taller[$taller_id] ?? [];
                            $resumen_horario = ($idioma_actual == 'en') ? 'Mon-Fri' : 'Lun-Sab';
                            if (!empty($taller_horario[1])) {
                                $resumen_horario = substr((string) $taller_horario[1]['hora_inicio'], 0, 5) . ' - ' . substr((string) $taller_horario[1]['hora_fin'], 0, 5);
                            }
                        ?>
                            <option value="<?php echo $taller_id; ?>" data-nombre="<?php echo htmlspecialchars(tgs_traducir($taller_opcion['nombre'] ?? '')); ?>">
                                <?php echo htmlspecialchars(tgs_traducir($taller_opcion['nombre'] ?? '') . ' · ' . $resumen_horario . ' · ' . tgs_traducir($taller_opcion['direccion'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="service-description"><?php echo ($idioma_actual == 'en' ? 'Choose the workshop where the work will be done.' : 'Elegí la sede donde se va a hacer el trabajo.'); ?></span>
                </div>

                <div class="form-group">
                    <label for="fecha"><?php echo ($idioma_actual == 'en' ? 'Date' : 'Fecha'); ?></label>
                    <?php echo tgs_campo_fecha('fecha', '', $idioma_actual, array('required' => true, 'id' => 'fecha', 'attrs' => 'onchange="cargarDisponibilidad()"')); ?>
                    <small style="display:block; margin-top:6px; font-size:12px; color:#94a3b8;">
                        <?php echo ($idioma_actual == 'en' ? 'Format: month/day/year (the site shows dates as ' . date('m/d/Y') . ').' : 'Formato: dia/mes/año (el sitio muestra las fechas como ' . date('d/m/Y') . ').'); ?>
                    </small>
                </div>

                <div class="form-group">
                    <label for="hora"><?php echo ($idioma_actual == 'en' ? 'Time' : 'Hora'); ?></label>
                    <input type="time" name="hora" id="hora" required>
                    <span class="service-description" id="disponibilidad-mensaje"><?php echo ($idioma_actual == 'en' ? 'Pick a date to see the available times.' : 'Elegí una fecha para ver los horarios disponibles.'); ?></span>
                </div>

                <div class="form-group" id="bloque-horarios" style="display: none;">
                    <label><?php echo ($idioma_actual == 'en' ? 'Available times' : 'Horarios disponibles'); ?></label>
                    <div id="horarios-libres" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
                </div>

                <button type="submit" class="btn-submit"><?php echo ($idioma_actual == 'en' ? 'Confirm Appointment' : 'Confirmar Reserva'); ?></button>
            </form>

            <div style="margin-top: 30px; border-top: 1px solid #334155; padding-top: 20px;" id="mis-vehiculos">
                <h3 style="font-size: 16px; color: #38bdf8; margin-bottom: 15px;"><?php echo ($idioma_actual == 'en' ? 'My Registered Vehicles' : 'Mis Vehículos Registrados'); ?></h3>
                <?php if (count($vehiculos) > 0): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($vehiculos as $v): ?>
                            <li style="display: flex; justify-content: space-between; align-items: center; gap: 8px; background: rgba(15, 23, 42, 0.6); padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; font-size: 13px; border: 1px solid #334155;">
                                <span style="color: #cbd5e1;">🚗 <strong><?php echo htmlspecialchars(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '')); ?></strong> <span style="color: #94a3b8; font-size: 11px;">(<?php echo htmlspecialchars($v['patente'] ?? 'Sin patente'); ?>)</span></span>
                                <span style="display: flex; gap: 6px;">
                                    <button type="button" class="btn-editar-vehiculo" style="background: #2563eb; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;"
                                        data-id="<?php echo htmlspecialchars($v['id_vehiculo'] ?? ''); ?>"
                                        data-marca="<?php echo htmlspecialchars($v['marca'] ?? ''); ?>"
                                        data-modelo="<?php echo htmlspecialchars($v['modelo'] ?? ''); ?>"
                                        data-anio="<?php echo htmlspecialchars($v['anio'] ?? ''); ?>"
                                        data-patente="<?php echo htmlspecialchars($v['patente'] ?? ''); ?>">
                                        <?php echo ($idioma_actual == 'en' ? 'Edit' : 'Editar'); ?>
                                    </button>
                                <form action="reservas.php" method="POST" onsubmit="return confirm('<?php echo ($idioma_actual == 'en' ? 'Do you want to delete this vehicle?' : '¿Deseas eliminar este vehículo?'); ?>');" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                    <input type="hidden" name="accion_eliminar_vehiculo" value="1">
                                        <input type="hidden" name="id_vehiculo_eliminar" value="<?php echo htmlspecialchars($v['id_vehiculo'] ?? ''); ?>">
                                        <button type="submit" style="background: #dc2626; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                                            <?php echo ($idioma_actual == 'en' ? 'Delete' : 'Eliminar'); ?>
                                        </button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div id="panel-editar-vehiculo" style="display: none; margin-top: 12px; background: rgba(15, 23, 42, 0.85); border: 1px solid #2563eb; border-radius: 8px; padding: 12px;">
                        <h4 style="font-size: 13px; color: #38bdf8; margin-bottom: 10px;"><?php echo ($idioma_actual == 'en' ? 'Edit vehicle' : 'Editar vehículo'); ?></h4>
                        <form action="reservas.php" method="POST" id="form-editar-vehiculo">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="accion_editar_vehiculo" value="1">
                            <input type="hidden" name="id_vehiculo_editar" id="edit_id_vehiculo">
                            <div class="form-group">
                                <label for="edit_marca"><?php echo ($idioma_actual == 'en' ? 'Brand' : 'Marca'); ?></label>
                                <input type="text" name="edit_marca" id="edit_marca" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_modelo"><?php echo ($idioma_actual == 'en' ? 'Model' : 'Modelo'); ?></label>
                                <input type="text" name="edit_modelo" id="edit_modelo" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_anio"><?php echo ($idioma_actual == 'en' ? 'Year' : 'Año'); ?></label>
                                <input type="number" name="edit_anio" id="edit_anio">
                            </div>
                            <div class="form-group">
                                <label for="edit_patente"><?php echo ($idioma_actual == 'en' ? 'License Plate' : 'Patente'); ?></label>
                                <input type="text" name="edit_patente" id="edit_patente">
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="submit" class="btn-submit" style="margin-top: 0;"><?php echo ($idioma_actual == 'en' ? 'Save changes' : 'Guardar cambios'); ?></button>
                                <button type="button" onclick="cancelarEdicionVehiculo()" style="background: #475569; color: white; border: none; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;"><?php echo ($idioma_actual == 'en' ? 'Cancel' : 'Cancelar'); ?></button>
                            </div>
                        </form>
                    </div>
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
                                <th><?php echo ($idioma_actual == 'en' ? 'Payment' : 'Pago'); ?></th>
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
                                $servicio_reserva = tgs_traducir($res['servicio_nombre'] ?? ($idioma_actual == 'en' ? 'Service not specified' : 'Servicio no especificado'));
                                // Estado del cobro de este turno: pendiente, parcial o pagado.
                                $estado_pago = strtolower(trim((string) ($res['estado_pago'] ?? 'pendiente')));
                                $precio_total = $res['precio_total'] ?? null;
                                $total_pagado = (float) ($res['total_pagado'] ?? 0);
                                $saldo = ($precio_total !== null) ? (float) $precio_total - $total_pagado : null;
                                $etiquetas_pago = [
                                    'pagado' => $idioma_actual == 'en' ? 'Paid' : 'Pagado',
                                    'parcial' => $idioma_actual == 'en' ? 'Partial' : 'Parcial',
                                    'pendiente' => $idioma_actual == 'en' ? 'Pending' : 'Pendiente'
                                ];
                                $etiqueta_pago = $etiquetas_pago[$estado_pago] ?? $etiquetas_pago['pendiente'];
                            ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($id_res); ?></td>
                                    <td><?php echo htmlspecialchars(tgs_fecha_legible($fec, $idioma_actual)); ?></td>
                                    <td><?php echo htmlspecialchars($hor); ?></td>
                                    <td><?php echo htmlspecialchars($veh); ?></td>
                                    <td><?php echo htmlspecialchars($servicio_reserva); ?></td>
                                    <td><?php echo htmlspecialchars(tgs_traducir($est)); ?></td>
                                    <td>
                                        <span style="font-size: 12px; color: #cbd5e1; display: block;"><?php echo htmlspecialchars($etiqueta_pago); ?></span>
                                        <?php if ($precio_total !== null): ?>
                                            <span style="font-size: 11px; color: #94a3b8;">
                                                <?php echo htmlspecialchars(number_format((float) $precio_total, 2, ',', '.')); ?>
                                                <?php echo ($idioma_actual == 'en' ? 'USD' : 'USD'); ?>
                                                <?php if ($saldo > 0.009): ?>
                                                    &middot; <?php echo ($idioma_actual == 'en' ? 'due' : 'debe'); ?> <?php echo htmlspecialchars(number_format($saldo, 2, ',', '.')); ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="reservas.php" method="POST" class="edit-form" onsubmit="return confirm('<?php echo ($idioma_actual == 'en' ? 'Do you want to save these appointment changes?' : '¿Deseas guardar estos cambios del turno?'); ?>');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion_editar_reserva" value="1">
                                            <input type="hidden" name="id_reserva" value="<?php echo htmlspecialchars($id_res); ?>">
                                            <?php echo tgs_campo_fecha('fecha_editar', htmlspecialchars($fec), $idioma_actual, array('required' => true, 'aria' => ($idioma_actual == 'en' ? 'Appointment date' : 'Fecha del turno'))); ?>
                                            <input type="time" name="hora_editar" value="<?php echo htmlspecialchars(substr((string) $hor, 0, 5)); ?>" required aria-label="<?php echo $idioma_actual == 'en' ? 'Appointment time' : 'Hora del turno'; ?>">
                                            <select name="id_taller_editar" required aria-label="<?php echo $idioma_actual == 'en' ? 'Workshop' : 'Sede'; ?>">
                                                <?php foreach ($talleres_disponibles as $taller_edicion):
                                                    $taller_ed_id = (int) ($taller_edicion['id_taller'] ?? 0);
                                                    $taller_ed_actual = (int) ($res['id_taller'] ?? 0);
                                                ?>
                                                    <option value="<?php echo $taller_ed_id; ?>" <?php echo ($taller_ed_actual === $taller_ed_id) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars(tgs_traducir($taller_edicion['nombre'] ?? '')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
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
                                                        <?php echo htmlspecialchars(tgs_traducir($servicio['nombre'])); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-action btn-edit"><?php echo $idioma_actual == 'en' ? 'Edit' : 'Editar'; ?></button>
                                        </form>
                                        <form action="cancelar_reserva.php" method="POST" onsubmit="return confirm('<?php echo ($idioma_actual == 'en' ? 'Do you want to cancel this appointment?' : '¿Deseas cancelar este turno?'); ?>');" style="margin: 0;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
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

    <?php include 'includes/pie.php'; ?>
    <script>
        // Consulta los horarios libres de la sede y la fecha elegidas y los
        // ofrece como botones. Es solo una ayuda para el cliente: la
        // validacion definitiva la hace el servidor al guardar el turno.
        function cargarDisponibilidad() {
            var taller = document.getElementById('id_taller');
            var fecha = document.getElementById('fecha');
            var mensaje = document.getElementById('disponibilidad-mensaje');
            var bloque = document.getElementById('bloque-horarios');
            var contenedor = document.getElementById('horarios-libres');

            if (!taller || !fecha || !mensaje || !bloque || !contenedor) {
                return;
            }

            if (!fecha.value) {
                mensaje.textContent = '<?php echo ($idioma_actual == 'en' ? 'Pick a date to see the available times.' : 'Elegí una fecha para ver los horarios disponibles.'); ?>';
                bloque.style.display = 'none';
                return;
            }

            var url = 'disponibilidad.php?taller=' + encodeURIComponent(taller.value) + '&fecha=' + encodeURIComponent(fecha.value);
            fetch(url)
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (datos) {
                    mensaje.textContent = datos.mensaje || '';
                    contenedor.innerHTML = '';

                    if (!datos.ok || !datos.libres || datos.libres.length === 0) {
                        bloque.style.display = 'none';
                        return;
                    }

                    datos.libres.forEach(function (horario) {
                        var boton = document.createElement('button');
                        boton.type = 'button';
                        boton.textContent = horario;
                        boton.style.background = 'rgba(56, 189, 248, 0.15)';
                        boton.style.color = '#38bdf8';
                        boton.style.border = '1px solid rgba(56, 189, 248, 0.4)';
                        boton.style.borderRadius = '6px';
                        boton.style.padding = '6px 12px';
                        boton.style.fontSize = '13px';
                        boton.style.cursor = 'pointer';
                        boton.addEventListener('click', function () {
                            document.getElementById('hora').value = horario;
                        });
                        contenedor.appendChild(boton);
                    });

                    bloque.style.display = 'block';
                })
                .catch(function () {
                    mensaje.textContent = '<?php echo ($idioma_actual == 'en' ? 'The availability could not be loaded.' : 'No se pudo consultar la disponibilidad.'); ?>';
                    bloque.style.display = 'none';
                });
        }
    </script>
    <script>
        // Rellena y muestra el panel de edicion con los datos del vehiculo
        // elegido. Se asignan con .value y se leen de data-* para no inyectar
        // valores dentro del codigo JavaScript (evita romper el script o XSS).
        function editarVehiculo(datos) {
            document.getElementById('edit_id_vehiculo').value = datos.id;
            document.getElementById('edit_marca').value = datos.marca;
            document.getElementById('edit_modelo').value = datos.modelo;
            document.getElementById('edit_anio').value = datos.anio;
            document.getElementById('edit_patente').value = datos.patente;
            var panel = document.getElementById('panel-editar-vehiculo');
            panel.style.display = 'block';
            panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function cancelarEdicionVehiculo() {
            document.getElementById('panel-editar-vehiculo').style.display = 'none';
        }

        // Un unico listener por boton de edicion; los datos viajan por data-*.
        document.querySelectorAll('.btn-editar-vehiculo').forEach(function (boton) {
            boton.addEventListener('click', function () {
                editarVehiculo({
                    id: boton.dataset.id,
                    marca: boton.dataset.marca,
                    modelo: boton.dataset.modelo,
                    anio: boton.dataset.anio,
                    patente: boton.dataset.patente
                });
            });
        });
    </script>
</body>
</html>
