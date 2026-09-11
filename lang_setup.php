<?php
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