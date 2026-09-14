<?php
###### # Cierra la sesiÃ³n actual y devuelve al usuario al inicio.
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
