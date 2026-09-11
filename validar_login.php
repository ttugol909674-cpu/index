<?php
# Inicia sesión para guardar el usuario autenticado
session_start();
require_once 'auth.php';

# Configuración de la base de datos para validar el login
$host = 'localhost';
$dbname = 'bluetech_base';
$username = 'root';
$password = '';

# Crea la conexión PDO y activa errores para depuración
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("No se pudo conectar con el sistema.");
}

# Si el formulario de login fue enviado, valida email y contraseña
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