<?php
###### # Permite consultar y gestionar las cuentas de usuario.
# Valida que el usuario sea administrador antes de acceder
require_once 'auth.php';
verificarPermiso('administrar_usuarios');

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
        'error_operacion' => ($lang_code === 'en') ? 'An error occurred.' : 'Ocurrió un error.',
        'alta_usuario' => ($lang_code === 'en') ? 'Create User' : 'Alta de Usuario',
        'alta_descripcion' => ($lang_code === 'en') ? 'Register a new account and assign its role.' : 'Registra una cuenta nueva y asigna su rol.',
        'crear_usuario' => ($lang_code === 'en') ? 'Create User' : 'Crear Usuario',
        'contrasena' => ($lang_code === 'en') ? 'Password' : 'Contraseña',
        'clave_automatica' => ($lang_code === 'en') ? 'Generate a temporary password automatically' : 'Generar una contraseña temporal automáticamente',
        'alta_completa' => ($lang_code === 'en') ? 'Please complete all required fields.' : 'Por favor completa todos los campos obligatorios.',
        'alta_email_invalido' => ($lang_code === 'en') ? 'The email address is not valid.' : 'El correo electrónico no es válido.',
        'alta_telefono_invalido' => ($lang_code === 'en') ? 'Enter a valid mobile phone number.' : 'Ingresa un número de celular válido.',
        'alta_rol_invalido' => ($lang_code === 'en') ? 'The selected role is not valid.' : 'El rol seleccionado no es válido.',
        'alta_clave_corta' => ($lang_code === 'en') ? 'The password must be at least 8 characters long.' : 'La contraseña debe tener al menos 8 caracteres.',
        'alta_duplicado' => ($lang_code === 'en') ? 'A user with that ID number or email already exists.' : 'Ya existe un usuario con esa cédula o correo electrónico.',
        'alta_exito' => ($lang_code === 'en') ? 'User created successfully!' : '¡Usuario creado exitosamente!',
        'clave_temporal' => ($lang_code === 'en') ? 'Temporary password: ' : 'Clave temporal: ',
        'buscar_usuario' => ($lang_code === 'en') ? 'Search by ID, name, email or phone' : 'Buscar por cédula, nombre, email o teléfono',
        'buscar' => ($lang_code === 'en') ? 'Search' : 'Buscar',
        'filtro_rol' => ($lang_code === 'en') ? 'Filter by role' : 'Filtrar por rol',
        'todos_los_roles' => ($lang_code === 'en') ? 'All roles' : 'Todos los roles',
        'limpiar_filtros' => ($lang_code === 'en') ? 'Clear filters' : 'Limpiar filtros',
        'resultados' => ($lang_code === 'en') ? 'results' : 'resultados',
        'sin_resultados' => ($lang_code === 'en') ? 'No users match the current search.' : 'No hay usuarios que coincidan con la búsqueda actual.'
    ];
}

# Variables para mensajes del panel
$mensaje = '';
$tipo_alerta = 'alert-success';

# Control CSRF: se valida una vez el token de sesion y todas las acciones
# POST de esta pagina lo exigen.
$csrf_valido = validarCsrf($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_valido) {
    $mensaje = $lang_code === 'en' ? 'The session expired. Please reload the page and try again.' : 'La sesión expiró. Recargá la página e intentá nuevamente.';
    $tipo_alerta = 'alert-error';
}

# Genera una clave temporal aleatoria para las cuentas creadas desde el panel.
# Usa random_int (criptográficamente seguro) y garantiza al menos una
# mayúscula, una minúscula, un número y un símbolo.
function generar_clave_temporal(int $longitud = 12): string
{
    $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $minusculas = 'abcdefghijkmnopqrstuvwxyz';
    $numeros = '23456789';
    $simbolos = '!#$%&*?';
    $todos = $mayusculas . $minusculas . $numeros . $simbolos;

    $clave = '';
    for ($i = 0; $i < $longitud; $i++) {
        $clave .= $todos[random_int(0, strlen($todos) - 1)];
    }

    # Se asegura que la clave cumpla con la mezcla de caracteres exigida.
    $clave[0] = $mayusculas[random_int(0, strlen($mayusculas) - 1)];
    $clave[1] = $minusculas[random_int(0, strlen($minusculas) - 1)];
    $clave[2] = $numeros[random_int(0, strlen($numeros) - 1)];
    $clave[3] = $simbolos[random_int(0, strlen($simbolos) - 1)];

    return $clave;
}

# Alta de usuarios desde el panel de administración. Valida los campos,
# comprueba que la cédula y el correo no existan y guarda la contraseña
# siempre encriptada con password_hash.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario']) && tienePermiso('crear_usuarios') && $csrf_valido) {
    # Roles que el administrador puede asignar al crear una cuenta.
    $roles_validos = ['cliente', 'gestor', 'admin'];

    $nueva_cedula   = trim($_POST['nueva_cedula'] ?? '');
    $nuevo_nombre   = trim($_POST['nuevo_nombre'] ?? '');
    $nuevo_email    = strtolower(trim($_POST['nuevo_email'] ?? ''));
    $nuevo_telefono = trim($_POST['nuevo_telefono'] ?? '');
    $nuevo_rol      = strtolower(trim($_POST['nuevo_rol'] ?? 'cliente'));
    $nueva_clave    = $_POST['nueva_clave'] ?? '';
    $clave_automatica = !empty($_POST['clave_automatica']);

    if ($nueva_cedula === '' || $nuevo_nombre === '' || $nuevo_email === '' || $nuevo_telefono === '') {
        $mensaje = $lang['alta_completa'] ?? 'Por favor completa todos los campos obligatorios.';
        $tipo_alerta = 'alert-error';
    } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = $lang['alta_email_invalido'] ?? 'El correo electrónico no es válido.';
        $tipo_alerta = 'alert-error';
    } elseif (!preg_match('/^\+?[0-9 ()-]{7,20}$/', $nuevo_telefono)) {
        $mensaje = $lang['alta_telefono_invalido'] ?? 'Ingresa un número de celular válido.';
        $tipo_alerta = 'alert-error';
    } elseif (!in_array($nuevo_rol, $roles_validos, true)) {
        $mensaje = $lang['alta_rol_invalido'] ?? 'El rol seleccionado no es válido.';
        $tipo_alerta = 'alert-error';
    } else {
        # Si el admin lo pide, la clave se genera en el servidor y se muestra
        # una sola vez para poder comunicársela al usuario.
        if ($clave_automatica) {
            $nueva_clave = generar_clave_temporal();
        }

        if (strlen($nueva_clave) < 8) {
            $mensaje = $lang['alta_clave_corta'] ?? 'La contraseña debe tener al menos 8 caracteres.';
            $tipo_alerta = 'alert-error';
        } else {
            try {
                # No se permite repetir cédula ni correo electrónico.
                $verificar = $conexion->prepare('SELECT cedula FROM usuario WHERE cedula = :cedula OR email = :email LIMIT 1');
                $verificar->execute(['cedula' => $nueva_cedula, 'email' => $nuevo_email]);

                if ($verificar->fetchColumn()) {
                    $mensaje = $lang['alta_duplicado'] ?? 'Ya existe un usuario con esa cédula o correo electrónico.';
                    $tipo_alerta = 'alert-error';
                } else {
                    $hash = password_hash($nueva_clave, PASSWORD_DEFAULT);
                    $ins = $conexion->prepare('INSERT INTO usuario (cedula, nombre, email, telefono, password, rol) VALUES (:cedula, :nombre, :email, :telefono, :password, :rol)');
                    $ins->execute([
                        'cedula' => $nueva_cedula,
                        'nombre' => $nuevo_nombre,
                        'email' => $nuevo_email,
                        'telefono' => $nuevo_telefono,
                        'password' => $hash,
                        'rol' => $nuevo_rol
                    ]);

                    $mensaje = $lang['alta_exito'] ?? '¡Usuario creado exitosamente!';
                    if ($clave_automatica) {
                        $mensaje .= ' ' . ($lang['clave_temporal'] ?? 'Clave temporal: ') . $nueva_clave;
                    }
                }
            } catch (PDOException $e) {
                # Mensaje generico: no se expone el detalle interno del error.
                $mensaje = $lang['error_operacion'] ?? 'Ocurrió un error.';
                $tipo_alerta = 'alert-error';
            }
        }
    }
}

# Eliminar un usuario si llega el POST con eliminar_cedula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_cedula']) && tienePermiso('eliminar_usuarios') && $csrf_valido) {
    $cedula_a_eliminar = $_POST['eliminar_cedula'];
    try {
        $del = $conexion->prepare("DELETE FROM usuario WHERE cedula = :cedula");
        $del->execute(['cedula' => $cedula_a_eliminar]);
        $mensaje = $lang['usuario_eliminado'] ?? '¡Usuario eliminado exitosamente!';
    } catch (PDOException $e) {
        # Mensaje generico: el detalle de $e->getMessage() no se muestra al
        # usuario para no filtrar info de la base de datos.
        $mensaje = $lang['error_operacion'] ?? 'Error';
        $tipo_alerta = 'alert-error';
    }
}

# Actualiza el rol de un usuario si llega el POST con id_usuario y nuevo_rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'], $_POST['nuevo_rol']) && tienePermiso('cambiar_roles') && $csrf_valido) {
    $id_usr = $_POST['id_usuario'];
    $n_rol = $_POST['nuevo_rol'];

    try {
        $upd = $conexion->prepare("UPDATE usuario SET rol = :rol WHERE cedula = :id OR email = :id");
        $upd->execute(['rol' => $n_rol, 'id' => $id_usr]);
        $mensaje = $lang['rol_actualizado'] ?? '¡Rol actualizado exitosamente!';
    } catch (PDOException $e) {
        # Igual que en la eliminacion: mensaje generico sin detalles internos.
        $mensaje = $lang['error_operacion'] ?? 'Error';
        $tipo_alerta = 'alert-error';
    }
}

# Filtros de búsqueda del listado: texto libre (cédula, nombre, correo o
# teléfono) y rol exacto. Ambos llegan por GET y se combinan con AND.
$filtro_texto = trim($_GET['q'] ?? '');
$filtro_rol = strtolower(trim($_GET['rol_filtro'] ?? ''));
$roles_filtro = ['cliente', 'gestor', 'admin'];
if (!in_array($filtro_rol, $roles_filtro, true)) {
    $filtro_rol = '';
}

# Trae los usuarios para listarlos en la tabla, aplicando los filtros.
# El termino de busqueda se escapa para que los comodines % y _ se
# busquen como texto literal (ESCAPE '!').
$condiciones = [];
$parametros = [];

if ($filtro_texto !== '') {
    $termino_buscado = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtro_texto) . '%';
    $condiciones[] = "(cedula LIKE :texto ESCAPE '!' OR nombre LIKE :texto ESCAPE '!' OR email LIKE :texto ESCAPE '!' OR telefono LIKE :texto ESCAPE '!')";
    $parametros['texto'] = $termino_buscado;
}

if ($filtro_rol !== '') {
    $condiciones[] = 'LOWER(rol) = :rol';
    $parametros['rol'] = $filtro_rol;
}

// Cargar saldo a la cuenta prepaga de un cliente: es con lo que el cliente
// puede pagar directo desde la aplicacion, sin pasar por el taller.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_saldo'], $_POST['cedula_saldo']) && tienePermiso('administrar_saldo_prepago') && $csrf_valido) {
    $cedula_saldo = trim((string) $_POST['cedula_saldo']);
    $saldo_saldo = trim((string) ($_POST['saldo_saldo'] ?? ''));
    if ($cedula_saldo !== '' && is_numeric($saldo_saldo) && (float) $saldo_saldo >= 0) {
        try {
            $stmt = $conexion->prepare('UPDATE usuario SET saldo_prepago = :saldo WHERE cedula = :cedula');
            $stmt->execute(['saldo' => number_format((float) $saldo_saldo, 2, '.', ''), 'cedula' => $cedula_saldo]);
            $mensaje = $lang_code === 'en' ? 'Prepaid balance updated.' : 'Saldo prepago actualizado.';
        } catch (PDOException $e) {
            $mensaje = $lang_code === 'en' ? 'The balance could not be updated.' : 'No se pudo actualizar el saldo.';
            $tipo_alerta = 'alert-error';
        }
    }
}

$sql_usuarios = 'SELECT * FROM usuario';
if (!empty($condiciones)) {
    $sql_usuarios .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql_usuarios .= ' ORDER BY nombre';
try {
    $stmt = $conexion->prepare($sql_usuarios);
    $stmt->execute($parametros);
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
        .card-alta { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
        .card-alta h3 { font-size: 16px; color: #38bdf8; margin-bottom: 4px; }
        .card-alta p.subtitulo { font-size: 13px; color: #94a3b8; margin-bottom: 16px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 13px; color: #94a3b8; }
        .form-group input { padding: 9px 12px; border-radius: 6px; border: 1px solid #475569; background-color: rgba(15, 23, 42, 0.9); color: white; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #38bdf8; }
        .form-group select { padding: 9px 12px; border-radius: 6px; border: 1px solid #475569; background-color: rgba(15, 23, 42, 0.9); color: white; font-size: 14px; }
        .check-group { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #cbd5e1; }
        .check-group input { width: auto; }
        .form-actions { margin-top: 16px; }
        .form-actions button { padding: 10px 18px; font-size: 14px; }
        .filtros-bar { display: grid; grid-template-columns: 2fr 1fr auto; gap: 14px; align-items: end; }
        .filtros-bar .form-group { gap: 6px; }
        .filtros-bar button { padding: 10px 18px; font-size: 14px; }
        .limpiar-filtros { font-size: 13px; color: #38bdf8; text-decoration: none; font-weight: 600; white-space: nowrap; }
        .limpiar-filtros:hover { text-decoration: underline; }
        .resultados-busqueda { font-size: 13px; color: #94a3b8; margin: 0 0 12px; }
        @media (max-width: 720px) {
            .filtros-bar { grid-template-columns: 1fr; }
        }
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

        <!-- Barra de filtros: texto libre y rol. Se envia por GET para poder
             compartir el enlace de la busqueda y conservarla al recargar. -->
        <div class="card-alta" style="padding: 16px;">
            <form action="administrar_usuarios.php" method="GET" class="filtros-bar">
                <div class="form-group">
                    <label for="q"><?php echo $lang['buscar_usuario'] ?? 'Buscar por cédula, nombre, email o teléfono'; ?></label>
                    <input type="text" id="q" name="q" maxlength="150" value="<?php echo htmlspecialchars($filtro_texto); ?>" placeholder="<?php echo htmlspecialchars($lang['buscar_usuario'] ?? 'Buscar'); ?>">
                </div>
                <div class="form-group">
                    <label for="rol_filtro"><?php echo $lang['filtro_rol'] ?? 'Filtrar por rol'; ?></label>
                    <select id="rol_filtro" name="rol_filtro">
                        <option value=""><?php echo $lang['todos_los_roles'] ?? 'Todos los roles'; ?></option>
                        <option value="cliente" <?php echo ($filtro_rol === 'cliente') ? 'selected' : ''; ?>>Cliente</option>
                        <option value="gestor" <?php echo ($filtro_rol === 'gestor') ? 'selected' : ''; ?>>Gestor</option>
                        <option value="admin" <?php echo ($filtro_rol === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group" style="justify-content: flex-end;">
                    <button type="submit"><?php echo $lang['buscar'] ?? 'Buscar'; ?></button>
                    <?php if ($filtro_texto !== '' || $filtro_rol !== ''): ?>
                        <a href="administrar_usuarios.php" class="limpiar-filtros"><?php echo $lang['limpiar_filtros'] ?? 'Limpiar filtros'; ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <p class="resultados-busqueda">
            <?php echo count($usuarios); ?> <?php echo $lang['resultados'] ?? 'resultados'; ?>
        </p>

        <?php if (!empty($mensaje)): ?>
            <div class="<?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <div class="card-alta">
            <h3><?php echo $lang['alta_usuario'] ?? 'Alta de Usuario'; ?></h3>
            <p class="subtitulo"><?php echo $lang['alta_descripcion'] ?? 'Registra una cuenta nueva y asigna su rol.'; ?></p>
            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="crear_usuario" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nueva_cedula"><?php echo $lang['cedula'] ?? 'Cédula'; ?></label>
                        <input type="text" id="nueva_cedula" name="nueva_cedula" required maxlength="20" placeholder="Ej: 12345678">
                    </div>
                    <div class="form-group">
                        <label for="nuevo_nombre"><?php echo $lang['nombre'] ?? 'Nombre'; ?></label>
                        <input type="text" id="nuevo_nombre" name="nuevo_nombre" required maxlength="100" placeholder="<?php echo $lang_code === 'en' ? 'Full name' : 'Nombre completo'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="nuevo_email"><?php echo $lang['email'] ?? 'Email'; ?></label>
                        <input type="email" id="nuevo_email" name="nuevo_email" required maxlength="150" placeholder="correo@email.com">
                    </div>
                    <div class="form-group">
                        <label for="nuevo_telefono"><?php echo $lang['telefono'] ?? 'Teléfono celular'; ?></label>
                        <input type="tel" id="nuevo_telefono" name="nuevo_telefono" required maxlength="20" inputmode="tel" pattern="\+?[0-9 ()-]{7,20}" placeholder="+598 99 123 456">
                    </div>
                    <div class="form-group">
                        <label for="nuevo_rol"><?php echo $lang['rol_actual'] ?? 'Rol Actual'; ?></label>
                        <select id="nuevo_rol" name="nuevo_rol">
                            <option value="cliente">Cliente</option>
                            <option value="gestor">Gestor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nueva_clave"><?php echo $lang['contrasena'] ?? 'Contraseña'; ?></label>
                        <input type="password" id="nueva_clave" name="nueva_clave" minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 14px;">
                    <label class="check-group" for="clave_automatica">
                        <input type="checkbox" id="clave_automatica" name="clave_automatica" value="1">
                        <span><?php echo $lang['clave_automatica'] ?? 'Generar una contraseña temporal automáticamente'; ?></span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit"><?php echo $lang['crear_usuario'] ?? 'Crear Usuario'; ?></button>
                </div>
            </form>
        </div>

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
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
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
                                <form action="" method="POST" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode($lang['confirmar_eliminar'] ?? '¿Estás seguro de que deseas eliminar este usuario?'), ENT_QUOTES, 'UTF-8'); ?>);">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                    <input type="hidden" name="eliminar_cedula" value="<?php echo htmlspecialchars($cedula_usr); ?>">
                                    <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> <?php echo $lang['eliminar'] ?? 'Eliminar'; ?></button>
                                </form>
                                <!-- Carga de saldo a la cuenta prepaga del cliente -->
                                <form action="" method="POST" style="margin-top: 8px; display: flex; gap: 6px; align-items: center;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                    <input type="hidden" name="accion_saldo" value="1">
                                    <input type="hidden" name="cedula_saldo" value="<?php echo htmlspecialchars($cedula_usr); ?>">
                                    <input type="number" step="0.01" min="0" name="saldo_saldo" value="<?php echo htmlspecialchars(number_format((float) ($u['saldo_prepago'] ?? 0), 2, '.', '')); ?>" style="width: 95px; background: rgba(15,23,42,0.9); color: white; border: 1px solid #475569; border-radius: 6px; padding: 6px 8px; font-size: 12px;" title="<?php echo $lang_code === 'en' ? 'Prepaid balance' : 'Saldo prepago'; ?>">
                                    <button type="submit" style="background:#334155; color:#cbd5e1; border:none; border-radius:6px; padding:6px 10px; font-size:12px; font-weight:600; cursor:pointer;"><?php echo $lang_code === 'en' ? 'Balance' : 'Saldo'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #94a3b8;"><?php echo ($filtro_texto !== '' || $filtro_rol !== '') ? ($lang['sin_resultados'] ?? 'No hay usuarios que coincidan con la búsqueda actual.') : ($lang['no_usuarios'] ?? 'No hay usuarios registrados.'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $lang_code === 'en' ? 'Global Workshop Services. All rights reserved.' : 'Taller Global de Servicios. Todos los derechos reservados.'; ?></p>
    </footer>
    <script>
        // Si se pide una clave temporal, el campo de clave manual se desactiva
        // para no confundir al administrador con dos contraseñas simultaneas.
        (function () {
            var check = document.getElementById('clave_automatica');
            var campo = document.getElementById('nueva_clave');
            if (!check || !campo) { return; }
            function sincronizar() {
                if (check.checked) {
                    campo.value = '';
                    campo.disabled = true;
                    campo.removeAttribute('minlength');
                } else {
                    campo.disabled = false;
                    campo.setAttribute('minlength', '8');
                }
            }
            check.addEventListener('change', sincronizar);
            sincronizar();
        })();
    </script>
</body>
</html>
