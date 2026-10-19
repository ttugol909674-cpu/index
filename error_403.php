<?php
/**
 * TGS - Taller Global de Servicios
 * Pagina 403: acceso denegado.
 *
 * Destino obligado de verificarRol() cuando el usuario tiene sesion pero
 * su rol no esta entre los permitidos para esa pagina. Envia el codigo
 * HTTP real 403 para que navegadores y proxies lo interpreten como una
 * denegacion (y no como un error de aplicacion).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
http_response_code(403);
// Un valor explícito (?lang=es o ?lang=en) manda sobre el de la sesión.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang_code = $_SESSION['lang'] ?? 'es';
$texto = $lang_code === 'en'
    ? ['titulo' => 'Access denied', 'mensaje' => 'You do not have permission to view this page.', 'volver' => 'Back to home']
    : ['titulo' => 'Acceso denegado', 'mensaje' => 'No tienes permisos para ver esta pagina.', 'volver' => 'Volver al inicio'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texto['titulo']; ?> - TGS</title>
    <link rel="stylesheet" href="css/tema-tgs.css">
</head>
<body>
    <main style="max-width: 560px; margin: 12vh auto; padding: 32px; text-align: center;">
        <p><a href="error_403.php?lang=es">ES</a> | <a href="error_403.php?lang=en">EN</a></p>
        <h1><?php echo $texto['titulo']; ?></h1>
        <p><?php echo $texto['mensaje']; ?></p>
        <p><a href="index.php"><?php echo $texto['volver']; ?></a></p>
    </main>
</body>
</html>
