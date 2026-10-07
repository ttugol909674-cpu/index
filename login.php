<?php
/**
 * TGS - Taller Global de Servicios
 * Formulario y validacion de inicio de sesion.
 *
 * Muestra el formulario y, ademas, procesa su propio POST (por eso el
 * action del form es "login.php"): busca el usuario por email, verifica
 * la contrasena con password_verify() y redirige segun el rol. El archivo
 * validar_login.php hace lo mismo y quedo como duplicado legado.
 *
 * Seguridad: el error es generico (no dice si fallo el email o la clave),
 * la cookie es httponly+SameSite, y session_regenerate_id() evita la
 * fijacion de sesion tras autenticarse. Los mensajes se imprimen con
 * htmlspecialchars().
 */
# Configura una cookie de sesión protegida antes de iniciar la sesión
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
session_start();
# Lee el idioma seleccionado y lo conserva durante la sesión
$lang_code = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : ($_SESSION['lang'] ?? 'es');
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $lang_code;
}
$texto = $lang_code === 'en' ? [
    'titulo' => 'Sign In', 'correo' => 'Email address', 'clave' => 'Password', 'entrar' => 'Sign In',
    'sin_cuenta' => "Don't have an account?", 'registrate' => 'Create one here', 'error_credenciales' => 'Incorrect email or password.',
    'completa' => 'Please complete all fields.', 'error_db' => 'Database error: ', 'marca' => 'Global Workshop Services',
    'derechos' => 'All rights reserved.'
] : [
    'titulo' => 'Iniciar Sesión', 'correo' => 'Correo Electrónico', 'clave' => 'Contraseña', 'entrar' => 'Ingresar',
    'sin_cuenta' => '¿No tienes una cuenta?', 'registrate' => 'Regístrate aquí', 'error_credenciales' => 'Correo o contraseña incorrectos.',
    'completa' => 'Por favor completa todos los campos.', 'error_db' => 'Error en la base de datos: ', 'marca' => 'Taller Global de Servicios',
    'derechos' => 'Todos los derechos reservados.'
];

# Carga la conexión para validar las credenciales del usuario

# Incluye la conexión a la base de datos
require_once 'conexion.php';
require_once 'auth.php';

# Variable para guardar mensajes de error del login
$error = '';

# Si el formulario fue enviado con el método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    # Limpia los valores recibidos del formulario
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    # Verifica que ambos campos sean válidos antes de consultar la base de datos
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password)) {
        try {
            # Busca el usuario por su correo electrónico
            $stmt = $conexion->prepare("SELECT * FROM usuario WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            # Si existe un usuario con ese correo
            if ($usuario) {
                # Convierte el rol a minúsculas para comparar sin problemas
                $rol = normalizarRol($usuario['rol'] ?? 'cliente');

                # Solo acepta contraseñas almacenadas mediante password_hash
                if (isset($usuario['password']) && password_verify($password, $usuario['password'])) {
                    # Cambia el ID para evitar fijación de sesión después del login
                    session_regenerate_id(true);

                    # Guarda la información del usuario en la sesión
                    $_SESSION['cedula'] = $usuario['cedula'] ?? '0000000-0';
                    $_SESSION['id_usuario'] = $usuario['id_usuario'] ?? null;
                    $_SESSION['nombre'] = $usuario['nombre'] ?? 'Usuario';
                    $_SESSION['email'] = $usuario['email'];
                    $_SESSION['telefono'] = $usuario['telefono'] ?? '';
                    $_SESSION['rol'] = $rol;

                    # Redirige a la vista según el rol del usuario
                    if ($rol === 'admin') {
                        header("Location: dashboard_admin.php");
                        exit();
                    } elseif ($rol === 'gestor') {
                        header("Location: dashboard_gestor.php");
                        exit();
                    } else {
                        header("Location: reservas.php");
                        exit();
                    }
                } else {
                    # Si las credenciales no coinciden, muestra error
                    $error = $texto['error_credenciales'];
                }
            } else {
                # Si no existe ese correo, muestra error
                $error = $texto['error_credenciales'];
            }
        } catch (PDOException $e) {
            # No muestra detalles internos de la base de datos al usuario
            $error = $texto['error_db'] . 'Please try again later.';
            if ($lang_code !== 'en') {
                $error = 'Error temporal del sistema. Intenta nuevamente más tarde.';
            }
        }
    } else {
        # Si faltan datos en el formulario
            $error = $texto['completa'];
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
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .login-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; width: 100%; }
        .login-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); }
        .login-card h2 { font-size: 24px; color: #38bdf8; margin-bottom: 25px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; color: #94a3b8; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #475569; background-color: rgba(15, 23, 42, 0.9); color: white; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #38bdf8; }
        .btn-submit { width: 100%; background-color: #2563eb; color: white; padding: 12px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        .btn-submit:hover { background-color: #1d4ed8; }
        .alert-error { background-color: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .register-link { text-align: center; margin-top: 20px; font-size: 14px; color: #94a3b8; }
        .register-link a { color: #38bdf8; text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); width: 100%; }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>
    <header>
        <a href="index.php" class="logo">TGS - Blue Tech Uruguay</a>
        <!-- Selector de idioma de la pantalla de acceso -->
        <div class="language-selector" aria-label="Language selector">
            <a href="login.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a>
            <a href="login.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a>
        </div>
    </header>

    <div class="login-container">
        <div class="login-card">
            <h2><?php echo $texto['titulo']; ?></h2>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email"><?php echo $texto['correo']; ?></label>
                    <input type="email" id="email" name="email" required placeholder="tucorreo@email.com" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="password"><?php echo $texto['clave']; ?></label>
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn-submit"><?php echo $texto['entrar']; ?></button>
            </form>

            <div class="register-link">
                <?php echo $texto['sin_cuenta']; ?> <a href="registro.php?lang=<?php echo $lang_code; ?>"><?php echo $texto['registrate']; ?></a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $texto['marca']; ?>. <?php echo $texto['derechos']; ?></p>
    </footer>
</body>
</html>
