<?php
###### # Selecciona y carga los textos del idioma activo de la sesiÃ³n.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cambiar idioma si viene por GET (?lang=en o ?lang=es)
if (isset($_GET['lang'])) {
    $lang = strtolower($_GET['lang']);
    if (in_array($lang, ['es', 'en'])) {
        $_SESSION['lang'] = $lang;
    }
}

// Idioma por defecto es 'es'
$current_lang = $_SESSION['lang'] ?? 'es';

// Cargar archivo según el idioma
$file = __DIR__ . "/lang/{$current_lang}.php";

if (file_exists($file)) {
    $txt = require $file;
} else {
    $txt = require __DIR__ . "/lang/es.php";
}
?>
