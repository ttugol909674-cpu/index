<?php
/**
 * TGS - Taller Global de Servicios
 * Perfil del usuario autenticado.
 *
 * Permite a cualquier usuario con sesion activa (cliente, gestor o admin)
 * actualizar sus datos de contacto (nombre, correo y telefono) y cambiar su
 * contraseña.
 *
 * Reglas de negocio:
 * - La cedula identifica al usuario y no se puede modificar desde la pagina.
 * - El correo electronico debe seguir siendo unico en la tabla usuario.
 * - El cambio de contraseña exige la contraseña actual y la confirmacion
 *   de la nueva; la nueva debe tener al menos 8 caracteres.
 *
 * Seguridad:
 * - Token CSRF en todas las consultas que modifican datos.
 * - Consultas preparadas contra SQL injection.
 * - La contraseña se guarda siempre con password_hash y se verifica con
 *   password_verify; nunca se muestra ni se compara en texto plano.
 * - Salida de datos con htmlspecialchars.
 */
require_once 'auth.php';
verificarPermiso('editar_perfil');
require_once 'conexion.php';

# Idioma de la pantalla. Un valor explícito (?lang=es o ?lang=en) manda
# sobre el que hay en la sesión.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang_code = $_SESSION['lang'] ?? 'es';

$texto = $lang_code === 'en' ? [
    'titulo' => 'My Profile',
    'datos' => 'Personal data',
    'nombre' => 'Full name',
    'email' => 'Email address',
    'telefono' => 'Mobile phone',
    'cedula' => 'ID number',
    'rol' => 'Role',
    'clave' => 'Password',
    'clave_actual' => 'Current password',
    'clave_nueva' => 'New password',
    'clave_confirmar' => 'Confirm new password',
    'cambiar_clave' => 'Change password',
    'cambiar_clave_desc' => 'Leave the fields empty if you do not want to change your password.',
    'guardar' => 'Save changes',
    'volver' => 'Back',
    'exito' => 'Profile updated successfully.',
    'exito_clave' => 'Password changed successfully.',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_completa' => 'Please complete all the required fields.',
    'error_email' => 'The email address is not valid.',
    'error_email_duplicado' => 'That email address is already in use by another user.',
    'error_telefono' => 'Enter a valid mobile phone number.',
    'error_clave_actual' => 'The current password is not correct.',
    'error_clave_corta' => 'The new password must be at least 8 characters long.',
    'error_clave_distinta' => 'The new password and its confirmation do not match.',
    'error_operacion' => 'The profile could not be updated.',
    'volver_panel' => 'Back to my panel',
    'baja_titulo' => 'Delete my account',
    'baja_descripcion' => 'If you no longer want to use TGS you can delete your account. All your data (appointments, vehicles, payments and budgets) will be deleted.',
    'baja_boton' => 'Delete my account',
    'marca' => 'Global Workshop Services',
    'derechos' => 'All rights reserved.'
] : [
    'titulo' => 'Mi Perfil',
    'datos' => 'Datos personales',
    'nombre' => 'Nombre Completo',
    'email' => 'Correo Electrónico',
    'telefono' => 'Teléfono celular',
    'cedula' => 'Cédula',
    'rol' => 'Rol',
    'clave' => 'Contraseña',
    'clave_actual' => 'Contraseña actual',
    'clave_nueva' => 'Contraseña nueva',
    'clave_confirmar' => 'Confirmar contraseña nueva',
    'cambiar_clave' => 'Cambiar contraseña',
    'cambiar_clave_desc' => 'Deja los campos vacíos si no quieres cambiar tu contraseña.',
    'guardar' => 'Guardar cambios',
    'volver' => 'Volver',
    'exito' => 'Perfil actualizado exitosamente.',
    'exito_clave' => 'Contraseña actualizada exitosamente.',
    'error_csrf' => 'La sesión expiró. Recarga la página e intenta nuevamente.',
    'error_completa' => 'Por favor completa todos los campos obligatorios.',
    'error_email' => 'El correo electrónico no es válido.',
    'error_email_duplicado' => 'Ese correo electrónico ya está en uso por otro usuario.',
    'error_telefono' => 'Ingresa un número de celular válido.',
    'error_clave_actual' => 'La contraseña actual no es correcta.',
    'error_clave_corta' => 'La contraseña nueva debe tener al menos 8 caracteres.',
    'error_clave_distinta' => 'La contraseña nueva y su confirmación no coinciden.',
    'error_operacion' => 'No se pudo actualizar el perfil.',
    'volver_panel' => 'Volver a mi panel',
    'baja_titulo' => 'Dar de baja mi cuenta',
    'baja_descripcion' => 'Si ya no querés usar TGS podés eliminar tu cuenta. Se borran todos tus datos (turnos, vehículos, cobros y presupuestos).',
    'baja_boton' => 'Eliminar mi cuenta',
    'marca' => 'Taller Global de Servicios',
    'derechos' => 'Todos los derechos reservados.'
];

# Cédula del usuario autenticado: es el identificador de todas las consultas.
$cedula_usuario = $_SESSION['cedula'] ?? '';

# Datos actuales del usuario (se rellenan el formulario y se muestran).
$datos_usuario = [
    'nombre' => $_SESSION['nombre'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'telefono' => $_SESSION['telefono'] ?? '',
    'rol' => $_SESSION['rol'] ?? ''
];

# Si la pagina se abre con ?lang=en, el enlace de "volver" tambien va en inglés.
$panel_destino = 'reservas.php';
if (normalizarRol($datos_usuario['rol']) === 'admin') {
    $panel_destino = 'dashboard_admin.php';
} elseif (normalizarRol($datos_usuario['rol']) === 'gestor') {
    $panel_destino = 'dashboard_gestor.php';
}

$error = '';
$exito = '';
$cambio_clave = false;

# Procesa el formulario de perfil (POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    # Token CSRF: evita que otra pagina envie el formulario en nombre del usuario.
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $error = $texto['error_csrf'];
    } else {
        $nuevo_nombre = trim($_POST['nombre'] ?? '');
        $nuevo_email = strtolower(trim($_POST['email'] ?? ''));
        $nuevo_telefono = trim($_POST['telefono'] ?? '');
        $clave_actual = $_POST['clave_actual'] ?? '';
        $clave_nueva = $_POST['clave_nueva'] ?? '';
        $clave_confirmar = $_POST['clave_confirmar'] ?? '';

        if ($nuevo_nombre === '' || $nuevo_email === '' || $nuevo_telefono === '') {
            $error = $texto['error_completa'];
        } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
            $error = $texto['error_email'];
        } elseif (!preg_match('/^\+?[0-9 ()-]{7,20}$/', $nuevo_telefono)) {
            $error = $texto['error_telefono'];
        } else {
            try {
                # El correo no puede pertenecer a otro usuario.
                $verificar_email = $conexion->prepare('SELECT cedula FROM usuario WHERE email = :email AND cedula <> :cedula LIMIT 1');
                $verificar_email->execute(['email' => $nuevo_email, 'cedula' => $cedula_usuario]);

                if ($verificar_email->fetchColumn()) {
                    $error = $texto['error_email_duplicado'];
                } else {
                    # Si se completo el bloque de contraseña, se valida y se cambia.
                    $hash_nuevo = null;
                    if ($clave_nueva !== '' || $clave_confirmar !== '' || $clave_actual !== '') {
                        $consulta_hash = $conexion->prepare('SELECT password FROM usuario WHERE cedula = :cedula');
                        $consulta_hash->execute(['cedula' => $cedula_usuario]);
                        $hash_actual = $consulta_hash->fetchColumn();

                        if (!$hash_actual || !password_verify($clave_actual, $hash_actual)) {
                            $error = $texto['error_clave_actual'];
                        } elseif (strlen($clave_nueva) < 8) {
                            $error = $texto['error_clave_corta'];
                        } elseif ($clave_nueva !== $clave_confirmar) {
                            $error = $texto['error_clave_distinta'];
                        } else {
                            $hash_nuevo = password_hash($clave_nueva, PASSWORD_DEFAULT);
                        }
                    }

                    if ($error === '') {
                        if ($hash_nuevo !== null) {
                            $actualizar = $conexion->prepare('UPDATE usuario SET nombre = :nombre, email = :email, telefono = :telefono, password = :password WHERE cedula = :cedula');
                            $actualizar->execute([
                                'nombre' => $nuevo_nombre,
                                'email' => $nuevo_email,
                                'telefono' => $nuevo_telefono,
                                'password' => $hash_nuevo,
                                'cedula' => $cedula_usuario
                            ]);
                            $cambio_clave = true;
                        } else {
                            $actualizar = $conexion->prepare('UPDATE usuario SET nombre = :nombre, email = :email, telefono = :telefono WHERE cedula = :cedula');
                            $actualizar->execute([
                                'nombre' => $nuevo_nombre,
                                'email' => $nuevo_email,
                                'telefono' => $nuevo_telefono,
                                'cedula' => $cedula_usuario
                            ]);
                        }

                        # La sesion se actualiza para que el nombre y el correo
                        # cambien también en la barra de navegación.
                        $_SESSION['nombre'] = $nuevo_nombre;
                        $_SESSION['email'] = $nuevo_email;
                        $_SESSION['telefono'] = $nuevo_telefono;

                        $datos_usuario['nombre'] = $nuevo_nombre;
                        $datos_usuario['email'] = $nuevo_email;
                        $datos_usuario['telefono'] = $nuevo_telefono;

                        $exito = $cambio_clave ? $texto['exito_clave'] : $texto['exito'];
                    }
                }
            } catch (PDOException $e) {
                # Mensaje generico: no se expone el detalle interno del error.
                $error = $texto['error_operacion'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            background: linear-gradient(rgba(11, 19, 41, 0.85), rgba(11, 19, 41, 0.95)), url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1920&auto=format&fit=crop') no-repeat center center fixed;
            background-size: cover;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        /* Cabecera del area de cliente: navegacion, idioma y cierre de sesion. */
        .acciones-cabecera { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .nav-enlace { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-enlace:hover { color: #38bdf8; }
        .nav-enlace.activo { color: #38bdf8; font-weight: 700; }
        .btn-salir { background-color: #ef4444; color: #ffffff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .btn-salir:hover { background-color: #dc2626; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .perfil-container { flex: 1; padding: 40px 20px; width: 100%; max-width: 640px; margin: 0 auto; }
        .perfil-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); }
        .perfil-card h2 { font-size: 22px; color: #38bdf8; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #475569; background-color: rgba(15, 23, 42, 0.9); color: white; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #38bdf8; }
        .form-group input:disabled { background-color: rgba(15, 23, 42, 0.6); color: #94a3b8; }
        .bloque-clave { margin-top: 24px; padding-top: 20px; border-top: 1px solid #334155; }
        .bloque-clave h3 { font-size: 15px; color: #38bdf8; margin-bottom: 4px; }
        .bloque-clave p.subtitulo { font-size: 12px; color: #94a3b8; margin-bottom: 14px; }
        .btn-submit { background-color: #2563eb; color: white; padding: 12px 22px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background-color: #1d4ed8; }
        .btn-volver { display: inline-block; margin-top: 18px; color: #38bdf8; text-decoration: none; font-size: 14px; font-weight: 600; }
        .btn-volver:hover { text-decoration: underline; }
        .bloque-baja { margin-top: 26px; padding-top: 20px; border-top: 1px solid #334155; }
        .bloque-baja h3 { font-size: 15px; color: #f87171; margin-bottom: 6px; }
        .bloque-baja p { font-size: 13px; color: #94a3b8; margin-bottom: 12px; }
        .btn-baja { display: inline-block; background: transparent; color: #f87171; border: 1px solid #ef4444; border-radius: 8px; padding: 9px 16px; font-size: 13px; font-weight: 600; text-decoration: none; }
        .btn-baja:hover { background: rgba(239, 68, 68, 0.12); }
        .alert-error { background-color: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background-color: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #4ade80; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>
    <header>
        <a href="index.php" class="logo">TGS - Blue Tech Uruguay</a>
        <div class="acciones-cabecera">
            <a href="reservas.php" class="nav-enlace"><?php echo $lang_code === 'en' ? 'My appointments' : 'Mis turnos'; ?></a>
            <a href="presupuestos.php" class="nav-enlace"><?php echo $lang_code === 'en' ? 'My budgets' : 'Mis presupuestos'; ?></a>
            <a href="pagos_cliente.php" class="nav-enlace"><?php echo $lang_code === 'en' ? 'My payments' : 'Mis pagos'; ?></a>
            <a href="perfil.php" class="nav-enlace activo"><?php echo $lang_code === 'en' ? 'My profile' : 'Mi perfil'; ?></a>
            <!-- Selector de idioma de la pantalla de perfil -->
            <div class="language-selector" aria-label="Language selector">
                <a href="perfil.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a>
                <a href="perfil.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
            <a href="logout.php" class="btn-salir"><?php echo $lang_code === 'en' ? 'Sign Out' : 'Cerrar Sesión'; ?></a>
        </div>
    </header>

    <div class="perfil-container">
        <div class="perfil-card">
            <h2><?php echo $texto['titulo']; ?></h2>

            <?php if ($error !== ''): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($exito !== ''): ?>
                <div class="alert-success"><?php echo htmlspecialchars($exito); ?></div>
            <?php endif; ?>

            <form action="perfil.php" method="POST" autocomplete="off">
                <!-- Token CSRF: valida que el formulario venga de esta sesion -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="actualizar_perfil" value="1">

                <div class="form-group">
                    <label for="cedula"><?php echo $texto['cedula']; ?></label>
                    <input type="text" id="cedula" value="<?php echo htmlspecialchars($cedula_usuario); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="nombre"><?php echo $texto['nombre']; ?></label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars($datos_usuario['nombre']); ?>">
                </div>

                <div class="form-group">
                    <label for="email"><?php echo $texto['email']; ?></label>
                    <input type="email" id="email" name="email" maxlength="150" required value="<?php echo htmlspecialchars($datos_usuario['email']); ?>">
                </div>

                <div class="form-group">
                    <label for="telefono"><?php echo $texto['telefono']; ?></label>
                    <input type="tel" id="telefono" name="telefono" maxlength="20" required inputmode="tel" pattern="\+?[0-9 ()-]{7,20}" value="<?php echo htmlspecialchars($datos_usuario['telefono']); ?>">
                </div>

                <div class="bloque-clave">
                    <h3><?php echo $texto['cambiar_clave']; ?></h3>
                    <p class="subtitulo"><?php echo $texto['cambiar_clave_desc']; ?></p>

                    <div class="form-group">
                        <label for="clave_actual"><?php echo $texto['clave_actual']; ?></label>
                        <input type="password" id="clave_actual" name="clave_actual" autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label for="clave_nueva"><?php echo $texto['clave_nueva']; ?></label>
                        <input type="password" id="clave_nueva" name="clave_nueva" minlength="8" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="clave_confirmar"><?php echo $texto['clave_confirmar']; ?></label>
                        <input type="password" id="clave_confirmar" name="clave_confirmar" minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn-submit"><?php echo $texto['guardar']; ?></button>
            </form>

            <a href="<?php echo $panel_destino; ?>?lang=<?php echo $lang_code; ?>" class="btn-volver">&larr; <?php echo $texto['volver_panel']; ?></a>

            <?php if (tienePermiso('eliminar_cuenta')): ?>
                <!-- Baja de cuenta: borra la cuenta y todos sus datos -->
                <div class="bloque-baja">
                    <h3><?php echo $texto['baja_titulo']; ?></h3>
                    <p><?php echo $texto['baja_descripcion']; ?></p>
                    <a href="eliminar_cuenta.php?lang=<?php echo $lang_code; ?>" class="btn-baja"><?php echo $texto['baja_boton']; ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $texto['marca']; ?>. <?php echo $texto['derechos']; ?></p>
    </footer>
</body>
</html>
