<?php
/**
 * TGS - Taller Global de Servicios
 * Selector de idioma compartido.
 *
 * Carga los textos del idioma activo ('es' por defecto) en la variable
 * $txt, leyendo lang/es.php o lang/en.php. Cualquier pagina que quiera
 * textos traducidos incluye este archivo y usa $txt['clave'].
 *
 * El valor por GET se acepta solo si es exactamente 'es' o 'en': nunca se
 * usa el parametro tal cual para construir la ruta del archivo, lo que
 * impide un path traversal via ?lang=../../etc/passwd.
 */
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
    // Si el archivo del idioma pedido no existe, caer en español.
    // Es un caso casi imposible dado el filtro de arriba, pero evita
    // dejar la pagina sin textos si alguien elige un idioma nuevo.
    $txt = require __DIR__ . "/lang/es.php";
}
?>
