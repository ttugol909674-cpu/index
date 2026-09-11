<?php
# Inicia la sesión para saber si el usuario ya está autenticado
session_start();
# Define el idioma y los textos de la pantalla general
$lang_code = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : ($_SESSION['lang'] ?? 'es');
$_SESSION['lang'] = $lang_code;
$texto = $lang_code === 'en' ? ['titulo' => 'Control Panel', 'bienvenida' => 'Welcome,', 'rol' => 'Role:', 'mensaje' => 'You have successfully signed in to the TGS system at Blue Tech Uruguay.', 'es' => 'Spanish', 'en' => 'English'] : ['titulo' => 'Panel de Control', 'bienvenida' => '¡Bienvenido,', 'rol' => 'Rol:', 'mensaje' => 'Has iniciado sesión correctamente en el sistema TGS de Blue Tech Uruguay.', 'es' => 'Español', 'en' => 'Inglés'];

# Si no existe el identificador del usuario, se fuerza el regreso a login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
</head>
<body>
    <p><a href="dashboard.php?lang=es">ES</a> | <a href="dashboard.php?lang=en">EN</a></p>
    <h1><?php echo $texto['bienvenida']; ?> <?php echo htmlspecialchars(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? '')); ?>!</h1>
    <p><?php echo $texto['rol']; ?> <?php echo htmlspecialchars($_SESSION['rol']); ?></p>
    
    <p><?php echo $texto['mensaje']; ?></p>
</body>
</html>