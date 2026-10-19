<?php
/**
 * TGS - Taller Global de Servicios
 * Datos bancarios para el pago de los clientes.
 *
 * El administrador publica una o mas cuentas (alias, CBU y codigo QR) que
 * el cliente ve en su pantalla de pagos para transferir. Cada cuenta se
 * puede activar o desactivar.
 *
 * Acceso: solo administradores (permiso 'administrar_datos_pago').
 *
 * Dependencias: auth.php, conexion.php
 */
require_once 'auth.php';
verificarPermiso('administrar_datos_pago');
require_once 'conexion.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

$texto = $lang_code === 'en' ? [
    'titulo' => 'Bank details for payments', 'inicio' => 'Back to Home', 'panel' => 'Admin Panel',
    'hola' => 'Hello', 'salir' => 'Sign Out',
    'resumen' => 'These accounts are shown to the customer on the payments screen, with the alias, the CBU and the QR code to transfer.',
    'agregar' => 'Add account', 'nombre' => 'Account name', 'alias' => 'Alias', 'cbu' => 'CBU',
    'qr' => 'QR image URL', 'notas' => 'Notes', 'activo' => 'Active', 'guardar' => 'Save', 'actualizar' => 'Update',
    'eliminar' => 'Delete', 'cancelar' => 'Cancel', 'acciones' => 'Actions', 'editar' => 'Edit',
    'sin_cuentas' => 'No bank accounts published yet.',
    'exito' => 'The bank details were saved.', 'eliminado' => 'The bank account was deleted.',
    'error_operacion' => 'The operation could not be completed.',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_nombre' => 'The account name and the alias are required.',
    'qr_ayuda' => 'URL of the QR image. You can also paste an <img> tag.',
    'volver' => 'Back to the admin panel'
] : [
    'titulo' => 'Datos bancarios para pagos', 'inicio' => 'Volver al Inicio', 'panel' => 'Panel Admin',
    'hola' => 'Hola', 'salir' => 'Cerrar Sesión',
    'resumen' => 'Estas cuentas se muestran al cliente en su pantalla de pagos, con el alias, el CBU y el código QR para transferir.',
    'agregar' => 'Agregar cuenta', 'nombre' => 'Nombre de la cuenta', 'alias' => 'Alias', 'cbu' => 'CBU',
    'qr' => 'URL de la imagen QR', 'notas' => 'Notas', 'activo' => 'Activa', 'guardar' => 'Guardar', 'actualizar' => 'Actualizar',
    'eliminar' => 'Eliminar', 'cancelar' => 'Cancelar', 'acciones' => 'Acciones', 'editar' => 'Editar',
    'sin_cuentas' => 'Todavía no publicaste ninguna cuenta bancaria.',
    'exito' => 'Los datos bancarios se guardaron.', 'eliminado' => 'La cuenta bancaria se eliminó.',
    'error_operacion' => 'No se pudo completar la operación.',
    'error_csrf' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
    'error_nombre' => 'El nombre de la cuenta y el alias son obligatorios.',
    'qr_ayuda' => 'URL de la imagen QR. También se puede pegar un <img>.',
    'volver' => 'Volver al panel de administración'
];

$mensaje = '';
$tipo_alerta = 'alert-success';
$editando = null;

// ---------------------------------------------------------------------------
// Acciones: agregar, actualizar, eliminar y activar/desactivar
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $texto['error_csrf'];
        $tipo_alerta = 'alert-error';
    } else {
        $accion = $_POST['accion'];

        if ($accion === 'guardar' || $accion === 'actualizar') {
            $id = filter_input(INPUT_POST, 'id_datos_pago', FILTER_VALIDATE_INT) ?: 0;
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $alias = trim((string) ($_POST['alias'] ?? ''));
            $cbu = trim((string) ($_POST['cbu'] ?? ''));
            $qr = trim((string) ($_POST['qr'] ?? ''));
            $notas = trim((string) ($_POST['notas'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '' || $alias === '') {
                $mensaje = $texto['error_nombre'];
                $tipo_alerta = 'alert-error';
            } else {
                try {
                    if ($id) {
                        $stmt = $conexion->prepare('UPDATE datos_pago SET nombre = :nombre, alias = :alias, cbu = :cbu, qr = :qr, notas = :notas, activo = :activo WHERE id_datos_pago = :id');
                        $stmt->execute([
                            'nombre' => $nombre, 'alias' => $alias,
                            'cbu' => $cbu !== '' ? $cbu : null,
                            'qr' => $qr !== '' ? $qr : null,
                            'notas' => $notas !== '' ? $notas : null,
                            'activo' => $activo,
                            'id' => $id
                        ]);
                    } else {
                        $stmt = $conexion->prepare('INSERT INTO datos_pago (nombre, alias, cbu, qr, notas, activo) VALUES (:nombre, :alias, :cbu, :qr, :notas, :activo)');
                        $stmt->execute([
                            'nombre' => $nombre, 'alias' => $alias,
                            'cbu' => $cbu !== '' ? $cbu : null,
                            'qr' => $qr !== '' ? $qr : null,
                            'notas' => $notas !== '' ? $notas : null,
                            'activo' => $activo
                        ]);
                    }
                    $mensaje = $texto['exito'];
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        if ($accion === 'eliminar') {
            $id = filter_input(INPUT_POST, 'id_datos_pago', FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    $stmt = $conexion->prepare('DELETE FROM datos_pago WHERE id_datos_pago = :id');
                    $stmt->execute(['id' => $id]);
                    $mensaje = $texto['eliminado'];
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }

        if ($accion === 'alternar') {
            $id = filter_input(INPUT_POST, 'id_datos_pago', FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    $stmt = $conexion->prepare('UPDATE datos_pago SET activo = 1 - activo WHERE id_datos_pago = :id');
                    $stmt->execute(['id' => $id]);
                    $mensaje = $texto['exito'];
                } catch (PDOException $e) {
                    $mensaje = $texto['error_operacion'];
                    $tipo_alerta = 'alert-error';
                }
            }
        }
    }
}

// Cargar la cuenta que se esta editando.
$id_editar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($id_editar) {
    try {
        $stmt = $conexion->prepare('SELECT * FROM datos_pago WHERE id_datos_pago = :id LIMIT 1');
        $stmt->execute(['id' => $id_editar]);
        $editando = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $editando = null;
    }
}

// Listado de cuentas.
$cuentas = [];
try {
    $stmt = $conexion->query('SELECT * FROM datos_pago ORDER BY activo DESC, id_datos_pago');
    $cuentas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $cuentas = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        nav span { color: #94a3b8; font-size: 14px; }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .container { flex: 1; padding: 30px 20px 50px; max-width: 1000px; width: 100%; margin: 0 auto; }
        .welcome-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 22px 26px; margin-bottom: 20px; }
        .welcome-card h2 { font-size: 21px; color: #38bdf8; margin-bottom: 6px; }
        .welcome-card p { color: #cbd5e1; font-size: 14px; }
        .bloque { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 20px 22px; margin-bottom: 20px; }
        .bloque h3 { font-size: 15px; color: #38bdf8; margin-bottom: 14px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .campo { display: flex; flex-direction: column; gap: 6px; }
        .campo label { font-size: 13px; color: #94a3b8; }
        .campo input { background: rgba(15, 23, 42, 0.9); color: white; border: 1px solid #475569; border-radius: 8px; padding: 10px 12px; font-size: 14px; outline: none; }
        .campo input:focus { border-color: #38bdf8; }
        .campo input[type="checkbox"] { width: 16px; height: 16px; accent-color: #22c55e; }
        .ayuda { font-size: 11px; color: #64748b; }
        .acciones { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .btn { border: none; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primario { background: #2563eb; color: white; }
        .btn-primario:hover { background: #1d4ed8; }
        .btn-neutro { background: #334155; color: #cbd5e1; }
        .btn-neutro:hover { background: #475569; }
        .btn-peligro { background: #ef4444; color: white; }
        .btn-peligro:hover { background: #dc2626; }
        .btn-mini { padding: 6px 11px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; padding: 9px; text-align: left; font-weight: 600; }
        td { padding: 9px; border-bottom: 1px solid #334155; color: #cbd5e1; vertical-align: middle; }
        td form { display: inline-block; }
        .qr-mini { width: 48px; height: 48px; border-radius: 6px; background: white; padding: 3px; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge.activo { background: rgba(34, 197, 94, 0.12); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.35); }
        .badge.inactivo { background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.35); }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #22c55e; background: rgba(34, 197, 94, 0.1); color: #bbf7d0; font-size: 14px; }
        .alert-error { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .estado-vacio { text-align: center; color: #94a3b8; padding: 20px; font-size: 13px; }
        .volver { display: inline-block; margin-top: 6px; color: #38bdf8; text-decoration: none; font-size: 14px; font-weight: 600; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>
    <header>
        <a href="dashboard_admin.php" class="logo">TGS · <?php echo $texto['titulo']; ?></a>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> <?php echo $texto['inicio']; ?></a>
            <a href="dashboard_admin.php" class="nav-link" style="color: #38bdf8; font-weight: bold;"><?php echo $texto['panel']; ?></a>
            <!-- Selector de idioma de los datos bancarios -->
            <div class="language-selector">
                <a href="datos_pago.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a>
                <a href="datos_pago.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
            <span><?php echo $texto['hola']; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Formulario de alta / edicion -->
        <div class="bloque">
            <h3><?php echo $editando ? $texto['actualizar'] : $texto['agregar']; ?></h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="accion" value="<?php echo $editando ? 'actualizar' : 'guardar'; ?>">
                <?php if ($editando): ?>
                    <input type="hidden" name="id_datos_pago" value="<?php echo (int) $editando['id_datos_pago']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="campo">
                        <label for="nombre"><?php echo $texto['nombre']; ?></label>
                        <input type="text" id="nombre" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars($editando['nombre'] ?? ''); ?>">
                    </div>
                    <div class="campo">
                        <label for="alias"><?php echo $texto['alias']; ?></label>
                        <input type="text" id="alias" name="alias" maxlength="60" required value="<?php echo htmlspecialchars($editando['alias'] ?? ''); ?>">
                    </div>
                    <div class="campo">
                        <label for="cbu"><?php echo $texto['cbu']; ?></label>
                        <input type="text" id="cbu" name="cbu" maxlength="34" value="<?php echo htmlspecialchars($editando['cbu'] ?? ''); ?>">
                    </div>
                    <div class="campo">
                        <label for="qr"><?php echo $texto['qr']; ?></label>
                        <input type="text" id="qr" name="qr" maxlength="500" value="<?php echo htmlspecialchars($editando['qr'] ?? ''); ?>">
                        <span class="ayuda"><?php echo $texto['qr_ayuda']; ?></span>
                    </div>
                    <div class="campo">
                        <label for="notas"><?php echo $texto['notas']; ?></label>
                        <input type="text" id="notas" name="notas" maxlength="255" value="<?php echo htmlspecialchars($editando['notas'] ?? ''); ?>">
                    </div>
                    <div class="campo">
                        <label for="activo"><?php echo $texto['activo']; ?></label>
                        <input type="checkbox" id="activo" name="activo" value="1" <?php echo (!isset($editando['activo']) || (int) $editando['activo'] === 1) ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="acciones">
                    <button type="submit" class="btn btn-primario"><?php echo $texto['guardar']; ?></button>
                    <?php if ($editando): ?>
                        <a href="datos_pago.php" class="btn btn-neutro"><?php echo $texto['cancelar']; ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Listado de cuentas -->
        <div class="bloque">
            <h3><?php echo $texto['titulo']; ?></h3>
            <?php if (empty($cuentas)): ?>
                <p class="estado-vacio"><?php echo $texto['sin_cuentas']; ?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo $texto['nombre']; ?></th>
                            <th><?php echo $texto['alias']; ?></th>
                            <th><?php echo $texto['cbu']; ?></th>
                            <th><?php echo $texto['qr']; ?></th>
                            <th><?php echo $texto['activo']; ?></th>
                            <th><?php echo $texto['acciones']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cuentas as $cuenta): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cuenta['nombre'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($cuenta['alias'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($cuenta['cbu'] ?? '-'); ?></td>
                                <td>
                                    <?php if (!empty($cuenta['qr'])): ?>
                                        <img class="qr-mini" src="<?php echo htmlspecialchars($cuenta['qr']); ?>" alt="QR">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo (int) ($cuenta['activo'] ?? 0) === 1 ? 'activo' : 'inactivo'; ?>">
                                        <?php echo (int) ($cuenta['activo'] ?? 0) === 1 ? ($lang_code === 'en' ? 'Active' : 'Activa') : ($lang_code === 'en' ? 'Inactive' : 'Inactiva'); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                        <input type="hidden" name="accion" value="alternar">
                                        <input type="hidden" name="id_datos_pago" value="<?php echo (int) $cuenta['id_datos_pago']; ?>">
                                        <button type="submit" class="btn btn-neutro btn-mini"><?php echo (int) ($cuenta['activo'] ?? 0) === 1 ? ($lang_code === 'en' ? 'Deactivate' : 'Desactivar') : ($lang_code === 'en' ? 'Activate' : 'Activar'); ?></button>
                                    </form>
                                    <a href="datos_pago.php?editar=<?php echo (int) $cuenta['id_datos_pago']; ?>" class="btn btn-primario btn-mini"><?php echo $texto['editar'] ?? 'Editar'; ?></a>
                                    <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Delete this bank account?' : '¿Eliminar esta cuenta bancaria?'; ?>');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_datos_pago" value="<?php echo (int) $cuenta['id_datos_pago']; ?>">
                                        <button type="submit" class="btn btn-peligro btn-mini"><?php echo $texto['eliminar']; ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <a href="dashboard_admin.php" class="volver">&larr; <?php echo $texto['volver']; ?></a>
    </div>

    <footer>
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
