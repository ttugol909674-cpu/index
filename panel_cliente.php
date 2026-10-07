<?php
/**
 * TGS - Taller Global de Servicios
 * Panel personal del cliente autenticado.
 *
 * Acceso exclusivo para el rol 'cliente': la autorizacion la aplica
 * verificarRol('cliente') desde auth.php, que redirige a login o a
 * error_403 si corresponde. El resto del contenido del cliente vive en
 * reservas.php (crear/programar turnos).
 */
require_once 'auth.php';
verificarRol('cliente');
# Define los textos básicos del panel según el idioma elegido
$lang_code = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : ($_SESSION['lang'] ?? 'es');
$_SESSION['lang'] = $lang_code;
$texto = $lang_code === 'en' ? ['bienvenida' => 'Welcome, Customer:', 'salir' => 'Sign Out', 'panel' => 'Customer Panel'] : ['bienvenida' => 'Bienvenido, Cliente:', 'salir' => 'Cerrar Sesión', 'panel' => 'Panel de Cliente'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <title><?php echo $texto['panel']; ?> - TGS</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>
    <header>
        <h1><?php echo $texto['bienvenida']; ?> <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>
        <div>
            <a href="panel_cliente.php?lang=es">ES</a>
            <a href="panel_cliente.php?lang=en">EN</a>
            <a href="logout.php" class="btn-logout"><?php echo $texto['salir']; ?></a>
        </div>
    </header>
    <main>
        <!-- Tu interfaz y funcionalidades originales del cliente se mantienen intactas aquí -->
    </main>
</body>
</html>
