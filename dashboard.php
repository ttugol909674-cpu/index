<?php
/**
 * TGS - Taller Global de Servicios
 * Pantalla de bienvenida tras el login.
 *
 * Es la pagina comun a todos los roles: muestra el nombre del usuario
 * logueado y su rol. No reemplaza a los paneles especificos
 * (dashboard_gestor.php / dashboard_admin.php / panel_cliente.php),
 * simplemente confirma que la sesion quedo abierta.
 *
 * Se escapan nombre, apellido y rol con htmlspecialchars() al imprimirlos:
 * vienen de la base y no se puede confiar en su contenido.
 */
session_start();
# Define el idioma y los textos de la pantalla general.
# Un valor explícito (?lang=es o ?lang=en) manda sobre el de la sesión.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang_code = $_SESSION['lang'] ?? 'es';
$texto = $lang_code === 'en' ? ['titulo' => 'Control Panel', 'bienvenida' => 'Welcome,', 'rol' => 'Role:', 'mensaje' => 'You have successfully signed in to the TGS system at Blue Tech Uruguay.', 'es' => 'Spanish', 'en' => 'English'] : ['titulo' => 'Panel de Control', 'bienvenida' => '¡Bienvenido,', 'rol' => 'Rol:', 'mensaje' => 'Has iniciado sesión correctamente en el sistema TGS de Blue Tech Uruguay.', 'es' => 'Español', 'en' => 'Inglés'];

# Si no existe el identificador del usuario, se fuerza el regreso a login
if (!isset($_SESSION['cedula']) && !isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
</head>
<body>
    <p><a href="dashboard.php?lang=es">ES</a> | <a href="dashboard.php?lang=en">EN</a></p>
    <h1><?php echo $texto['bienvenida']; ?> <?php echo htmlspecialchars(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? '')); ?>!</h1>
    <p><?php echo $texto['rol']; ?> <?php echo htmlspecialchars($_SESSION['rol']); ?></p>
    
    <p><?php echo $texto['mensaje']; ?></p>
</body>
</html>
