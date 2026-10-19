<?php
/**
 * TGS - Taller Global de Servicios
 * Cargador central de configuracion.
 *
 * Lee dos archivos y los combina con la precedencia:
 *
 *     .env        variables GLOBALES  (versionadas, compartidas por el equipo)
 *     .env.local  variables LOCALES   (NO versionadas, pisan a las globales)
 *
 * El orden de fusion es: .env -> .env.local, de modo que lo local gana.
 * Si un archivo no existe simplemente se ignora, asi el sistema tambien
 * funciona con Apache nativo en XAMPP usando solo .env.
 *
 * No requiere Composer ni ninguna libreria externa: el parser de .env
 * esta implementado a mano en tgs_parse_env().
 */

// Raiz del proyecto: todos los archivos se resuelven relativos a el.
if (!defined('TGS_BASE')) {
    define('TGS_BASE', __DIR__);
}

/**
 * Lee un archivo .env y devuelve un array clave => valor.
 *
 * Tolera: lineas en blanco, comentarios con #, el prefijo "export ",
 * valores entre comillas simples o dobles, y comentarios al final de
 * una linea sin comillas (por ejemplo:  APP_DEBUG=0  # comentario).
 */
function tgs_parse_env(string $archivo): array
{
    $valores = [];

    if (!is_file($archivo) || !is_readable($archivo)) {
        return $valores;
    }

    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        if ($linea === '' || $linea[0] === '#') {
            continue;
        }

        if (strpos($linea, 'export ') === 0) {
            $linea = trim(substr($linea, 7));
        }

        if (strpos($linea, '=') === false) {
            continue;
        }

        list($clave, $valor) = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        if ($clave === '') {
            continue;
        }

        if ($valor !== '' && ($valor[0] === '"' || $valor[0] === "'")) {
            // Valor entrecomillado: se toma hasta la comilla de cierre y
            // se descarta cualquier comentario posterior.
            $comilla = $valor[0];
            $cierre = strpos($valor, $comilla, 1);
            $valor = $cierre !== false ? substr($valor, 1, $cierre - 1) : substr($valor, 1);
        } else {
            // Valor sin comillas: elimina un comentario final separado
            // por al menos un espacio, para no romper contrasenas con "#".
            $posicion = strpos($valor, ' #');
            if ($posicion !== false) {
                $valor = substr($valor, 0, $posicion);
            }
            $valor = trim($valor);
        }

        $valores[$clave] = $valor;
    }

    return $valores;
}

/**
 * Devuelve la configuracion combinada. Se calcula una sola vez por
 * peticion y se reutiliza en todas las llamadas a tgs_env().
 */
function tgs_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $globales = tgs_parse_env(TGS_BASE . '/.env');
    $locales  = tgs_parse_env(TGS_BASE . '/.env.local');

    // array_merge con el array local segundo: las claves locales pisan
    // a las globales, que es exactamente la precedencia que buscamos.
    $config = array_merge($globales, $locales);

    return $config;
}

/**
 * Obtiene una variable de entorno con valor por defecto opcional.
 */
function tgs_env(string $clave, $defecto = null)
{
    $config = tgs_config();

    return array_key_exists($clave, $config) ? $config[$clave] : $defecto;
}

/**
 * Devuelve los parametros de conexion ya resueltos desde el entorno.
 * La usan tanto tgs_pdo() como tgs_mysqli() para no duplicar defaults.
 */
function tgs_config_bd(): array
{
    return array(
        'host'    => tgs_env('DB_HOST', '127.0.0.1'),
        'puerto'  => tgs_env('DB_PUERTO', '3306'),
        'nombre'  => tgs_env('DB_NAME', 'bluetech_base'),
        'usuario' => tgs_env('DB_USER', 'root'),
        'clave'   => tgs_env('DB_PASS', ''),
        'charset' => tgs_env('DB_CHARSET', 'utf8mb4'),
    );
}

/**
 * Conexion PDO (reutilizada por conexion.php y las paginas que ya
 * usan PDO). Es perezosa: el enlace se abre en la primera llamada.
 */
function tgs_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $bd = tgs_config_bd();
    $dsn = "mysql:host={$bd['host']};port={$bd['puerto']};dbname={$bd['nombre']};charset={$bd['charset']}";

    try {
        $pdo = new PDO($dsn, $bd['usuario'], $bd['clave'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ));
    } catch (PDOException $e) {
        // El error mas comun en este proyecto es que Apache corra como
        // www-data y no pueda leer .env.local: en ese caso la clave llega
        // vacia y MySQL responde "Access denied". Se avisa con precision
        // en vez de mostrar un error de autenticacion generico.
        $envLocal = TGS_BASE . '/.env.local';

        if (is_file($envLocal) && !is_readable($envLocal)) {
            throw new RuntimeException(
                'No se pudo leer .env.local con los permisos actuales: '
                . substr(sprintf('%o', fileperms($envLocal)), -3) . '. '
                . 'Ejecuta: bash scripts/instalar.sh (lo deja en 644).',
                0,
                $e
            );
        }

        if (tgs_env('DB_PASS') === '') {
            throw new RuntimeException(
                'Falta DB_PASS. No se encontro en .env ni en .env.local. '
                . 'Ejecuta: bash scripts/instalar.sh',
                0,
                $e
            );
        }

        throw $e;
    }

    return $pdo;
}

/**
 * Configuracion del envio de correo.
 *
 * Si SMTP_HOST esta definido se usa SMTP (cliente propio, sin librerias
 * externas). Si no, se intenta mail() de PHP, que necesita un MTA instalado
 * en el servidor. Si no hay ninguna de las dos opciones, el envio queda
 * pendiente: el aviso se registra igual en la tabla 'notificacion' para
 * poder reintentarlo mas tarde.
 *
 * Variables: SMTP_HOST, SMTP_PUERTO, SMTP_USUARIO, SMTP_CLAVE, SMTP_REMITENTE,
 * SMTP_TLS (1 = TLS implicito), SMTP_PORTO_SMTP (1 = puerto 587 con STARTTLS).
 */
function tgs_config_correo(): array
{
    return array(
        'host'       => tgs_env('SMTP_HOST', ''),
        'puerto'     => (int) tgs_env('SMTP_PUERTO', 587),
        'usuario'    => tgs_env('SMTP_USUARIO', ''),
        'clave'      => tgs_env('SMTP_CLAVE', ''),
        'remitente'  => tgs_env('SMTP_REMITENTE', tgs_env('SMTP_USUARIO', 'no-reply@tgs.local')),
        'tls'        => (int) tgs_env('SMTP_TLS', 0) === 1,
        'starttls'   => (int) tgs_env('SMTP_PORTO_SMTP', 1) === 1,
        'timeout'    => (int) tgs_env('SMTP_TIMEOUT', 10)
    );
}

/**
 * Conexion mysqli para las paginas que aun usan la API antigua.
 * Reemplaza los 'new mysqli("localhost","root","","bluetech_base")'
 * que estaban escritos a mano con credenciales fijas.
 */
function tgs_mysqli(): mysqli
{
    static $conexion = null;

    if ($conexion instanceof mysqli) {
        return $conexion;
    }

    $bd = tgs_config_bd();

    $conexion = new mysqli($bd['host'], $bd['usuario'], $bd['clave'], $bd['nombre']);

    if ($conexion->connect_errno) {
        throw new RuntimeException(
            'No se pudo conectar con la base de datos: ' . $conexion->connect_error
        );
    }

    $conexion->set_charset($bd['charset']);

    return $conexion;
}

/**
 * Modo de depuracion.
 *
 * La imagen oficial de php:8.4-apache arranca con la configuracion de
 * desarrollo, donde los errores se MUESTRAN en pantalla. Eso significa que
 * un simple aviso de variable indefinida le revela al visitante rutas del
 * servidor. Con APP_DEBUG apagado (el valor por defecto) los errores no se
 * muestran: quedan registrados en el log del contenedor, que es donde hay
 * que mirar si algo falla.
 */
if (function_exists('ini_set')) {
    $tgs_debug = (string) tgs_env('APP_DEBUG', '0');
    if ($tgs_debug === '1' || $tgs_debug === 'true') {
        ini_set('display_errors', '1');
        ini_set('log_errors', '1');
    } else {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }
    error_reporting(E_ALL);
}
