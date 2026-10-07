<?php
/**
 * TGS - Taller Global de Servicios
 * Cierre de sesion.
 *
 * Destruye la sesion PHP y devuelve al usuario al login. Se destruye la
 * sesion completa y no solo se desloguean las variables, porque conservar
 * el identificador de sesion (aunque este vacio) permitiria que una
 * cookie robada siguiera siendo valida frente al servidor.
 */
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit();