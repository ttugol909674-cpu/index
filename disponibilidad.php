<?php
/**
 * TGS - Taller Global de Servicios
 * Endpoint JSON con los horarios libres de un taller.
 *
 * Se usa desde el formulario de reserva: al elegir la sede y la fecha, el
 * navegador pide los horarios disponibles y los muestra al cliente.
 *
 * Uso:  disponibilidad.php?taller=1&fecha=2026-10-20
 * Respuesta: { ok, taller, horario, capacidad, libres, ocupadas, mensaje }
 *
 * Devuelve siempre JSON (tambien en caso de error) para que el JavaScript
 * del formulario no se rompa.
 *
 * Acceso: requiere sesion iniciada.
 *
 * Dependencias: auth.php, conexion.php, taller_disponibilidad.php
 */
require_once 'auth.php';
require_once 'conexion.php';
require_once 'taller_disponibilidad.php';

// Si no hay sesion se responde con el mismo formato JSON.
if (!isset($_SESSION['cedula']) && !isset($_SESSION['id_usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Sesion no iniciada',
        'libres' => [],
        'ocupadas' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id_taller = filter_input(INPUT_GET, 'taller', FILTER_VALIDATE_INT);
$fecha = trim((string) ($_GET['fecha'] ?? ''));

$respuesta = [
    'ok' => false,
    'taller' => '',
    'horario' => null,
    'capacidad' => 1,
    'libres' => [],
    'ocupadas' => [],
    'mensaje' => ''
];

if (!$id_taller) {
    $respuesta['mensaje'] = 'Taller no indicado';
} else {
    // Nombre del taller, para mostrarlo junto a los horarios.
    try {
        $stmt = $conexion->prepare('SELECT nombre FROM taller WHERE id_taller = :id_taller LIMIT 1');
        $stmt->execute(['id_taller' => $id_taller]);
        $respuesta['taller'] = (string) ($stmt->fetchColumn() ?: '');
    } catch (PDOException $e) {
        $respuesta['taller'] = '';
    }

    $franjas = tgs_franjas_del_taller($conexion, (int) $id_taller, $fecha);
    $respuesta['ok'] = $franjas['ok'];
    $respuesta['horario'] = $franjas['horario'];
    $respuesta['capacidad'] = $franjas['capacidad'];
    $respuesta['libres'] = $franjas['libres'];
    $respuesta['ocupadas'] = $franjas['ocupadas'];
    $respuesta['mensaje'] = $franjas['mensaje'];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
