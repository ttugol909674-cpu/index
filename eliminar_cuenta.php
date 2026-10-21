<?php
/**
 * TGS - Taller Global de Servicios
 * Baja de cuenta del cliente.
 *
 * Pantalla donde el cliente puede eliminar su propia cuenta. Al confirmar,
 * se borran la cuenta y todos los datos que dependen de ella:
 *   - vehiculos y reservas del cliente (por ON DELETE CASCADE)
 *   - cobros, presupuestos y avisos de esas reservas (cascada)
 *   - avisos que no tenian reserva asociada (borrado explicito)
 *
 * Reglas de negocio:
 * - Solo el rol cliente puede llegar a esta pantalla (permiso
 *   'eliminar_cuenta'), y solo puede borrar SU cuenta: no se recibe ningun
 *   identificador de usuario, se usa siempre el de la sesion.
 * - Se pide la contraseña actual y la confirmacion escrita "ELIMINAR" para
 *   evitar borrados por error.
 * - No se puede eliminar la ultima cuenta de administrador del sistema.
 *
 * Seguridad:
 * - Token CSRF en la confirmacion.
 * - Verificacion de la contraseña actual con password_verify.
 * - Todas las consultas usan la cédula de la sesión, nunca un id recibido.
 *
 * Dependencias: auth.php, conexion.php
 */
require_once 'auth.php';
verificarPermiso('eliminar_cuenta');
require_once 'conexion.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$cedula_usuario = $_SESSION['cedula'] ?? '';

$texto = $lang_code === 'en' ? [
    'titulo' => 'Delete my account',
    'resumen' => 'This permanently deletes your account and everything attached to it.',
    'advertencia' => 'This action cannot be undone.',
    'que_se_borra' => 'The following will be deleted:',
    'vehiculo' => 'Vehicle', 'reserva' => 'Appointment', 'pago' => 'Payment', 'presupuesto' => 'Budget', 'aviso' => 'Notice',
    'contrasena' => 'Current password',
    'confirmacion' => 'Type DELETE to confirm',
    'eliminar' => 'Delete my account',
    'cancelar' => 'Keep my account',
    'volver' => 'Back to my profile',
    'error_csrf' => 'The session expired. Please reload the page and try again.',
    'error_clave' => 'The current password is not correct.',
    'error_confirmacion' => 'Type DELETE exactly to confirm.',
    'error_operacion' => 'The account could not be deleted.',
    'exito' => 'Your account was deleted successfully.',
    'palabras' => 'appointments, vehicles, payments, budgets and notices',
    'singular' => 'appointment', 'singular_vehiculo' => 'vehicle'
] : [
    'titulo' => 'Baja de mi cuenta',
    'resumen' => 'Se elimina tu cuenta y todos los datos que dependen de ella.',
    'advertencia' => 'Esta acción no se puede deshacer.',
    'que_se_borra' => 'Se va a eliminar:',
    'vehiculo' => 'Vehículo', 'reserva' => 'Turno', 'pago' => 'Cobro', 'presupuesto' => 'Presupuesto', 'aviso' => 'Aviso',
    'contrasena' => 'Contraseña actual',
    'confirmacion' => 'Escribí ELIMINAR para confirmar',
    'eliminar' => 'Eliminar mi cuenta',
    'cancelar' => 'Conservar mi cuenta',
    'volver' => 'Volver a mi perfil',
    'error_csrf' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
    'error_clave' => 'La contraseña actual no es correcta.',
    'error_confirmacion' => 'Escribí ELIMINAR exactamente para confirmar.',
    'error_operacion' => 'No se pudo eliminar la cuenta.',
    'exito' => 'Tu cuenta fue eliminada correctamente.',
    'palabras' => 'turnos, vehículos, cobros, presupuestos y avisos',
    'singular' => 'turno', 'singular_vehiculo' => 'vehículo'
];

$mensaje = '';
$tipo_alerta = 'alert-error';

// ---------------------------------------------------------------------------
// Resumen de lo que se va a borrar
// ---------------------------------------------------------------------------
$resumen_datos = ['vehiculos' => 0, 'reservas' => 0, 'pagos' => 0, 'presupuestos' => 0, 'avisos' => 0];
try {
    $consulta = $conexion->prepare(
        'SELECT
            (SELECT COUNT(*) FROM vehiculo WHERE cedula_usuario = :c1) AS vehiculos,
            (SELECT COUNT(*) FROM reserva WHERE cedula_usuario = :c2) AS reservas,
            (SELECT COUNT(*) FROM pago p JOIN reserva r ON r.id_reserva = p.id_reserva WHERE r.cedula_usuario = :c3) AS pagos,
            (SELECT COUNT(*) FROM presupuesto WHERE cedula_usuario = :c4) AS presupuestos,
            (SELECT COUNT(*) FROM notificacion WHERE cedula_usuario = :c5) AS avisos'
    );
    $consulta->execute([
        'c1' => $cedula_usuario,
        'c2' => $cedula_usuario,
        'c3' => $cedula_usuario,
        'c4' => $cedula_usuario,
        'c5' => $cedula_usuario
    ]);
    $fila = $consulta->fetch(PDO::FETCH_ASSOC);
    if ($fila) {
        $resumen_datos = array_map('intval', $fila);
    }
} catch (PDOException $e) {
    // Si no se puede calcular el resumen, se muestra en cero.
}

// ---------------------------------------------------------------------------
// Confirmacion de la baja
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $texto['error_csrf'];
    } else {
        $clave = (string) ($_POST['password'] ?? '');
        $confirmacion = trim((string) ($_POST['confirmacion'] ?? ''));
        $palabra = ($lang_code === 'en') ? 'DELETE' : 'ELIMINAR';

        if ($confirmacion !== $palabra) {
            $mensaje = $texto['error_confirmacion'];
        } else {
            try {
                // Se verifica la contraseña actual del usuario de la sesión.
                $consulta = $conexion->prepare('SELECT password FROM usuario WHERE cedula = :cedula');
                $consulta->execute(['cedula' => $cedula_usuario]);
                $hash = $consulta->fetchColumn();

                if (!$hash || !password_verify($clave, $hash)) {
                    $mensaje = $texto['error_clave'];
                } else {
                    // Todo el borrado va en una transacción: o se borra
                    // todo o no se borra nada.
                    $conexion->beginTransaction();

                    // Avisos sin reserva asociada (no los cubre la cascada).
                    $borrar_avisos = $conexion->prepare('DELETE FROM notificacion WHERE cedula_usuario = :cedula_usuario');
                    $borrar_avisos->execute(['cedula_usuario' => $cedula_usuario]);

                    // La cascada de la base borra vehiculos, reservas, y de
                    // ellas los cobros, presupuestos y avisos asociados.
                    $borrar_usuario = $conexion->prepare('DELETE FROM usuario WHERE cedula = :cedula');
                    $borrar_usuario->execute(['cedula' => $cedula_usuario]);

                    $conexion->commit();

                    // Se cierra la sesión: la cuenta ya no existe.
                    $_SESSION = [];
                    if (ini_get('session.use_cookies')) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
                    }
                    session_destroy();

                    header('Location: index.php?cuenta_eliminada=1');
                    exit;
                }
            } catch (PDOException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                $mensaje = $texto['error_operacion'];
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
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
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
        .container { flex: 1; padding: 40px 20px; max-width: 620px; width: 100%; margin: 0 auto; }
        .tarjeta { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 28px 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); }
        .tarjeta h2 { font-size: 22px; color: #38bdf8; margin-bottom: 8px; }
        .tarjeta p { color: #cbd5e1; font-size: 14px; margin-bottom: 14px; }
        .advertencia { background-color: rgba(239, 68, 68, 0.12); border: 1px solid #ef4444; color: #fca5a5; border-radius: 8px; padding: 12px 14px; font-size: 14px; font-weight: 600; margin-bottom: 18px; }
        .lista-borrado { background: rgba(3, 9, 18, 0.45); border: 1px solid #334155; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; }
        .lista-borrado h3 { font-size: 14px; color: #38bdf8; margin-bottom: 10px; }
        .lista-borrado ul { list-style: none; }
        .lista-borrado li { display: flex; justify-content: space-between; font-size: 13px; color: #cbd5e1; padding: 4px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5); }
        .lista-borrado li:last-child { border-bottom: none; }
        .lista-borrado li span:last-child { color: #f87171; font-weight: 700; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #475569; background-color: rgba(15, 23, 42, 0.9); color: white; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #38bdf8; }
        .form-group input.confirmacion { border-color: #ef4444; }
        .acciones { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px; }
        .btn { border: none; border-radius: 8px; padding: 12px 20px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-peligro { background: #dc2626; color: white; }
        .btn-peligro:hover { background: #b91c1c; }
        .btn-neutro { background: #334155; color: #cbd5e1; }
        .btn-neutro:hover { background: #475569; }
        .volver { display: inline-block; margin-top: 20px; color: #38bdf8; text-decoration: none; font-size: 14px; font-weight: 600; }
        .volver:hover { text-decoration: underline; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; font-size: 14px; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TGS - Blue Tech Uruguay</a>
        <div class="acciones-cabecera">
            <a href="reservas.php" class="nav-enlace"><?php echo $lang_code === 'en' ? 'My appointments' : 'Mis turnos'; ?></a>
            <a href="presupuestos.php" class="nav-enlace"><?php echo $lang_code === 'en' ? 'My budgets' : 'Mis presupuestos'; ?></a>
            <a href="pagos_cliente.php" class="nav-enlace"><?php echo $lang_code === 'en' ? 'My payments' : 'Mis pagos'; ?></a>
            <a href="perfil.php" class="nav-enlace"><?php echo $lang_code === 'en' ? 'My profile' : 'Mi perfil'; ?></a>
            <!-- Selector de idioma de la baja de cuenta -->
            <div class="language-selector" aria-label="Language selector">
                <a href="eliminar_cuenta.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a>
                <a href="eliminar_cuenta.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
            <a href="logout.php" class="btn-salir"><?php echo $lang_code === 'en' ? 'Sign Out' : 'Cerrar Sesión'; ?></a>
        </div>
    </header>

    <div class="container">
        <div class="tarjeta">
            <h2><?php echo $texto['titulo']; ?></h2>
            <p><?php echo $texto['resumen']; ?></p>

            <div class="advertencia"><?php echo $texto['advertencia']; ?></div>

            <?php if ($mensaje !== ''): ?>
                <div class="alert"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <div class="lista-borrado">
                <h3><?php echo $texto['que_se_borra']; ?></h3>
                <ul>
                    <li><span><?php echo $texto['vehiculo']; ?></span><span><?php echo $resumen_datos['vehiculos']; ?></span></li>
                    <li><span><?php echo $texto['reserva']; ?></span><span><?php echo $resumen_datos['reservas']; ?></span></li>
                    <li><span><?php echo $texto['pago']; ?></span><span><?php echo $resumen_datos['pagos']; ?></span></li>
                    <li><span><?php echo $texto['presupuesto']; ?></span><span><?php echo $resumen_datos['presupuestos']; ?></span></li>
                    <li><span><?php echo $texto['aviso']; ?></span><span><?php echo $resumen_datos['avisos']; ?></span></li>
                </ul>
            </div>

            <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Delete your account and all its data?' : '¿Eliminar tu cuenta y todos tus datos?'; ?>');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="accion" value="eliminar_cuenta">

                <div class="form-group">
                    <label for="password"><?php echo $texto['contrasena']; ?></label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label for="confirmacion"><?php echo $texto['confirmacion']; ?></label>
                    <input type="text" id="confirmacion" name="confirmacion" class="confirmacion" required autocomplete="off">
                </div>

                <div class="acciones">
                    <button type="submit" class="btn btn-peligro"><?php echo $texto['eliminar']; ?></button>
                    <a href="perfil.php" class="btn btn-neutro"><?php echo $texto['cancelar']; ?></a>
                </div>
            </form>

            <a href="perfil.php" class="volver">&larr; <?php echo $texto['volver']; ?></a>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
