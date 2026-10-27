<?php
/**
 * TGS - Taller Global de Servicios
 * Administracion del catalogo de servicios.
 *
 * Permite crear, editar, activar/desactivar y eliminar los servicios que los
 * clientes pueden elegir al reservar un turno. Es una pantalla privada.
 *
 * Acceso: solo administradores y gestores (verificarRol).
 *
 * Depende de: auth.php (sesion y permisos) y conexion.php (PDO a la base de
 * datos). La tabla servicio se crea automaticamente si no existe.
 */

// Autorizacion: corta la ejecucion y redirige si el rol no esta permitido.
require_once 'auth.php';
verificarPermiso('administrar_servicios');
require_once 'conexion.php';

// Guarda el idioma elegido para mantener la pantalla bilingüe.
// Se restringe a en/es para no ensuciar la sesion con valores arbitrarios.
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'en' ? 'en' : 'es';
}
$lang_code = $_SESSION['lang'] ?? 'es';
// El rol solo decide a que panel vuelve el boton "Volver".
$rol_actual = normalizarRol($_SESSION['rol'] ?? '');
$texto = $lang_code === 'en' ? [
    'titulo' => 'Service Management', 'listado' => 'Registered services', 'inicio' => 'Back to Home', 'panel' => 'Back to Panel', 'hola' => 'Hello', 'salir' => 'Sign Out',
    'descripcion' => 'Create and update the services available when customers book an appointment.', 'nuevo' => 'Add service',
    'editar' => 'Edit service', 'nombre' => 'Service name', 'detalle' => 'Description', 'precio' => 'Price', 'guardar' => 'Save service',
    'cancelar' => 'Cancel', 'acciones' => 'Actions', 'activo' => 'Active', 'inactivo' => 'Inactive', 'editar_btn' => 'Edit',
    'desactivar' => 'Deactivate', 'activar' => 'Activate', 'eliminar' => 'Delete', 'sin_servicios' => 'No services registered.',
    'creado' => 'Service created successfully.', 'actualizado' => 'Service updated successfully.', 'cambiado' => 'Service status updated.',
    'eliminado' => 'Service deleted successfully.', 'error' => 'The operation could not be completed.', 'usado' => 'This service has reservations and cannot be deleted. Deactivate it instead.',
    'confirmar' => 'Do you want to delete this service?', 'moneda' => 'Price in pesos'
] : [
    'titulo' => 'Administración de Servicios', 'listado' => 'Servicios registrados', 'inicio' => 'Volver al Inicio', 'panel' => 'Volver al Panel', 'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'descripcion' => 'Crea y actualiza los servicios disponibles cuando los clientes reservan un turno.', 'nuevo' => 'Agregar servicio',
    'editar' => 'Editar servicio', 'nombre' => 'Nombre del servicio', 'detalle' => 'Descripción', 'precio' => 'Precio', 'guardar' => 'Guardar servicio',
    'cancelar' => 'Cancelar', 'acciones' => 'Acciones', 'activo' => 'Activo', 'inactivo' => 'Inactivo', 'editar_btn' => 'Editar',
    'desactivar' => 'Desactivar', 'activar' => 'Activar', 'eliminar' => 'Eliminar', 'sin_servicios' => 'No hay servicios registrados.',
    'creado' => 'Servicio creado correctamente.', 'actualizado' => 'Servicio actualizado correctamente.', 'cambiado' => 'Estado del servicio actualizado.',
    'eliminado' => 'Servicio eliminado correctamente.', 'error' => 'No se pudo completar la operación.', 'usado' => 'Este servicio tiene reservas y no se puede eliminar. Desactívalo en su lugar.',
    'confirmar' => '¿Deseas eliminar este servicio?', 'moneda' => 'Precio en pesos'
];
$mensaje = '';
$tipo_mensaje = 'success';

// Crea la tabla si todavía no existe en la instalación actual. Es idempotente:
// permite habilitar el modulo sin ejecutar SQL a mano. Si falla (por permisos
// o base sin privilegios) se avisa por pantalla en vez de romper la pagina.
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS servicio (
        id_servicio INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        descripcion VARCHAR(255) NULL,
        precio DECIMAL(10,2) DEFAULT 0,
        activo TINYINT(1) DEFAULT 1
    )");
} catch (PDOException $e) {
    $mensaje = $texto['error'];
    $tipo_mensaje = 'error';
}

// Procesa altas, ediciones y cambios de estado.
// Todas las sentencias usan marcadores (consultas preparadas) para que los
// datos del formulario nunca se concatenen directamente en el SQL.
// El token CSRF se valida antes de tocar nada: sin el, la operacion no se
// ejecuta.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validarCsrf($_POST['csrf_token'] ?? '')) {
    $mensaje = $texto['error'];
    $tipo_mensaje = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // La accion define la operacion; el id validado como entero distingue
    // edicion (id presente) de alta (id ausente).
    $accion = $_POST['accion'] ?? '';
    $id_servicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);

    try {
        if ($accion === 'guardar') {
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            // El precio se fuerza a numero y nunca puede ser negativo;
            // si llega cualquier otra cosa se guarda 0.
            $precio = is_numeric($_POST['precio'] ?? '') ? max(0, (float) $_POST['precio']) : 0;

            // El nombre es obligatorio. Se aborta con el mensaje traducido.
            if ($nombre === '') {
                throw new RuntimeException($texto['error']);
            }

            // Con id es edicion; sin id es un alta nueva que nace activa.
            if ($id_servicio) {
                $stmt = $conexion->prepare('UPDATE servicio SET nombre = :nombre, descripcion = :descripcion, precio = :precio WHERE id_servicio = :id_servicio');
                $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion, 'precio' => $precio, 'id_servicio' => $id_servicio]);
                $mensaje = $texto['actualizado'];
            } else {
                $stmt = $conexion->prepare('INSERT INTO servicio (nombre, descripcion, precio, activo) VALUES (:nombre, :descripcion, :precio, 1)');
                $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion, 'precio' => $precio]);
                $mensaje = $texto['creado'];
            }
        } elseif ($accion === 'estado' && $id_servicio) {
            // Alterna activo/inactivo en una sola consulta para no leer y reescribir.
            // Los servicios inactivos dejan de ofrecerse al momento de reservar.
            $stmt = $conexion->prepare('UPDATE servicio SET activo = IF(activo = 1, 0, 1) WHERE id_servicio = :id_servicio');
            $stmt->execute(['id_servicio' => $id_servicio]);
            $mensaje = $texto['cambiado'];
        } elseif ($accion === 'eliminar' && $id_servicio) {
            // Borrado protegido: si el servicio ya tiene reservas no se elimina,
            // para no romper la integridad referencial ni perder trazabilidad.
            // En ese caso se sugiere desactivarlo.
            $usos = $conexion->prepare('SELECT COUNT(*) FROM reserva WHERE id_servicio = :id_servicio');
            $usos->execute(['id_servicio' => $id_servicio]);
            if ((int) $usos->fetchColumn() > 0) {
                $mensaje = $texto['usado'];
                $tipo_mensaje = 'error';
            } else {
                $stmt = $conexion->prepare('DELETE FROM servicio WHERE id_servicio = :id_servicio');
                $stmt->execute(['id_servicio' => $id_servicio]);
                $mensaje = $texto['eliminado'];
            }
        }
    } catch (Throwable $e) {
        // Solo las RuntimeException llevan un mensaje pensado para el usuario;
        // cualquier otro error interno se oculta detras del texto generico.
        $mensaje = $e instanceof RuntimeException ? $e->getMessage() : $texto['error'];
        $tipo_mensaje = 'error';
    }
}

// Lista completa para la tabla. El ORDER BY activo DESC deja primero los
// activos (los que se ofrecen al reservar) y ordena alfabeticamente dentro
// de cada grupo.
$servicios = [];
try {
    $stmt = $conexion->query('SELECT id_servicio, nombre, descripcion, precio, activo FROM servicio ORDER BY activo DESC, nombre ASC');
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = $texto['error'];
    $tipo_mensaje = 'error';
}
// Totales para las tarjetas: activos (reservables) e inactivos (ocultos).
$servicios_activos = count(array_filter($servicios, static function ($servicio) { return (int) $servicio['activo'] === 1; }));
$servicios_inactivos = count($servicios) - $servicios_activos;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/tgs-panel.css">
    <link rel="stylesheet" href="css/base-tgs.css">
    <style>
        /* Formulario de servicios: la grilla mantiene los campos en linea. */
        .form-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.7fr) minmax(0, 0.7fr) auto; gap: 14px; align-items: end; }
        .form-field { min-width: 0; }
        .form-field textarea { resize: vertical; min-height: 42px; }
        .form-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .cancel-edit { display: none; color: var(--tgs-texto-suave); background: transparent; border: 1px solid var(--tgs-borde-claro); padding: 6px 11px; font-size: 12px; border-radius: 7px; cursor: pointer; font-weight: 600; }
        .cancel-edit:hover { color: var(--tgs-texto); border-color: var(--tgs-acento); background: rgba(56, 189, 248, 0.1); }
        .precio { color: var(--tgs-acento) !important; font-weight: 700; white-space: nowrap; }
        .servicio-id { display: block; color: #64748b; font-size: 11px; margin-top: 2px; }
        .acciones-servicio { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .acciones-servicio form { margin: 0; }
        @media (max-width: 860px) {
            .form-grid { grid-template-columns: 1fr; }
        }
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
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['descripcion']; ?></p>
            <div class="tgs-acciones" style="margin-top: 14px;">
                <a href="#formulario-servicio" class="tgs-btn">+ <?php echo $texto['nuevo']; ?></a>
            </div>
        </div>

        <div class="tgs-rejilla">
            <div class="tgs-metrica"><span><?php echo $lang_code === 'en' ? 'Total services' : 'Servicios totales'; ?></span><strong><?php echo count($servicios); ?></strong></div>
            <div class="tgs-metrica"><span><?php echo $texto['activo']; ?></span><strong><?php echo $servicios_activos; ?></strong></div>
            <div class="tgs-metrica"><span><?php echo $texto['inactivo']; ?></span><strong><?php echo $servicios_inactivos; ?></strong></div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="tgs-aviso <?php echo $tipo_mensaje === 'error' ? 'error' : ''; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Formulario reutilizado para crear o editar un servicio -->
        <section class="tgs-tarjeta" id="formulario-servicio">
            <div class="form-heading">
                <h3 id="form-title"><?php echo $texto['nuevo']; ?></h3>
                <button type="button" class="cancel-edit" id="cancel-edit" onclick="cancelarEdicion()"><?php echo $texto['cancelar']; ?></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="accion" value="guardar">
                <div class="form-grid">
                    <div class="form-field tgs-campo">
                        <label for="nombre"><?php echo $texto['nombre']; ?></label>
                        <input id="nombre" name="nombre" type="text" maxlength="120" required>
                    </div>
                    <div class="form-field tgs-campo">
                        <label for="descripcion"><?php echo $texto['detalle']; ?></label>
                        <textarea id="descripcion" name="descripcion" maxlength="255" rows="1"></textarea>
                    </div>
                    <div class="form-field tgs-campo">
                        <label for="precio"><?php echo $texto['precio']; ?></label>
                        <input id="precio" name="precio" type="number" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <button type="submit" class="tgs-btn"><?php echo $texto['guardar']; ?></button>
                </div>
            </form>
        </section>

        <!-- Tabla de servicios con estado y acciones administrativas -->
        <section class="tgs-tarjeta">
            <h3><?php echo $texto['listado']; ?></h3>
            <div class="tgs-tabla-scroll">
                <table class="tgs-tabla">
                    <thead>
                        <tr>
                            <th><?php echo $texto['nombre']; ?></th>
                            <th><?php echo $texto['detalle']; ?></th>
                            <th><?php echo $texto['precio']; ?></th>
                            <th><?php echo $texto['estado'] ?? ($lang_code === 'en' ? 'Status' : 'Estado'); ?></th>
                            <th><?php echo $texto['acciones']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($servicios)): ?>
                            <tr><td colspan="5" class="vacio"><?php echo $texto['sin_servicios']; ?></td></tr>
                        <?php else: foreach ($servicios as $servicio): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($servicio['nombre']); ?></strong>
                                    <span class="servicio-id">ID #<?php echo (int) $servicio['id_servicio']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($servicio['descripcion'] ?? ''); ?></td>
                                <td class="precio"><?php echo number_format((float) $servicio['precio'], 2, ',', '.'); ?> USD</td>
                                <td>
                                    <span class="tgs-badge <?php echo (int) $servicio['activo'] === 1 ? 'exito' : 'peligro'; ?>">
                                        <?php echo (int) $servicio['activo'] === 1 ? $texto['activo'] : $texto['inactivo']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones-servicio">
                                        <?php // Los datos viajan como JSON escapado para rellenar el formulario sin romper el onclick. ?>
                                        <a href="#formulario-servicio" class="tgs-btn chico" onclick="editarServicio(<?php echo htmlspecialchars(json_encode($servicio), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo $texto['editar_btn']; ?></a>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion" value="estado">
                                            <input type="hidden" name="id_servicio" value="<?php echo (int) $servicio['id_servicio']; ?>">
                                            <button class="tgs-btn neutro chico" type="submit"><?php echo (int) $servicio['activo'] === 1 ? $texto['desactivar'] : $texto['activar']; ?></button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars($texto['confirmar'], ENT_QUOTES, 'UTF-8'); ?>');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_servicio" value="<?php echo (int) $servicio['id_servicio']; ?>">
                                            <button class="tgs-btn peligro chico" type="submit"><?php echo $texto['eliminar']; ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script>
        function editarServicio(servicio) {
            // Rellena el formulario con el servicio elegido y agrega el id
            // oculto que hace que el POST se trate como edicion y no como alta.
            document.getElementById('form-title').textContent = '<?php echo addslashes($texto['editar']); ?>';
            document.getElementById('cancel-edit').style.display = 'inline-block';
            document.getElementById('nombre').value = servicio.nombre || '';
            document.getElementById('descripcion').value = servicio.descripcion || '';
            document.getElementById('precio').value = servicio.precio || 0;
            const form = document.querySelector('#formulario-servicio form');
            let id = form.querySelector('input[name="id_servicio"]');
            if (!id) { id = document.createElement('input'); id.type = 'hidden'; id.name = 'id_servicio'; form.appendChild(id); }
            id.value = servicio.id_servicio;
        }

        function cancelarEdicion() {
            // Quita el id oculto para volver al modo alta y limpia los campos.
            const form = document.querySelector('#formulario-servicio form');
            const id = form.querySelector('input[name="id_servicio"]');
            if (id) { id.remove(); }
            form.reset();
            document.getElementById('form-title').textContent = '<?php echo addslashes($texto['nuevo']); ?>';

      </script>

      <?php include 'includes/pie.php'; ?>
  </body>
  </html>
