<?php
/**
 * TGS - Taller Global de Servicios
 * Formato y validacion de datos segun el pais y el idioma.
 *
 * Este archivo agrupa las funciones que se usan en varias paginas:
 *   - el catalogo de paises con las reglas de telefono y documento
 *   - la validacion del telefono y del documento segun el pais
 *   - el formato de las fechas segun el idioma activo
 *
 * No guarda estado ni consulta la base de datos: son funciones puras.
 */

/**
 * Catalogo de paises con las reglas de contacto.
 *
 * 'telefono'  - prefijo internacional y cantidad de digitos del numero
 *               nacional (un celular y un fijo tienen largo distinto).
 * 'documento' - reglas de formato del documento de identidad:
 *               'digitos' = solo numeros, con esta cantidad.
 *
 * A proposito NO se valida el digito verificador de la cedula: el
 * algoritmo de cada pais es distinto y un error ahi rechazaria
 * documentos validos. Lo que se controla es el formato y el largo, que
 * es lo que evita el error tipografico mas comun.
 */
function tgs_paises(): array
{
    return array(
        'UY' => array(
            'nombre' => 'Uruguay',
            'prefijo' => '+598',
            'telefono' => array('min' => 8, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 8, 'max' => 8),
        ),
        'EC' => array(
            'nombre' => 'Ecuador',
            'prefijo' => '+593',
            'telefono' => array('min' => 9, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 10, 'max' => 10),
        ),
        'AR' => array(
            'nombre' => 'Argentina',
            'prefijo' => '+54',
            'telefono' => array('min' => 10, 'max' => 11),
            'documento' => array('tipo' => 'digitos', 'min' => 7, 'max' => 8),
        ),
        'CL' => array(
            'nombre' => 'Chile',
            'prefijo' => '+56',
            'telefono' => array('min' => 9, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 8, 'max' => 9),
        ),
        'CO' => array(
            'nombre' => 'Colombia',
            'prefijo' => '+57',
            'telefono' => array('min' => 10, 'max' => 10),
            'documento' => array('tipo' => 'digitos', 'min' => 6, 'max' => 10),
        ),
        'MX' => array(
            'nombre' => 'México',
            'nombre_en' => 'Mexico',
            'prefijo' => '+52',
            'telefono' => array('min' => 10, 'max' => 10),
            'documento' => array('tipo' => 'digitos', 'min' => 10, 'max' => 13),
        ),
        'PE' => array(
            'nombre' => 'Perú',
            'nombre_en' => 'Peru',
            'prefijo' => '+51',
            'telefono' => array('min' => 9, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 8, 'max' => 8),
        ),
        'BR' => array(
            'nombre' => 'Brasil',
            'nombre_en' => 'Brazil',
            'prefijo' => '+55',
            'telefono' => array('min' => 10, 'max' => 11),
            'documento' => array('tipo' => 'digitos', 'min' => 11, 'max' => 11),
        ),
        'PY' => array(
            'nombre' => 'Paraguay',
            'prefijo' => '+595',
            'telefono' => array('min' => 9, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 8, 'max' => 8),
        ),
        'ES' => array(
            'nombre' => 'España',
            'nombre_en' => 'Spain',
            'prefijo' => '+34',
            'telefono' => array('min' => 9, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 8, 'max' => 9),
        ),
        'US' => array(
            'nombre' => 'Estados Unidos',
            'nombre_en' => 'United States',
            'prefijo' => '+1',
            'telefono' => array('min' => 10, 'max' => 10),
            'documento' => array('tipo' => 'digitos', 'min' => 4, 'max' => 16),
        ),
    );
}

/**
 * Nombre del pais en el idioma pedido.
 */
function tgs_nombre_pais(string $codigo, string $idioma = 'es'): string
{
    $paises = tgs_paises();
    $codigo = strtoupper(trim($codigo));
    if (!isset($paises[$codigo])) {
        $codigo = 'UY';
    }

    if ($idioma === 'en' && !empty($paises[$codigo]['nombre_en'])) {
        return $paises[$codigo]['nombre_en'];
    }

    return $paises[$codigo]['nombre'];
}

/**
 * Devuelve las reglas de un pais, o las de Uruguay si no se reconoce.
 */
function tgs_pais(string $codigo): array
{
    $paises = tgs_paises();
    $codigo = strtoupper(trim($codigo));

    if (!isset($paises[$codigo])) {
        $codigo = 'UY';
    }

    return array_merge(array('codigo' => $codigo), $paises[$codigo]);
}

/**
 * Deja un numero de telefono solo con sus digitos.
 */
function tgs_solo_digitos(string $texto): string
{
    return preg_replace('/\D+/', '', $texto);
}

/**
 * Valida un numero de telefono segun las reglas del pais.
 *
 * Se acepta con o sin el prefijo internacional: si viene con prefijo,
 * se descuenta antes de comparar la cantidad de digitos.
 */
function tgs_telefono_valido(string $numero, string $pais): bool
{
    $reglas = tgs_pais($pais);
    $digitos = tgs_solo_digitos($numero);

    if ($digitos === '') {
        return false;
    }

    $prefijo = tgs_solo_digitos($reglas['prefijo']);
    if ($prefijo !== '' && strpos($digitos, $prefijo) === 0) {
        $digitos = substr($digitos, strlen($prefijo));
    }

    $largo = strlen($digitos);
    if ($largo < $reglas['telefono']['min'] || $largo > $reglas['telefono']['max']) {
        return false;
    }

    return true;
}

/**
 * Valida el documento de identidad segun las reglas del pais.
 */
function tgs_documento_valido(string $documento, string $pais): bool
{
    $reglas = tgs_pais($pais);
    $digitos = tgs_solo_digitos($documento);

    if ($digitos === '') {
        return false;
    }

    $largo = strlen($digitos);
    if ($largo < $reglas['documento']['min'] || $largo > $reglas['documento']['max']) {
        return false;
    }

    return true;
}

/**
 * Explica que se espera en el telefono, segun el pais y el idioma.
 * Se muestra debajo del campo para que el usuario sepa como escribirlo.
 */
function tgs_ayuda_telefono(string $codigo, string $idioma = 'es'): string
{
    $reglas = tgs_pais($codigo);
    $min = $reglas['telefono']['min'];
    $max = $reglas['telefono']['max'];

    if ($idioma === 'en') {
        $largo = ($min === $max) ? "$min digits" : "between $min and $max digits";
        return $reglas['nombre'] . ': ' . $largo . '. Example: 099 123 456. You can also write ' . $reglas['prefijo'] . ' 99 123 456.';
    }

    $largo = ($min === $max) ? "$min dígitos" : "entre $min y $max dígitos";
    return $reglas['nombre'] . ': ' . $largo . '. Ejemplo: 099 123 456. También podés escribir ' . $reglas['prefijo'] . ' 99 123 456.';
}

/**
 * Explica que se espera en el documento de identidad.
 */
function tgs_ayuda_documento(string $codigo, string $idioma = 'es'): string
{
    $reglas = tgs_pais($codigo);
    $min = $reglas['documento']['min'];
    $max = $reglas['documento']['max'];

    if ($idioma === 'en') {
        $largo = ($min === $max) ? "$min digits" : "between $min and $max digits";
        return $reglas['nombre'] . ': ID number of ' . $largo . '. Example: 12345678.';
    }

    $largo = ($min === $max) ? "$min dígitos" : "entre $min y $max dígitos";
    return $reglas['nombre'] . ': documento de identidad de ' . $largo . '. Ejemplo: 12345678.';
}

/**
 * Textos de ayuda de todos los paises, listos para enviarse al navegador.
 * Se usa para actualizar la ayuda en el momento de cambiar el pais, sin
 * recargar la pagina.
 */
function tgs_ayuda_paises(string $idioma = 'es'): array
{
    $ayuda = array();

    foreach (tgs_paises() as $codigo => $datos) {
        $ayuda[$codigo] = array(
            'documento' => tgs_ayuda_documento($codigo, $idioma),
            'telefono'  => tgs_ayuda_telefono($codigo, $idioma),
        );
    }

    return $ayuda;
}

/**
 * Nombre del mes en ingles, para las fechas.
 */
function tgs_meses_ingles(): array
{
    return array(
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    );
}

/**
 * Formatea una fecha segun el idioma.
 *
 * En español se usa dd/mm/aaaa y en inglés mm/dd/yyyy (formato
 * estadounidense), que es el que muestra el calendario nativo en ingles.
 * Asi el formato del sitio y el del formulario son siempre el mismo.
 *
 * Si la fecha no se puede interpretar, se devuelve tal cual.
 */
function tgs_fecha_legible(string $fecha, string $idioma = 'es'): string
{
    $fecha = trim($fecha);
    if ($fecha === '' || $fecha === '-') {
        return $fecha;
    }

    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return $fecha;
    }

    if ($idioma === 'en') {
        return date('m/d/Y', $timestamp);
    }

    return date('d/m/Y', $timestamp);
}

/**
 * Formato de fecha que se escribe en los campos: dd/mm/aaaa o mm/dd/yyyy.
 */
function tgs_formato_fecha(string $idioma = 'es'): string
{
    return $idioma === 'en' ? 'mm/dd/yyyy' : 'dd/mm/aaaa';
}

/**
 * Dibuja un campo de fecha con calendario propio.
 *
 * El input nativo del navegador (type="date") no cambia de formato con
 * el idioma de la pagina: Chrome y Edge usan el idioma del navegador, no
 * el de la pagina, asi que una pagina en ingles seguia mostrando
 * dd/mm/aaaa. Este campo si lo hace: en ingles muestra mm/dd/yyyy.
 *
 * El valor que se envia al servidor sigue siendo el ISO (aaaa-mm-dd),
 * que es como esta guardado en la base.
 *
 * Opciones admitidas:
 *   'required' => el campo es obligatorio
 *   'id'       => id del input oculto (por defecto, el nombre)
 *   'aria'    => texto accesible del campo
 *   'attrs'   => atributos extra del input oculto (por ejemplo, un onchange)
 */
function tgs_campo_fecha(string $nombre, string $valor, string $idioma = 'es', array $opciones = array()): string
{
    $en = ($idioma === 'en');
    $formato = tgs_formato_fecha($idioma);
    $texto = tgs_fecha_legible($valor, $idioma);
    $id = !empty($opciones['id']) ? $opciones['id'] : $nombre;
    $requerido = !empty($opciones['required']) ? ' required' : '';
    $aria = !empty($opciones['aria']) ? ' aria-label="' . htmlspecialchars($opciones['aria']) : '';
    $extra = !empty($opciones['attrs']) ? ' ' . $opciones['attrs'] : '';
    $etiquetaBoton = $en ? 'Open the calendar' : 'Abrir el calendario';

    return '<span class="tgs-fecha" data-tgs-fecha data-formato="' . ($en ? 'en' : 'es') . '">'
        . '<input type="text" class="tgs-fecha-texto" value="' . htmlspecialchars($texto) . '"'
        . ' placeholder="' . $formato . '" inputmode="numeric" autocomplete="off" spellcheck="false"'
        . $aria . '>'
        . '<input type="hidden" name="' . htmlspecialchars($nombre) . '" id="' . htmlspecialchars($id) . '"'
        . ' value="' . htmlspecialchars($valor) . '"' . $requerido . $extra . '>'
        . '<button type="button" class="tgs-fecha-boton" tabindex="-1" aria-label="' . $etiquetaBoton . '">'
        . '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">'
        . '<rect x="3" y="5" width="18" height="16" rx="2"></rect>'
        . '<path d="M3 10h18M8 3v4M16 3v4"></path>'
        . '</svg></button>'
        . '<span class="tgs-calendario" hidden></span>'
        . '</span>';
}

/**
 * Formatea fecha y hora segun el idioma.
 */
function tgs_fecha_hora_legible(string $fecha, string $hora, string $idioma = 'es'): string
{
    $legible = tgs_fecha_legible($fecha, $idioma);
    $hora = trim($hora);

    if ($hora === '' || $hora === '-') {
        return $legible;
    }

    // La hora viene como "10:00:00" o "10:00".
    if (preg_match('/^\d{2}:\d{2}/', $hora, $coincidencia)) {
        $hora = $coincidencia[0];
    } else {
        return $legible;
    }

    if ($legible === '-' || $legible === '') {
        return $hora;
    }

    return $legible . ' ' . $hora;
}
