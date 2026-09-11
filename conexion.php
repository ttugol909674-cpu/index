<?php
# Dirección del servidor donde está la base de datos
$host = 'localhost';

# Nombre de la base de datos del proyecto
$dbname = 'bluetech_base';

# Usuario con permisos para acceder a MySQL
$username = 'root';

# Contraseña del usuario; en XAMPP suele estar vacía
$password = ''; // Por defecto en XAMPP suele estar vacío

try {
    # Se crea la conexión con PDO
    # La conexión usa UTF-8 para conservar correctamente acentos y caracteres del sitio
    # PDO permite conectarse a MySQL de forma segura y moderna
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

    # Configura PDO para que muestre errores si falla la consulta o la conexión
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    # Si la conexión falla, se corta la ejecución y se muestra el mensaje de error
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>