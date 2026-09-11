<?php
# Si la sesión aún no existe, la iniciamos para poder guardar datos del usuario
if (session_status() === PHP_SESSION_NONE) {
    # Refuerza la cookie de sesión para reducir exposición desde el navegador
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
}

# Normaliza los nombres de rol de la base de datos a los identificadores internos
function normalizarRol($rol)
{
    $rol = strtolower(trim((string) $rol));
    $equivalencias = [
        'administrador' => 'admin',
        'administrator' => 'admin',
        'admin' => 'admin',
        'gestor de taller' => 'gestor',
        'manager' => 'gestor',
        'gestor' => 'gestor',
        'cliente' => 'cliente',
        'customer' => 'cliente'
    ];

    return $equivalencias[$rol] ?? $rol;
}

# Función para proteger páginas según el rol del usuario
# Ejemplo: verificarRol('admin') o verificarRol(['admin','gestor'])
function verificarRol($rolesPermitidos) {
    # Esta función es el punto central de autorización de las páginas privadas
    # Validar si el usuario ha iniciado sesión
    # Se acepta si existe cedula o id_usuario en la sesión
    if (!isset($_SESSION['cedula']) && !isset($_SESSION['id_usuario'])) {
        # Si no inició sesión, lo manda al login
        header("Location: login.php?error=acceso_denegado");
        exit();
    }

    # Si el parámetro recibido no es un arreglo, lo convierto en uno
    # para que funcione igual tanto con un valor como con varios
    if (!is_array($rolesPermitidos)) {
        $rolesPermitidos = [$rolesPermitidos];
    }

    # Obtiene el rol actual del usuario desde la sesión y lo convierte a minúsculas
    $rolActual = normalizarRol($_SESSION['rol'] ?? '');

    # Convierte todos los roles permitidos también a minúsculas
    # para evitar errores por mayúsculas/minúsculas
    $rolesPermitidos = array_map('strtolower', $rolesPermitidos);

    # Si el rol del usuario no está dentro de los permitidos, lo bloquea
    if (!in_array($rolActual, $rolesPermitidos)) {
        # Redirige a una página de acceso prohibido
        header("Location: error_403.php");
        exit();
    }
}
?>