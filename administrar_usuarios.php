<?php
###### # Permite consultar y gestionar las cuentas de usuario.
# Valida que el usuario sea administrador antes de acceder
require_once 'auth.php';
verificarRol('admin');

# Incluye la conexión a la base de datos
require_once 'conexion.php';

# Manejo de idioma desde la URL ?lang=es o ?lang=en
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang_code = $_SESSION['lang'] ?? 'es';
$lang_file = ($lang_code === 'en') ? 'lang_en.php' : 'lang_es.php';
if (file_exists($lang_file)) {
    include_once $lang_file;
}

# Fallback por si no existen los archivos de idioma o no hay textos definidos
if (!isset($lang) || !is_array($lang)) {
    $lang = [
        'titulo_usuarios' => ($lang_code === 'en') ? 'User Management' : 'Administración de Usuarios',
        'volver_admin' => ($lang_code === 'en') ? '← Back to Admin Panel' : '← Volver al Panel de Administración',
        'home' => ($lang_code === 'en') ? 'Home' : 'Página Principal',
        'lista_usuarios' => ($lang_code === 'en') ? 'Registered Users List' : 'Lista de Usuarios Registrados',
        'cedula' => 'Cédula',
        'nombre' => ($lang_code === 'en') ? 'Name' : 'Nombre',
        'email' => 'Email',
        'telefono' => ($lang_code === 'en') ? 'Mobile phone' : 'Teléfono celular',
        'rol_actual' => ($lang_code === 'en') ? 'Current Role' : 'Rol Actual',
        'cambiar_rol' => ($lang_code === 'en') ? 'Change Role' : 'Cambiar Rol',
        'acciones' => ($lang_code === 'en') ? 'Actions' : 'Acciones',
        'actualizar' => ($lang_code === 'en') ? 'Update' : 'Actualizar',
        'eliminar' => ($lang_code === 'en') ? 'Delete' : 'Eliminar',
        'no_usuarios' => ($lang_code === 'en') ? 'No registered users.' : 'No hay usuarios registrados.',
        'confirmar_eliminar' => ($lang_code === 'en') ? 'Are you sure you want to delete this user?' : '¿Estás seguro de que deseas eliminar este usuario?',
        'rol_actualizado' => ($lang_code === 'en') ? 'Role updated successfully!' : '¡Rol actualizado exitosamente!',
        'usuario_eliminado' => ($lang_code === 'en') ? 'User deleted successfully!' : '¡Usuario eliminado exitosamente!',
        'error_operacion' => ($lang_code === 'en') ? 'An error occurred.' : 'Ocurrió un error.'
    ];
}

# Variables para mensajes del panel
$mensaje = '';
$tipo_alerta = 'alert-success';

# Eliminar un usuario si llega el POST con eliminar_cedula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_cedula'])) {
    $cedula_a_eliminar = $_POST['eliminar_cedula'];
    try {
        $del = $conexion->prepare("DELETE FROM usuario WHERE cedula = :cedula");
        $del->execute(['cedula' => $cedula_a_eliminar]);
        $mensaje = $lang['usuario_eliminado'] ?? '¡Usuario eliminado exitosamente!';
    } catch (PDOException $e) {
        $mensaje = ($lang['error_operacion'] ?? 'Error') . ": " . $e->getMessage();
        $tipo_alerta = 'alert-error';
    }
}

# Actualiza el rol de un usuario si llega el POST con id_usuario y nuevo_rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'], $_POST['nuevo_rol'])) {
    $id_usr = $_POST['id_usuario'];
    $n_rol = $_POST['nuevo_rol'];

    try {
        $upd = $conexion->prepare("UPDATE usuario SET rol = :rol WHERE cedula = :id OR email = :id");
        $upd->execute(['rol' => $n_rol, 'id' => $id_usr]);
        $mensaje = $lang['rol_actualizado'] ?? '¡Rol actualizado exitosamente!';
    } catch (PDOException $e) {
        $mensaje = ($lang['error_operacion'] ?? 'Error') . ": " . $e->getMessage();
        $tipo_alerta = 'alert-error';
    }
}

# Trae todos los usuarios para listarlos en la tabla
try {
    $stmt = $conexion->query("SELECT * FROM usuario");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['titulo_usuarios'] ?? 'Administrar Usuarios'; ?> - TGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 20px; color: #38bdf8; }
        .header-actions { display: flex; align-items: center; gap: 15px; }
        .container { flex: 1; padding: 40px 20px; max-width: 1200px; width: 100%; margin: 0 auto; }
        .btn-navegacion { display: inline-flex; align-items: center; gap: 8px; background-color: #334155; color: #38bdf8; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: background 0.3s, color 0.3s; margin-bottom: 20px; border: 1px solid #475569; }
        .btn-navegacion:hover { background-color: #2563eb; color: white; border-color: #2563eb; }
        .lang-selector { display: flex; gap: 5px; }
        .lang-btn { background-color: #334155; color: #38bdf8; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid #475569; transition: background 0.3s, color 0.3s; }
        .lang-btn:hover, .lang-btn.active { background-color: #2563eb; color: white; border-color: #2563eb; }
        table { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border-radius: 8px; overflow: hidden; border: 1px solid #334155; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #334155; font-size: 14px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; }
        select, button { padding: 6px 10px; border-radius: 6px; border: 1px solid #475569; background-color: rgba(15, 23, 42, 0.9); color: white; font-size: 13px; outline: none; }
        button { background-color: #2563eb; cursor: pointer; border: none; font-weight: 600; transition: background 0.3s; }
        button:hover { background-color: #1d4ed8; }
        .btn-danger { background-color: #ef4444; }
        .btn-danger:hover { background-color: #dc2626; }
        .alert-success { background-color: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #4ade80; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background-color: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
</head>
<body>
    <header>
        <h1><?php echo $lang['titulo_usuarios'] ?? 'Administración de Usuarios'; ?></h1>
        <div class="header-actions">
            <div class="lang-selector">
                <a href="?lang=es" class="lang-btn <?php echo ($lang_code === 'es') ? 'active' : ''; ?>">ES</a>
                <a href="?lang=en" class="lang-btn <?php echo ($lang_code === 'en') ? 'active' : ''; ?>">EN</a>
            </div>
            <a href="index.php" class="btn-navegacion" style="margin-bottom: 0;"><i class="fas fa-home"></i> <?php echo $lang['home'] ?? 'Página Principal'; ?></a>
        </div>
    </header>

    <div class="container">
        <a href="dashboard_admin.php" class="btn-navegacion"><?php echo $lang['volver_admin'] ?? '&larr; Volver al Panel de Administración'; ?></a>
        
        <h2 style="margin-bottom: 20px; color: #f8fafc;"><?php echo $lang['lista_usuarios'] ?? 'Lista de Usuarios Registrados'; ?></h2>

        <?php if (!empty($mensaje)): ?>
            <div class="<?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th><?php echo $lang['cedula'] ?? 'Cédula'; ?></th>
                    <th><?php echo $lang['nombre'] ?? 'Nombre'; ?></th>
                    <th><?php echo $lang['email'] ?? 'Email'; ?></th>
                    <th><?php echo $lang['telefono'] ?? 'Teléfono celular'; ?></th>
                    <th><?php echo $lang['rol_actual'] ?? 'Rol Actual'; ?></th>
                    <th><?php echo $lang['cambiar_rol'] ?? 'Cambiar Rol'; ?></th>
                    <th><?php echo $lang['acciones'] ?? 'Acciones'; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($usuarios) > 0): ?>
                    <?php foreach ($usuarios as $u): ?>
                        <?php $cedula_usr = $u['cedula'] ?? ''; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cedula_usr); ?></td>
                            <td><?php echo htmlspecialchars($u['nombre'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($u['telefono'] ?? '-'); ?></td>
                            <td><strong><?php echo htmlspecialchars($u['rol'] ?? 'cliente'); ?></strong></td>
                            <td>
                                <form action="" method="POST" style="display: flex; gap: 8px; align-items: center;">
                                    <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($cedula_usr); ?>">
                                    <select name="nuevo_rol">
                                        <option value="cliente" <?php if(strtolower($u['rol']??'')=='cliente') echo 'selected'; ?>>Cliente</option>
                                        <option value="gestor" <?php if(strtolower($u['rol']??'')=='gestor') echo 'selected'; ?>>Gestor</option>
                                        <option value="admin" <?php if(strtolower($u['rol']??'')=='admin') echo 'selected'; ?>>Admin</option>
                                    </select>
                                    <button type="submit"><?php echo $lang['actualizar'] ?? 'Actualizar'; ?></button>
                                </form>
                            </td>
                            <td>
                                <form action="" method="POST" onsubmit="return confirm('<?php echo $lang['confirmar_eliminar'] ?? '¿Estás seguro de que deseas eliminar este usuario?'; ?>');">
                                    <input type="hidden" name="eliminar_cedula" value="<?php echo htmlspecialchars($cedula_usr); ?>">
                                    <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> <?php echo $lang['eliminar'] ?? 'Eliminar'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #94a3b8;"><?php echo $lang['no_usuarios'] ?? 'No hay usuarios registrados.'; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $lang_code === 'en' ? 'Global Workshop Services. All rights reserved.' : 'Taller Global de Servicios. Todos los derechos reservados.'; ?></p>
    </footer>
</body>
</html>
