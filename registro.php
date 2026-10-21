<?php
###### # Muestra y procesa el registro de nuevas cuentas de usuario.
# Configura una cookie de sesión protegida antes de iniciar la sesión
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
session_start();
# Lee el idioma seleccionado y lo conserva durante la sesión.
# Un valor explicito (?lang=es o ?lang=en) manda sobre el de la sesión.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang_code = $_SESSION['lang'] ?? 'es';
$texto = $lang_code === 'en' ? [
    'titulo' => 'Create Account', 'cedula' => 'ID number', 'nombre' => 'Full name', 'correo' => 'Email address', 'telefono' => 'Mobile phone',
    'clave' => 'Password', 'registrar' => 'Create Account', 'ya_cuenta' => 'Already have an account?', 'entrar' => 'Sign in',
    'duplicado' => 'A user with that ID number or email already exists.', 'exito' => 'Registration successful! You can now sign in.',
    'completa' => 'Please complete all fields.', 'telefono_invalido' => 'Enter a valid mobile phone number.', 'error' => 'Registration error: ', 'marca' => 'Global Workshop Services', 'derechos' => 'All rights reserved.'
] : [
    'titulo' => 'Crear Cuenta', 'cedula' => 'Cédula', 'nombre' => 'Nombre Completo', 'correo' => 'Correo Electrónico', 'telefono' => 'Teléfono celular',
    'clave' => 'Contraseña', 'registrar' => 'Registrarse', 'ya_cuenta' => '¿Ya tienes una cuenta?', 'entrar' => 'Inicia sesión',
    'duplicado' => 'Ya existe un usuario registrado con esa cédula o correo electrónico.', 'exito' => '¡Registro exitoso! Ya puedes iniciar sesión.',
    'completa' => 'Por favor completa todos los campos.', 'telefono_invalido' => 'Ingresa un número de celular válido.', 'error' => 'Error al registrar: ', 'marca' => 'Taller Global de Servicios', 'derechos' => 'Todos los derechos reservados.'
];

# Carga la conexión para guardar la nueva cuenta

# Incluye la conexión a la base de datos
require_once 'conexion.php';

# Variables para mostrar mensajes de éxito o error al usuario
$error = '';
$exito = '';

# Si se envió el formulario de registro por POST
require_once 'auth.php';

# Si se envió el formulario de registro por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    # Token CSRF: impide que otra pagina cree cuentas en nombre del visitante.
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $error = $texto['error'] . ($idioma_actual == 'en'
            ? 'The session expired. Please reload the page and try again.'
            : 'La sesión expiró. Recargá la página e intentá nuevamente.');
    } else {
    # Recoge y limpia los valores enviados desde el formulario
    $cedula = trim($_POST['cedula'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';

    # Comprueba que todos los campos obligatorios estén completos
    if (!empty($cedula) && !empty($nombre) && !empty($email) && !empty($telefono) && !empty($password)) {
        if (!preg_match('/^\+?[0-9 ()-]{7,20}$/', $telefono)) {
            $error = $texto['telefono_invalido'];
        } else {
        try {
            # Verifica si ya existe un usuario con esa cédula o email
            $check = $conexion->prepare("SELECT * FROM usuario WHERE cedula = :cedula OR email = :email");
            $check->execute(['cedula' => $cedula, 'email' => $email]);

            # Si ya existe, se evita el registro duplicado
            if ($check->rowCount() > 0) {
                $error = $texto['duplicado'];
            } else {
                # Encripta la contraseña antes de guardarla
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                # Define el rol por defecto para un nuevo usuario registrado
                $rol_por_defecto = 'cliente';

                # Inserta el nuevo usuario en la tabla usuario
                $stmt = $conexion->prepare("INSERT INTO usuario (cedula, nombre, email, telefono, password, rol) VALUES (:cedula, :nombre, :email, :telefono, :password, :rol)");
                $stmt->execute([
                    'cedula' => $cedula,
                    'nombre' => $nombre,
                    'email' => $email,
                    'telefono' => $telefono,
                    'password' => $password_hash,
                    'rol' => $rol_por_defecto
                ]);

                # Mensaje de éxito para el usuario
                $exito = $texto['exito'];
            }
} catch (PDOException $e) {
            # Error de base de datos: se oculta el detalle tecnico y se muestra
            # un mensaje generico (el motivo mas comun es un duplicado real).
            # El getMessage() NO se expone al usuario por filtracion de datos.
            $error = $texto['error'] . 'Intenta nuevamente o usa otro correo.';
        }
        }
    } else {
        # Si falta algún campo, muestra aviso
        $error = $texto['completa'];
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
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .register-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .register-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); }
        .register-card h2 { font-size: 24px; color: #38bdf8; margin-bottom: 25px; text-align: center; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 14px; color: #94a3b8; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #475569; background-color: rgba(15, 23, 42, 0.9); color: white; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #38bdf8; }
        .btn-submit { width: 100%; background-color: #2563eb; color: white; padding: 12px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        .btn-submit:hover { background-color: #1d4ed8; }
        .alert-error { background-color: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .alert-success { background-color: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #4ade80; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .login-link { text-align: center; margin-top: 20px; font-size: 14px; color: #94a3b8; }
        .login-link a { color: #38bdf8; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TGS - Blue Tech Uruguay</a>
        <!-- Selector de idioma de la pantalla de registro -->
        <div class="language-selector" aria-label="Language selector">
            <a href="registro.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a>
            <a href="registro.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a>
        </div>
    </header>

    <div class="register-container">
        <div class="register-card">
            <h2><?php echo $texto['titulo']; ?></h2>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($exito)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($exito); ?></div>
            <?php endif; ?>

            <form action="registro.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <div class="form-group">
                    <label for="cedula"><?php echo $texto['cedula']; ?></label>
                    <input type="text" id="cedula" name="cedula" required placeholder="Ej: 12345678">
                </div>

                <div class="form-group">
                    <label for="nombre"><?php echo $texto['nombre']; ?></label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Tu nombre">
                </div>

                <div class="form-group">
                    <label for="email"><?php echo $texto['correo']; ?></label>
                    <input type="email" id="email" name="email" required placeholder="tucorreo@email.com">
                </div>

                <div class="form-group">
                    <label for="telefono"><?php echo $texto['telefono']; ?></label>
                    <input type="tel" id="telefono" name="telefono" required autocomplete="tel" inputmode="tel" pattern="\+?[0-9 ()-]{7,20}" placeholder="+598 99 123 456">
                </div>

                <div class="form-group">
                    <label for="password"><?php echo $texto['clave']; ?></label>
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn-submit"><?php echo $texto['registrar']; ?></button>
            </form>

            <div class="login-link">
                <?php echo $texto['ya_cuenta']; ?> <a href="login.php?lang=<?php echo $lang_code; ?>"><?php echo $texto['entrar']; ?></a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 TGS - <?php echo $texto['marca']; ?>. <?php echo $texto['derechos']; ?></p>
    </footer>
</body>
</html>
