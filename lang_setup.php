<?php
/**
 * TGS - Taller Global de Servicios
 * Variante reducida de lang_config.php.
 *
 * Hace lo mismo (elige 'es' o 'en' segun la sesion o el parametro GET y
 * carga los textos en $txt) pero sin el control de archivo inexistente.
 * Se usa en las paginas que ya saben que lang/es.php y lang/en.php
 * existen. Al igual que en lang_config, el idioma se restringe a 'es'|'en'
 * y nunca se usa sin validar para construir la ruta.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['lang'])) {
    $lang = $_GET['lang'] === 'en' ? 'en' : 'es';
    $_SESSION['lang'] = $lang;
}

$current_lang = $_SESSION['lang'] ?? 'es';

// Carga el archivo de la carpeta lang/ (es.php o en.php)
$txt = require __DIR__ . "/lang/{$current_lang}.php";
?>
