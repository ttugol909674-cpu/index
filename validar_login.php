<?php
/**
 * TGS - Taller Global de Servicios
 * Valida las credenciales del login y abre la sesion.
 *
 * Recibe el POST del formulario de login.php, busca el usuario por email,
 * verifica la contrasena con password_verify() contra el hash guardado y
 * crea la sesion. El redireccionamiento final depende del rol:
 *   - admin  -> dashboard_admin.php
 *   - gestor -> dashboard_gestor.php
 *   - cliente-> reservas.php (gestion de sus turnos)
 *
 * Seguridad:
 *  - No se compara la contrasena en SQL ni en texto plano: siempre se usa
 *    password_verify() contra el hash bcrypt de la base.
 *  - El mensaje de error es generico a proposito: no revela si el email
 *    existia o fue la contrasena lo incorrecto (anti-enumeracion).
 *  - session_regenerate_id(true) evita la fijacion de sesion: el ID que
 *    el atacante pudo conocer antes del login deja de ser valido.
 */
session_start();
require_once 'auth.php';
require_once __DIR__ . '/config.php';

# Crea la conexión PDO con los datos de .env / .env.local
try {
    $pdo = tgs_pdo();
} catch (PDOException $e) {
    die("No se pudo conectar con el sistema.");
}

# Si el formulario de login fue enviado, valida email y contraseña
# El token CSRF evita que otra pagina envie el login en nombre del visitante.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validarCsrf($_POST['csrf_token'] ?? '')) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($pass)) {
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && isset($usuario['password']) && password_verify($pass, $usuario['password'])) {
            # Cambia el ID de sesión al autenticarse para evitar fijación de sesión
            session_regenerate_id(true);
            $_SESSION['cedula'] = $usuario['cedula'];
            $_SESSION['nombre'] = $usuario['nombre'] ?? 'Usuario';
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['telefono'] = $usuario['telefono'] ?? '';
            // Guardamos el rol (Cliente, Administrador, Gestor de Taller)
            $_SESSION['rol'] = normalizarRol($usuario['rol'] ?? 'cliente');

            # Redirige al panel correspondiente según el rol normalizado
            if ($_SESSION['rol'] === 'admin') {
                header('Location: dashboard_admin.php');
            } elseif ($_SESSION['rol'] === 'gestor') {
                header('Location: dashboard_gestor.php');
            } else {
                header('Location: reservas.php');
            }
            exit;
        } else {
            echo "<script>alert('Correo o contraseña incorrecta'); window.location.href='login.php';</script>";
            exit;
        }
    } else {
        header('Location: login.php');
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}
