<?php
###### # Cancela una reserva solicitada por el usuario autorizado.
# Inicia la sesión para verificar que el usuario está autenticado
session_start();

# Usa la misma conexión PDO del proyecto
require_once "conexion.php";

# Si no hay usuario logueado, se fuerza al login
if (!isset($_SESSION['cedula'])) {
    header('Location: login.php');
    exit;
}

# Solo acepta solicitudes POST con un id de reserva válido
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['id_reserva'])) {
    header("Location: reservas.php");
    exit();
}

# Convierte el ID a entero para evitar datos inválidos
$id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
$cedula = $_SESSION['cedula'];

# Si el id no es válido, se devuelve al listado
if ($id_reserva === false || $id_reserva === null) {
    header("Location: reservas.php");
    exit();
}

try {
    # Elimina solo la reserva que pertenece al usuario actual
    $sql = "DELETE FROM reserva WHERE id_reserva = :id_reserva AND cedula_usuario = :cedula_usuario";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        'id_reserva' => $id_reserva,
        'cedula_usuario' => $cedula
    ]);
} catch (PDOException $e) {
    # Se deja el flujo seguro: no rompe la app, solo regresa a la vista
}

header("Location: reservas.php");
exit();
?>
