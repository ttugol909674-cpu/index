<?php
/**
 * TGS - Taller Global de Servicios
 * Conexion PDO a la base de datos.
 *
 * Antes este archivo traia usuario y contrasena escritos a mano, lo que
 * hacia imposible desplegar el proyecto en otro equipo sin editar codigo.
 * Ahora la configuracion sale de .env y .env.local a traves de config.php.
 */

require_once __DIR__ . '/config.php';

// Enlace PDO con los parametros resueltos desde el entorno.
$conexion = tgs_pdo();

/**
 * El campo telefono se agrego despues del primer despliegue, asi que
 * se comprueba antes de intentar crearlo. Es idempotente: el ALTER
 * solo llega a ejecutarse una vez, en la primera peticion.
 */
try {
    $columna_telefono = $conexion->query("SHOW COLUMNS FROM usuario LIKE 'telefono'");

    if (!$columna_telefono->fetch(PDO::FETCH_ASSOC)) {
        $conexion->exec('ALTER TABLE usuario ADD COLUMN telefono VARCHAR(30) NULL');
    }
} catch (PDOException $e) {
    // Si la tabla usuario aun no existe (base recien creada) no es un
    // error: el esquema definitivo llega desde db/schema.sql.
}
