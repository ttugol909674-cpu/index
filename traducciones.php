<?php
/**
 * TGS - Taller Global de Servicios
 * Traduccion de los datos que vienen de la base de datos.
 *
 * Los textos de la interfaz estan en lang/es.php y lang/en.php, pero
 * hay otros textos que viven en la base: los nombres y las descripciones
 * de los servicios, los nombres de las sedes, los datos bancarios y los
 * nombres de los paises. Esos datos se guardan una sola vez (en el
 * idioma del que los creo el administrador) y esta funcion devuelve la
 * version en ingles cuando la pagina esta en ingles.
 *
 * Si un valor no esta en el diccionario, se devuelve tal cual: un
 * servicio nuevo agregado por el administrador se ve bien en los dos
 * idiomas, sin haberlotranslated.
 */

$tgs_traducciones_en = array(
    // --- Servicios (tabla servicio) ---
    'Mantenimiento preventivo' => 'Preventive maintenance',
    'Aceite, filtros, fluidos y revision general' => 'Oil, filters, fluids and general check-up',
    'Diagnostico computarizado' => 'Computer diagnostics',
    'Revision electronica y deteccion de fallas' => 'Electronic checks and fault detection',
    'Mecanica general' => 'General mechanics',
    'Frenos, suspension, transmision y reparaciones' => 'Brakes, suspension, transmission and repairs',

    // --- Sedes (tabla taller) ---
    'TGS Centro' => 'TGS Center',
    'Peatonal Saravia 505' => 'Saravia 505 Pedestrian Street',

    // --- Datos bancarios (tabla datos_pago) ---
    'TGS - Cuenta de demostracion' => 'TGS - Demonstration account',
    'Cuenta de ejemplo. Reemplazar alias, CBU y QR por los datos reales.' =>
        'Example account. Replace the alias, CBU and QR with the real details.',

    // --- Paises (formulario de registro) ---
    'Mexico' => 'Mexico',
    'México' => 'Mexico',
    'Perú' => 'Peru',
    'Brasil' => 'Brazil',
    'España' => 'Spain',
    'Estados Unidos' => 'United States',

    // --- Estados de los turnos ---
    'Pendiente' => 'Pending',
    'En curso' => 'In progress',
    'Finalizada' => 'Completed',
    'Cancelada' => 'Cancelled',

    // --- Estados de los avisos (tabla notificacion) ---
    'pendiente' => 'Pending',
    'enviado' => 'Sent',
    'error' => 'Failed',

    // --- Metodos de pago ---
    'efectivo' => 'Cash',
    'tarjeta' => 'Card',
    'transferencia' => 'Transfer',
    'prepago' => 'Prepaid account',
);

/**
 * Traduce un dato de la base segun el idioma activo de la sesion.
 *
 * En espanol devuelve el valor original. En ingles devuelve la version
 * traducida si la hay, y el original si no.
 */
function tgs_traducir(string $valor): string
{
    $idioma = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'es';
    if ($idioma !== 'en' || $valor === '') {
        return $valor;
    }

    global $tgs_traducciones_en;

    return $tgs_traducciones_en[$valor] ?? $valor;
}

/**
 * Traduce un dato de la base usando un idioma dado (por si la pagina
 * necesita forzar uno distinto del de la sesion).
 */
function tgs_traducir_en(string $valor, string $idioma): string
{
    if ($idioma !== 'en' || $valor === '') {
        return $valor;
    }

    global $tgs_traducciones_en;

    return $tgs_traducciones_en[$valor] ?? $valor;
}
