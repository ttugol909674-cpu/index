<?php
/**
 * TGS - Taller Global de Servicios
 * Pie compartido de las pantallas internas.
 *
 * Mismo texto y mismo estilo en todas las paginas. El anio y el
 * nombre de la empresa se adaptan al idioma activo.
 *
 * Variables que usa de la pagina: $lang_code, $texto.
 */

$tgs_idioma = isset($lang_code) ? $lang_code : (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'es');
if ($tgs_idioma !== 'en') {
    $tgs_idioma = 'es';
}

$tgs_marca = $tgs_idioma === 'en' ? 'Global Workshop Services' : 'Taller Global de Servicios';
$tgs_derechos = $tgs_idioma === 'en' ? 'All rights reserved.' : 'Todos los derechos reservados.';

// Si la pagina trae sus propios textos de marca, se respetan.
if (!empty($texto) && is_array($texto)) {
    if (!empty($texto['marca'])) {
        $tgs_marca = $texto['marca'];
    }
    if (!empty($texto['derechos'])) {
        $tgs_derechos = $texto['derechos'];
    }
}
?>
<footer>
    <p>&copy; <?php echo date('Y'); ?> TGS - <?php echo $tgs_marca; ?>. <?php echo $tgs_derechos; ?></p>
</footer>
