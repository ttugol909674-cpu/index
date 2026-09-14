<?php
###### # Administra los servicios disponibles para los talleres.
# Solo administradores y gestores pueden administrar el catálogo de servicios
require_once 'auth.php';
verificarRol(['admin', 'gestor']);
require_once 'conexion.php';

# Guarda el idioma elegido para mantener la pantalla bilingüe
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'en' ? 'en' : 'es';
}
$lang_code = $_SESSION['lang'] ?? 'es';
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

# Crea la tabla si todavía no existe en la instalación actual
# Esto permite instalar el módulo sin ejecutar SQL manualmente
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

# Procesa altas, ediciones y cambios de estado con consultas preparadas
# Las consultas preparadas separan los datos del SQL y reducen riesgos de inyección
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id_servicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);

    try {
        if ($accion === 'guardar') {
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = is_numeric($_POST['precio'] ?? '') ? max(0, (float) $_POST['precio']) : 0;

            if ($nombre === '') {
                throw new RuntimeException($texto['error']);
            }

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
            $stmt = $conexion->prepare('UPDATE servicio SET activo = IF(activo = 1, 0, 1) WHERE id_servicio = :id_servicio');
            $stmt->execute(['id_servicio' => $id_servicio]);
            $mensaje = $texto['cambiado'];
        } elseif ($accion === 'eliminar' && $id_servicio) {
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
        $mensaje = $e instanceof RuntimeException ? $e->getMessage() : $texto['error'];
        $tipo_mensaje = 'error';
    }
}

# Obtiene todos los servicios para mostrarlos en la tabla
# Ordena primero los servicios activos para facilitar la gestión diaria
$servicios = [];
try {
    $stmt = $conexion->query('SELECT id_servicio, nombre, descripcion, precio, activo FROM servicio ORDER BY activo DESC, nombre ASC');
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = $texto['error'];
    $tipo_mensaje = 'error';
}
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/tema-tgs.css">
    <style>
        body { background: radial-gradient(circle at top right, rgba(56, 201, 245, 0.14), transparent 32%), #050b16 !important; }
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        header { position: relative; min-height: 70px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; padding: 15px 32px; background: rgba(5,11,22,.96); border-bottom: 1px solid rgba(56,201,245,.24); box-shadow: 0 8px 24px rgba(0,0,0,.2); }
        header .logo { color: #38bdf8; font-size: 20px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        header nav { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 14px; }
        header .nav-link { color: #cbd5e1; font-size: 14px; text-decoration: none; transition: color .2s ease; }
        header .nav-link:hover { color: #8ee8ff; }
        header nav > .btn, .services-header .btn, .service-actions .btn { text-decoration: none !important; }
        header .btn-logout { display: inline-flex; align-items: center; min-height: 34px; padding: 7px 14px; border-radius: 7px; background: #ef4444; color: #ffffff; font-size: 13px; font-weight: 600; text-decoration: none; transition: transform .2s ease, background .2s ease, box-shadow .2s ease; }
        header .btn-logout:hover { background: #dc2626; color: #ffffff; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(239,68,68,.24); }
        header nav > span { color: #94a3b8; font-size: 13px; white-space: nowrap; }
        .language-selector { display: flex; gap: 4px; align-items: center; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .services-page { max-width: 1200px; margin: 0 auto; padding: 40px 20px 58px; }
        .services-header { display: flex; justify-content: space-between; gap: 20px; align-items: center; background: rgba(30,41,59,.95); border: 1px solid #334155; border-radius: 12px; padding: 30px; margin-bottom: 24px; box-shadow: 0 10px 25px rgba(0,0,0,.5); }
        .services-header > div { flex: 1; min-width: 0; }
        .services-header h1 { color: #38bdf8; font-size: 24px; margin-bottom: 9px; letter-spacing: .01em; }
        .section-kicker { color: #7dd3fc; font-size: 11px; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; margin-bottom: 10px; }
        .services-header p { color: #cbd5e1; font-size: 13px; line-height: 1.55; }
        .service-counts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .count-card { flex: 1; background: rgba(30,41,59,.95); border: 1px solid #334155; border-radius: 10px; padding: 18px 20px; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,.5); }
        .count-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: #38c9f5; }
        .count-card span { display: block; color: #cbd5e1; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 5px; }
        .count-card strong { color: #9be7ff; font-size: 22px; }
        .service-form { background: rgba(30,41,59,.95); border: 1px solid #334155; border-radius: 12px; padding: 26px; margin-bottom: 24px; box-shadow: 0 10px 25px rgba(0,0,0,.5); }
        .service-form h2, .service-list h2 { color: #8ee8ff; font-size: 18px; margin: 0 0 16px; padding-bottom: 12px; border-bottom: 1px solid rgba(71,112,145,.42); }
        .form-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .cancel-edit { display: none; color: #cbd5e1; background: transparent; border: 1px solid #475569; padding: 6px 10px; font-size: 12px; border-radius: 7px; cursor: pointer; }
        .cancel-edit:hover { color: #f8fafc; border-color: #38bdf8; background: rgba(56,189,248,.1); }
        .form-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.7fr) minmax(0, .7fr) auto; gap: 14px; align-items: end; }
        .form-field { min-width: 0; }
        .form-field label { display: block; color: #e2e8f0; font-size: 12px; margin-bottom: 6px; font-weight: 600; }
        .form-field input, .form-field textarea { display: block; width: 100%; min-width: 0; min-height: 42px; background: rgba(15,23,42,.9); color: #f8fafc; border: 1px solid #475569; border-radius: 8px; padding: 9px 11px; outline: none; }
        .form-field textarea { resize: vertical; min-height: 40px; }
        .form-grid > .btn { min-height: 42px; white-space: nowrap; align-self: end; }
        .service-list { background: rgba(30,41,59,.95); border: 1px solid #334155; border-radius: 12px; padding: 22px 20px; overflow-x: auto; box-shadow: 0 10px 25px rgba(0,0,0,.5); }
        .service-table { width: 100%; min-width: 720px; border-collapse: collapse; }
        .service-table th, .service-table td { text-align: left; border-bottom: 1px solid rgba(71,112,145,.42); padding: 13px 10px; color: #cbd5e1; font-size: 13px; vertical-align: middle; }
        .service-table th { color: #8ee8ff; text-transform: uppercase; font-size: 11px; letter-spacing: .06em; }
        .service-table tbody tr { transition: background .2s ease; }
        .service-table tbody tr:hover { background: rgba(56,189,248,.08); }
        .service-table td strong { color: #f8fafc; display: block; margin-bottom: 3px; }
        .service-id { color: #64748b; font-size: 11px; }
        .price { color: #9be7ff !important; font-weight: 700; white-space: nowrap; }
        .service-table td small { color: #cbd5e1; line-height: 1.45; }
        .status { display: inline-block; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 700; }
        .status.active { color: #86efac; background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); }
        .status.inactive { color: #fca5a5; background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); }
        .service-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .service-actions form { margin: 0; }
        .service-actions .btn { padding: 6px 9px; font-size: 11px; white-space: nowrap; }
        .services-header > .btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 16px; border-radius: 8px !important; font-size: 14px; font-weight: 600; color: #ffffff !important; background: #2563eb !important; text-decoration: none !important; white-space: nowrap; }
        .services-header > .btn:hover { background: #1d4ed8 !important; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(37,99,235,.22); }
        .alert-box { border-radius: 8px; padding: 11px 14px; margin-bottom: 18px; font-size: 13px; }
        .alert-box.success { color: #bbf7d0; background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); }
        .alert-box.error { color: #fecaca; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); }
        @media (max-width: 760px) {
            header { padding: 14px 16px; align-items: flex-start; }
            header .logo { width: 100%; text-align: center; white-space: normal; }
            header nav { justify-content: center; width: 100%; gap: 9px; }
            .services-page { padding: 24px 12px 32px; }
            .services-header { align-items: stretch; flex-direction: column; gap: 14px; }
            .services-header h1 { font-size: 26px; }
            .services-header > .btn { width: 100%; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid > .btn { width: 100%; }
            .service-form, .service-list { padding: 17px; }
            .service-counts { gap: 8px; }
            .count-card { padding: 12px 10px; }
            .count-card strong { font-size: 18px; }
            .service-counts { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .count-card span { font-size: 9px; }
        }
    </style>
</head>
<body>
    <!-- Navegación, selector de idioma y cierre de sesión -->
    <header>
        <a href="<?php echo $rol_actual === 'admin' ? 'dashboard_admin.php' : 'dashboard_gestor.php'; ?>" class="logo">TGS - <?php echo $texto['titulo']; ?></a>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> <?php echo $texto['inicio']; ?></a>
            <a href="<?php echo $rol_actual === 'admin' ? 'dashboard_admin.php' : 'dashboard_gestor.php'; ?>" class="nav-link"><?php echo $texto['panel']; ?></a>
            <div class="language-selector"><a href="administrar_servicios.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a><a href="administrar_servicios.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a></div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? ''); ?></strong></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <main class="services-page">
        <div class="services-header">
            <div><div class="section-kicker">TGS · Taller Global de Servicios</div><h1><?php echo $texto['titulo']; ?></h1><p><?php echo $texto['descripcion']; ?></p></div>
            <a href="#formulario-servicio" class="btn btn-primary"><i class="fas fa-plus"></i> <?php echo $texto['nuevo']; ?></a>
        </div>

        <div class="service-counts">
            <div class="count-card"><span><?php echo $lang_code === 'en' ? 'Total services' : 'Servicios totales'; ?></span><strong><?php echo count($servicios); ?></strong></div>
            <div class="count-card"><span><?php echo $texto['activo']; ?></span><strong><?php echo $servicios_activos; ?></strong></div>
            <div class="count-card"><span><?php echo $texto['inactivo']; ?></span><strong><?php echo $servicios_inactivos; ?></strong></div>
        </div>

        <?php if ($mensaje !== ''): ?><div class="alert-box <?php echo $tipo_mensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>

        <!-- Formulario reutilizado para crear o editar un servicio -->
        <section class="service-form" id="formulario-servicio">
            <div class="form-heading"><h2 id="form-title"><?php echo $texto['nuevo']; ?></h2><button type="button" class="cancel-edit" id="cancel-edit" onclick="cancelarEdicion()"><?php echo $texto['cancelar']; ?></button></div>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar">
                <div class="form-grid">
                    <div class="form-field"><label for="nombre"><?php echo $texto['nombre']; ?></label><input id="nombre" name="nombre" type="text" maxlength="120" required></div>
                    <div class="form-field"><label for="descripcion"><?php echo $texto['detalle']; ?></label><textarea id="descripcion" name="descripcion" maxlength="255" rows="1"></textarea></div>
                    <div class="form-field"><label for="precio"><?php echo $texto['precio']; ?></label><input id="precio" name="precio" type="number" min="0" step="0.01" placeholder="0.00"></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $texto['guardar']; ?></button>
                </div>
            </form>
        </section>

        <!-- Tabla de servicios con estado y acciones administrativas -->
        <section class="service-list">
            <h2><?php echo $texto['listado']; ?></h2>
            <table class="service-table">
                <thead><tr><th><?php echo $texto['nombre']; ?></th><th><?php echo $texto['detalle']; ?></th><th><?php echo $texto['precio']; ?></th><th><?php echo $texto['estado'] ?? ($lang_code === 'en' ? 'Status' : 'Estado'); ?></th><th><?php echo $texto['acciones']; ?></th></tr></thead>
                <tbody>
                    <?php if (empty($servicios)): ?><tr><td colspan="5"><?php echo $texto['sin_servicios']; ?></td></tr>
                    <?php else: foreach ($servicios as $servicio): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($servicio['nombre']); ?></strong><span class="service-id">ID #<?php echo (int) $servicio['id_servicio']; ?></span></td>
                            <td><small><?php echo htmlspecialchars($servicio['descripcion'] ?? ''); ?></small></td>
                            <td class="price">$<?php echo number_format((float) $servicio['precio'], 2, ',', '.'); ?></td>
                            <td><span class="status <?php echo (int) $servicio['activo'] === 1 ? 'active' : 'inactive'; ?>"><?php echo (int) $servicio['activo'] === 1 ? $texto['activo'] : $texto['inactivo']; ?></span></td>
                            <td><div class="service-actions">
                                <a href="#formulario-servicio" class="btn btn-primary" onclick="editarServicio(<?php echo htmlspecialchars(json_encode($servicio), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-pen"></i> <?php echo $texto['editar_btn']; ?></a>
                                <form method="POST"><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_servicio" value="<?php echo (int) $servicio['id_servicio']; ?>"><button class="btn btn-outline-light" type="submit"><?php echo (int) $servicio['activo'] === 1 ? $texto['desactivar'] : $texto['activar']; ?></button></form>
                                <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars($texto['confirmar'], ENT_QUOTES, 'UTF-8'); ?>');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_servicio" value="<?php echo (int) $servicio['id_servicio']; ?>"><button class="btn btn-danger" type="submit"><?php echo $texto['eliminar']; ?></button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>
    </main>
    <script>
        function editarServicio(servicio) {
            document.getElementById('form-title').textContent = '<?php echo addslashes($texto['editar']); ?>';
            document.getElementById('cancel-edit').style.display = 'inline-block';
            document.getElementById('nombre').value = servicio.nombre || '';
            document.getElementById('descripcion').value = servicio.descripcion || '';
            document.getElementById('precio').value = servicio.precio || 0;
            const form = document.querySelector('.service-form form');
            let id = form.querySelector('input[name="id_servicio"]');
            if (!id) { id = document.createElement('input'); id.type = 'hidden'; id.name = 'id_servicio'; form.appendChild(id); }
            id.value = servicio.id_servicio;
        }

        function cancelarEdicion() {
            const form = document.querySelector('.service-form form');
            const id = form.querySelector('input[name="id_servicio"]');
            if (id) { id.remove(); }
            form.reset();
            document.getElementById('form-title').textContent = '<?php echo addslashes($texto['nuevo']); ?>';
            document.getElementById('cancel-edit').style.display = 'none';
        }
    </script>
</body>
</html>
