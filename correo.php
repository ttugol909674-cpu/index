<?php
/**
 * TGS - Taller Global de Servicios
 * Servicio de envio de correo.
 *
 * Se usa para avisarle al cliente cuando su turno cambia de estado
 * (por ejemplo, cuando el taller lo marca como "Finalizada") y cuando se
 * reprograma un turno.
 *
 * Transporte, en este orden:
 *  1. SMTP: si esta definido SMTP_HOST se habla SMTP con el servidor.
 *  2. mail() de PHP: si el servidor tiene un MTA configurado.
 *  3. Sin transporte: el aviso queda registrado como pendiente y se puede
 *     reintentar mas tarde desde notificaciones.php.
 *
 * Cada intento queda registrado en la tabla 'notificacion' con su estado
 * (enviado, error o pendiente), asi el administrador puede ver que avios
 * salieron y cuales fallaron.
 *
 * No usa librerias externas: el cliente SMTP esta implementado con
 * fsockopen para que el proyecto no dependa de composer.
 *
 * Dependencias: config.php (configuracion), conexion.php (registro).
 */
require_once __DIR__ . '/config.php';

/**
 * Resultado de un envio: 'enviado', 'error' o 'pendiente'.
 */
function tgs_enviar_correo(string $destinatario, string $asunto, string $cuerpo, array $opciones = []): array
{
    $destinatario = trim($destinatario);
    if ($destinatario === '' || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return ['estado' => 'error', 'detalle' => 'Destinatario invalido'];
    }

    $remitente = $opciones['remitente'] ?? tgs_config_correo()['remitente'];
    $remitente_nombre = $opciones['remitente_nombre'] ?? 'TGS - Taller Global de Servicios';

    $correo = tgs_config_correo();

    // Cabeceras comunes. Se evita el salto de linea en el asunto (inyeccion
    // de cabeceras) y el cuerpo va en UTF-8.
    $cabeceras = [
        'From: ' . $remitente_nombre . ' <' . $remitente . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: TGS/' . phpversion()
    ];

    // 1) Transporte SMTP.
    if ($correo['host'] !== '') {
        return tgs_correo_smtp($destinatario, $remitente, $asunto, $cuerpo, $correo);
    }

    // 2) mail() de PHP (necesita un MTA en el servidor).
    if (function_exists('mail') && tgs_tiene_mta()) {
        $cabeceras_php = implode("\r\n", $cabeceras);
        $ok = @mail($destinatario, $asunto, $cuerpo, $cabeceras_php);
        if ($ok) {
            return ['estado' => 'enviado', 'detalle' => 'Enviado con mail()'];
        }
        return ['estado' => 'error', 'detalle' => 'mail() devolvio false (revisar el MTA del servidor)'];
    }

    // 3) Sin transporte configurado: el aviso queda pendiente.
    return ['estado' => 'pendiente', 'detalle' => 'Sin transporte de correo configurado (falta SMTP_HOST)'];
}

/**
 * Indica si el servidor tiene un MTA (programa sendmail) realmente disponible.
 * ini_get('sendmail_path') siempre trae un valor por defecto, asi que hay
 * que comprobar que el binario exista y sea ejecutable.
 */
function tgs_tiene_mta(): bool
{
    $path = trim((string) ini_get('sendmail_path'));
    if ($path === '') {
        return false;
    }

    $binario = strtok($path, " \t");
    if ($binario === false || $binario === '') {
        return false;
    }

    // Ruta absoluta o relativa al sistema de archivos.
    if (strpos($binario, '/') !== false) {
        return is_executable($binario);
    }

    // Nombre simple: se busca en el PATH.
    foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directorio) {
        if ($directorio !== '' && is_executable($directorio . '/' . $binario)) {
            return true;
        }
    }

    return false;
}

/**
 * Cliente SMTP minimo: se conecta al servidor, autentica si hace falta y
 * entrega el mensaje. Devuelve el estado del envio.
 */
function tgs_correo_smtp(string $destinatario, string $remitente, string $asunto, string $cuerpo, array $correo): array
{
    $host = $correo['host'];
    $puerto = $correo['puerto'] > 0 ? $correo['puerto'] : 587;
    $timeout = $correo['timeout'] > 0 ? $correo['timeout'] : 10;

    $destino = ($correo['tls'] ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;
    $socket = @fsockopen($destino, $puerto, $errno, $errstr, $timeout);

    if (!$socket) {
        return ['estado' => 'error', 'detalle' => 'No se pudo conectar al servidor SMTP: ' . $errstr . ' (' . $errno . ')'];
    }

    stream_set_timeout($socket, $timeout);

    $respuesta = tgs_smtp_leer($socket);
    if (!tgs_smtp_espera_ok($respuesta, 220)) {
        fclose($socket);
        return ['estado' => 'error', 'detalle' => 'SMTP: el servidor no respondio 220. ' . tgs_smtp_resumir($respuesta)];
    }

    // Saludo del cliente.
    fwrite($socket, "EHLO tgs.local\r\n");
    $respuesta = tgs_smtp_leer($socket);
    if (!tgs_smtp_espera_ok($respuesta, 250)) {
        // Algunos servidores solo aceptan HELO.
        fwrite($socket, "HELO tgs.local\r\n");
        $respuesta = tgs_smtp_leer($socket);
        if (!tgs_smtp_espera_ok($respuesta, 250)) {
            fclose($socket);
            return ['estado' => 'error', 'detalle' => 'SMTP: fallo el saludo. ' . tgs_smtp_resumir($respuesta)];
        }
    }

    // STARTTLS cuando el puerto no es implicito y el servidor lo ofrece.
    if ($correo['starttls'] && !$correo['tls']) {
        fwrite($socket, "STARTTLS\r\n");
        $respuesta = tgs_smtp_leer($socket);
        if (tgs_smtp_espera_ok($respuesta, 220)) {
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['estado' => 'error', 'detalle' => 'SMTP: no se pudo activar TLS'];
            }
            fwrite($socket, "EHLO tgs.local\r\n");
            tgs_smtp_leer($socket);
        }
    }

    // Autenticacion (si hay usuario configurado).
    if ($correo['usuario'] !== '') {
        fwrite($socket, "AUTH LOGIN\r\n");
        $respuesta = tgs_smtp_leer($socket);
        if (tgs_smtp_espera_ok($respuesta, 334)) {
            fwrite($socket, base64_encode($correo['usuario']) . "\r\n");
            $respuesta = tgs_smtp_leer($socket);
            if (tgs_smtp_espera_ok($respuesta, 334)) {
                fwrite($socket, base64_encode($correo['clave']) . "\r\n");
                $respuesta = tgs_smtp_leer($socket);
                if (!tgs_smtp_espera_ok($respuesta, 235)) {
                    fclose($socket);
                    return ['estado' => 'error', 'detalle' => 'SMTP: usuario o clave incorrectos. ' . tgs_smtp_resumir($respuesta)];
                }
            } else {
                fclose($socket);
                return ['estado' => 'error', 'detalle' => 'SMTP: el servidor no pidio usuario. ' . tgs_smtp_resumir($respuesta)];
            }
        } else {
            fclose($socket);
            return ['estado' => 'error', 'detalle' => 'SMTP: el servidor no acepta AUTH LOGIN. ' . tgs_smtp_resumir($respuesta)];
        }
    }

    // Remitente y destinatario.
    fwrite($socket, 'MAIL FROM:<' . $remitente . ">\r\n");
    $respuesta = tgs_smtp_leer($socket);
    if (!tgs_smtp_espera_ok($respuesta, 250)) {
        fclose($socket);
        return ['estado' => 'error', 'detalle' => 'SMTP: MAIL FROM rechazado. ' . tgs_smtp_resumir($respuesta)];
    }

    fwrite($socket, 'RCPT TO:<' . $destinatario . ">\r\n");
    $respuesta = tgs_smtp_leer($socket);
    if (!tgs_smtp_espera_ok($respuesta, 250)) {
        fclose($socket);
        return ['estado' => 'error', 'detalle' => 'SMTP: RCPT TO rechazado. ' . tgs_smtp_resumir($respuesta)];
    }

    fwrite($socket, "DATA\r\n");
    $respuesta = tgs_smtp_leer($socket);
    if (!tgs_smtp_espera_ok($respuesta, 354)) {
        fclose($socket);
        return ['estado' => 'error', 'detalle' => 'SMTP: DATA rechazado. ' . tgs_smtp_resumir($respuesta)];
    }

    // Cuerpo del mensaje. Se normalizan los saltos de linea a CRLF y se
    // antecede cada linea con ">" para evitar que un "." la cierre antes.
    $mensaje = "From: $remitente\r\n";
    $mensaje .= "To: $destinatario\r\n";
    $mensaje .= "Subject: $asunto\r\n";
    $mensaje .= "MIME-Version: 1.0\r\n";
    $mensaje .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mensaje .= "X-Mailer: TGS/" . phpversion() . "\r\n";
    $mensaje .= "\r\n";
    $mensaje .= preg_replace('/\r\n|\r|\n/', "\r\n", $cuerpo);
    $mensaje = preg_replace('/^\./m', '..', $mensaje);

    fwrite($socket, $mensaje . "\r\n.\r\n");
    $respuesta = tgs_smtp_leer($socket);
    if (!tgs_smtp_espera_ok($respuesta, 250)) {
        fclose($socket);
        return ['estado' => 'error', 'detalle' => 'SMTP: el mensaje no fue aceptado. ' . tgs_smtp_resumir($respuesta)];
    }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return ['estado' => 'enviado', 'detalle' => 'Enviado por SMTP'];
}

/**
 * Lee la respuesta multilinea del servidor SMTP.
 */
function tgs_smtp_leer($socket): string
{
    $respuesta = '';
    while (($linea = fgets($socket, 515)) !== false) {
        $respuesta .= $linea;
        // La ultima linea de una respuesta multilinea tiene "codigo texto"
        // sin guion despues del codigo.
        if (strlen($linea) < 4 || $linea[3] === ' ') {
            break;
        }
    }
    return $respuesta;
}

/**
 * Indica si la respuesta del servidor incluye el codigo esperado.
 */
function tgs_smtp_espera_ok(string $respuesta, int $codigo): bool
{
    return strpos($respuesta, (string) $codigo) === 0;
}

/**
 * Resumen corto de la respuesta del servidor, para los mensajes de error.
 */
function tgs_smtp_resumir(string $respuesta): string
{
    $limpia = preg_replace('/\s+/', ' ', trim($respuesta));
    return substr($limpia, 0, 180);
}

/**
 * Registra una notificacion en la base y la intenta enviar.
 *
 * Devuelve un arreglo con el estado ('enviado', 'error' o 'pendiente') y el
 * identificador de la notificacion para poder reintentarla.
 */
function tgs_notificar(PDO $conexion, array $datos): array
{
    $destinatario = trim((string) ($datos['email'] ?? ''));
    $asunto = (string) ($datos['asunto'] ?? '');
    $cuerpo = (string) ($datos['cuerpo'] ?? '');
    $tipo = (string) ($datos['tipo'] ?? 'general');

    // Se registra siempre, incluso si el envío falla: queda el registro de
    // lo que se quiso avisar y se puede reintentar.
    try {
        $stmt = $conexion->prepare('INSERT INTO notificacion (id_reserva, cedula_usuario, email_destino, asunto, cuerpo, tipo, estado, intentos) VALUES (:id_reserva, :cedula_usuario, :email_destino, :asunto, :cuerpo, :tipo, :estado, 0)');
        $stmt->execute([
            'id_reserva' => $datos['id_reserva'] ?? null,
            'cedula_usuario' => $datos['cedula_usuario'] ?? null,
            'email_destino' => $destinatario,
            'asunto' => $asunto,
            'cuerpo' => $cuerpo,
            'tipo' => $tipo,
            'estado' => 'pendiente'
        ]);
        $id_notificacion = (int) $conexion->lastInsertId();
    } catch (PDOException $e) {
        return ['estado' => 'error', 'detalle' => 'No se pudo registrar la notificacion'];
    }

    $resultado = tgs_enviar_correo($destinatario, $asunto, $cuerpo, $datos);

    try {
        $actualizar = $conexion->prepare('UPDATE notificacion SET estado = :estado, respuesta_transporte = :respuesta, intentos = intentos + 1, fecha_envio = :fecha_envio WHERE id_notificacion = :id_notificacion');
        $actualizar->execute([
            'estado' => $resultado['estado'],
            'respuesta' => substr((string) ($resultado['detalle'] ?? ''), 0, 250),
            'fecha_envio' => $resultado['estado'] === 'enviado' ? date('Y-m-d H:i:s') : null,
            'id_notificacion' => $id_notificacion
        ]);
    } catch (PDOException $e) {
        // El aviso ya se registro; solo falla la actualizacion del estado.
    }

    $resultado['id_notificacion'] = $id_notificacion;
    return $resultado;
}

/**
 * Aviso al cliente de que su turno quedo finalizado y el vehiculo esta
 * listo para retirar. Devuelve un texto corto para mostrar en el panel.
 */
/**
 * Avisa al cliente que su vehiculo ya esta listo para retirar.
 *
 * El correo sale en el idioma que se le indica. Como la base no guarda
 * el idioma preferido del cliente, se usa el idioma de la sesion de
 * quien esta gestionando el turno (el taller), que es quien decide en
 * que idioma esta trabajando.
 */
function tgs_avisar_reserva_finalizada(PDO $conexion, int $id_reserva, string $idioma = 'es'): string
{
    try {
        $consulta = $conexion->prepare(
            'SELECT r.id_reserva, r.fecha_reserva, r.hora_reserva, r.cedula_usuario,
                    u.email, u.nombre, u.apellido,
                    v.marca, v.modelo, v.patente,
                    s.nombre AS servicio_nombre
             FROM reserva r
             LEFT JOIN usuario u ON u.cedula = r.cedula_usuario
             LEFT JOIN vehiculo v ON v.id_vehiculo = r.id_vehiculo
             LEFT JOIN servicio s ON s.id_servicio = r.id_servicio
             WHERE r.id_reserva = :id_reserva
             LIMIT 1'
        );
        $consulta->execute(['id_reserva' => $id_reserva]);
        $reserva = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            return 'No se encontro la reserva para avisar al cliente.';
        }

        $email = trim((string) ($reserva['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'El cliente no tiene un correo valido, no se le pudo avisar.';
        }

        $nombre = trim(($reserva['nombre'] ?? '') . ' ' . ($reserva['apellido'] ?? '')) ?: 'Cliente';
        $vehiculo = trim(($reserva['marca'] ?? '') . ' ' . ($reserva['modelo'] ?? '')) ?: 'tu vehiculo';
        $patente = $reserva['patente'] ?? '';
        $servicio = $reserva['servicio_nombre'] ?? 'el servicio programado';
        $fecha = $reserva['fecha_reserva'] ?? '';
        $hora = $reserva['hora_reserva'] ?? '';

        if ($idioma === 'en') {
            $asunto = 'Your vehicle is ready for pickup';
            $cuerpo = "Hello $nombre,\n\n"
                . "We are writing from TGS - Global Workshop Services: the work on $vehiculo"
                . ($patente !== '' ? " (license plate $patente)" : '')
                . " is finished.\n\n"
                . "Appointment: $fecha at $hora\n"
                . "Work carried out: $servicio\n\n"
                . "You can come and pick up your vehicle at the workshop.\n\n"
                . "Best regards,\nTGS - Global Workshop Services";
        } else {
            $asunto = 'Tu vehiculo esta listo para retirar';
            $cuerpo = "Hola $nombre,\n\n"
                . "Te escribimos desde TGS - Taller Global de Servicios: el trabajo sobre $vehiculo"
                . ($patente !== '' ? " (patente $patente)" : '')
                . " ya termino.\n\n"
                . "Turno: $fecha a las $hora\n"
                . "Trabajo realizado: $servicio\n\n"
                . "Podés pasar a retirar tu vehiculo en el taller.\n\n"
                . "Saludos,\nTGS - Taller Global de Servicios";
        }

        $envio = tgs_notificar($conexion, [
            'id_reserva' => $id_reserva,
            'cedula_usuario' => $reserva['cedula_usuario'] ?? null,
            'email' => $email,
            'asunto' => $asunto,
            'cuerpo' => $cuerpo,
            'tipo' => 'finalizacion'
        ]);

        if ($envio['estado'] === 'enviado') {
            return 'Se aviso al cliente por correo.';
        }
        if ($envio['estado'] === 'pendiente') {
            return 'El aviso al cliente quedo pendiente de envio (falta configurar el correo).';
        }
        return 'No se pudo enviar el aviso al cliente.';
    } catch (PDOException $e) {
        return 'No se pudo generar el aviso al cliente.';
    }
}

/**
 * Reintenta el envio de una notificacion registrada.
 */
function tgs_reintentar_notificacion(PDO $conexion, int $id_notificacion): array
{    try {
        $consulta = $conexion->prepare('SELECT * FROM notificacion WHERE id_notificacion = :id_notificacion LIMIT 1');
        $consulta->execute(['id_notificacion' => $id_notificacion]);
        $notificacion = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$notificacion) {
            return ['estado' => 'error', 'detalle' => 'La notificacion no existe'];
        }

        $resultado = tgs_enviar_correo(
            (string) ($notificacion['email_destino'] ?? ''),
            (string) ($notificacion['asunto'] ?? ''),
            (string) ($notificacion['cuerpo'] ?? '')
        );

        $actualizar = $conexion->prepare('UPDATE notificacion SET estado = :estado, respuesta_transporte = :respuesta, intentos = intentos + 1, fecha_envio = :fecha_envio WHERE id_notificacion = :id_notificacion');
        $actualizar->execute([
            'estado' => $resultado['estado'],
            'respuesta' => substr((string) ($resultado['detalle'] ?? ''), 0, 250),
            'fecha_envio' => $resultado['estado'] === 'enviado' ? date('Y-m-d H:i:s') : null,
            'id_notificacion' => $id_notificacion
        ]);

        return $resultado;
    } catch (PDOException $e) {
        return ['estado' => 'error', 'detalle' => 'No se pudo reintentar la notificacion'];
    }
}
