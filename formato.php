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
            'prefijo' => '+52',
            'telefono' => array('min' => 10, 'max' => 10),
            'documento' => array('tipo' => 'digitos', 'min' => 10, 'max' => 13),
        ),
        'PE' => array(
            'nombre' => 'Perú',
            'prefijo' => '+51',
            'telefono' => array('min' => 9, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 8, 'max' => 8),
        ),
        'BR' => array(
            'nombre' => 'Brasil',
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
            'prefijo' => '+34',
            'telefono' => array('min' => 9, 'max' => 9),
            'documento' => array('tipo' => 'digitos', 'min' => 8, 'max' => 9),
        ),
        'US' => array(
            'nombre' => 'Estados Unidos',
            'prefijo' => '+1',
            'telefono' => array('min' => 10, 'max' => 10),
            'documento' => array('tipo' => 'digitos', 'min' => 4, 'max' => 16),
        ),
    );
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
 * En español se usa el formato habitual: dd/mm/aaaa.
 * En ingles se usa el nombre del mes: "Sep 15, 2026", que no se
 * confunde con el formato americano mm/dd/aaaa.
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
        $meses = tgs_meses_ingles();
        return $meses[(int) date('n', $timestamp) - 1] . ' ' . date('j', $timestamp) . ', ' . date('Y', $timestamp);
    }

    return date('d/m/Y', $timestamp);
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
