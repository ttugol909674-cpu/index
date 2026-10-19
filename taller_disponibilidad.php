<?php
/**
 * TGS - Taller Global de Servicios
 * Disponibilidad de los talleres.
 *
 * Reglas que se aplican al tomar un turno:
 *  - El turno se pide en una sede concreta (taller).
 *  - Cada sede tiene un horario por dia de la semana (taller_horario).
 *  - Cada sede acepta una cantidad de reservas simultaneas por franja
 *    horaria (taller.capacidad).
 *  - La franja de una hora debe caber dentro de la ventana de atencion y
 *    tener un lugar libre.
 *
 * Las franjas son de 60 minutos y arrancan en la hora de apertura, por lo
 * que el horario 08:30-18:30 genera las franjas 08:30, 09:30, ... 17:30.
 *
 * Dependencias: conexion.php (para las consultas).
 */

/**
 * Minutos de duracion de cada franja de reserva.
 */
function tgs_duracion_franja(): int
{
    return 60;
}

/**
 * Dia de la semana (1 = lunes ... 7 = domingo) de una fecha.
 */
function tgs_dia_semana(string $fecha): int
{
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return 0;
    }
    // date('N') devuelve 1 (lunes) a 7 (domingo).
    return (int) date('N', $timestamp);
}

/**
 * Ventana de atencion de un taller para una fecha.
 * Devuelve null si el dia no tiene horario configurado o esta inactivo.
 */
function tgs_horario_del_dia(PDO $conexion, int $id_taller, string $fecha): ?array
{
    $dia = tgs_dia_semana($fecha);
    if ($dia < 1 || $dia > 7) {
        return null;
    }

    try {
        $stmt = $conexion->prepare(
            'SELECT hora_inicio, hora_fin, activo FROM taller_horario
             WHERE id_taller = :id_taller AND dia_semana = :dia_semana LIMIT 1'
        );
        $stmt->execute(['id_taller' => $id_taller, 'dia_semana' => $dia]);
        $horario = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }

    if (!$horario || (int) $horario['activo'] !== 1) {
        return null;
    }

    return [
        'dia_semana' => $dia,
        'hora_inicio' => substr((string) $horario['hora_inicio'], 0, 5),
        'hora_fin' => substr((string) $horario['hora_fin'], 0, 5)
    ];
}

/**
 * Capacidad de reservas simultaneas de un taller.
 */
function tgs_capacidad_taller(PDO $conexion, int $id_taller): int
{
    try {
        $stmt = $conexion->prepare('SELECT capacidad FROM taller WHERE id_taller = :id_taller LIMIT 1');
        $stmt->execute(['id_taller' => $id_taller]);
        $capacidad = $stmt->fetchColumn();
        return $capacidad === false || $capacidad === null ? 1 : max(1, (int) $capacidad);
    } catch (PDOException $e) {
        return 1;
    }
}

/**
 * Cantidad de reservas que ya tienen esa franja en ese taller.
 * Las reservas canceladas no ocupan lugar.
 * $id_reserva_excluir sirve para no contar la propia reserva cuando se la
 * esta reprogramando (si no, su lugar actual bloquearia el nuevo horario).
 */
function tgs_reservas_en_franja(PDO $conexion, int $id_taller, string $fecha, string $hora_inicio, string $hora_fin, int $id_reserva_excluir = 0): int
{
    try {
        $sql = "SELECT COUNT(*) FROM reserva
                WHERE id_taller = :id_taller
                  AND fecha_reserva = :fecha_reserva
                  AND estado <> 'Cancelada'
                  AND hora_reserva >= :hora_inicio
                  AND hora_reserva < :hora_fin";
        $parametros = [
            'id_taller' => $id_taller,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin
        ];

        if ($id_reserva_excluir > 0) {
            $sql .= ' AND id_reserva <> :id_reserva_excluir';
            $parametros['id_reserva_excluir'] = $id_reserva_excluir;
        }

        $stmt = $conexion->prepare($sql);
        $stmt->execute($parametros);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Franjas libres y ocupadas de un taller para una fecha.
 *
 * Devuelve un arreglo con:
 *   - ok:          si el taller atiende ese dia
 *   - horario:     ventana de atencion (inicio, fin)
 *   - capacidad:   lugares por franja
 *   - libres:      horarios con lugar disponible
 *   - ocupadas:    horarios sin lugar
 *   - mensaje:     texto para mostrar al cliente
 */
function tgs_franjas_del_taller(PDO $conexion, int $id_taller, string $fecha, int $id_reserva_excluir = 0): array
{
    $respuesta = [
        'ok' => false,
        'horario' => null,
        'capacidad' => tgs_capacidad_taller($conexion, $id_taller),
        'libres' => [],
        'ocupadas' => [],
        'mensaje' => ''
    ];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $respuesta['mensaje'] = 'Fecha invalida';
        return $respuesta;
    }

    $horario = tgs_horario_del_dia($conexion, $id_taller, $fecha);
    if ($horario === null) {
        $respuesta['mensaje'] = 'El taller no atiende ese dia';
        return $respuesta;
    }
    $respuesta['horario'] = $horario;

    $duracion = tgs_duracion_franja();
    $inicio = strtotime($fecha . ' ' . $horario['hora_inicio']);
    $fin = strtotime($fecha . ' ' . $horario['hora_fin']);
    if ($inicio === false || $fin === false || $fin <= $inicio) {
        $respuesta['mensaje'] = 'El horario del taller no es valido';
        return $respuesta;
    }

    // Si la fecha es hoy, las franjas que ya pasaron no se ofrecen.
    $ahora = time();
    $es_hoy = date('Y-m-d') === $fecha;

    for ($cursor = $inicio; $cursor + $duracion * 60 <= $fin; $cursor += $duracion * 60) {
        $hora_inicio = date('H:i', $cursor);
        $hora_fin = date('H:i', $cursor + $duracion * 60);

        if ($es_hoy && $cursor <= $ahora) {
            continue;
        }

        $ocupadas = tgs_reservas_en_franja($conexion, $id_taller, $fecha, $hora_inicio, $hora_fin, $id_reserva_excluir);
        if ($ocupadas >= $respuesta['capacidad']) {
            $respuesta['ocupadas'][] = $hora_inicio;
        } else {
            $respuesta['libres'][] = $hora_inicio;
        }
    }

    $respuesta['ok'] = true;
    $respuesta['mensaje'] = count($respuesta['libres']) > 0
        ? 'Horarios disponibles para ese dia'
        : 'No quedan horarios libres para ese dia';

    return $respuesta;
}

/**
 * Comprueba si un turno (taller, fecha y hora) se puede tomar.
 * Es la validacion que se aplica al crear o reprogramar una reserva.
 *
 * $id_reserva_excluir: cuando se reprograma una reserva, su lugar actual
 * queda libre y no debe contar como ocupado.
 */
function tgs_comprobar_disponibilidad(PDO $conexion, int $id_taller, string $fecha, string $hora, int $id_reserva_excluir = 0): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return ['ok' => false, 'mensaje' => 'La fecha o la hora no son validas'];
    }

    $horario = tgs_horario_del_dia($conexion, $id_taller, $fecha);
    if ($horario === null) {
        return ['ok' => false, 'mensaje' => 'El taller no atiende ese dia'];
    }

    $inicio = strtotime($fecha . ' ' . $horario['hora_inicio']);
    $fin = strtotime($fecha . ' ' . $horario['hora_fin']);
    $hora_pedida = strtotime($fecha . ' ' . $hora);

    if ($inicio === false || $fin === false || $hora_pedida === false) {
        return ['ok' => false, 'mensaje' => 'La fecha o la hora no son validas'];
    }

    // La hora pedida tiene que estar dentro de la ventana de atencion.
    if ($hora_pedida < $inicio || $hora_pedida >= $fin) {
        return ['ok' => false, 'mensaje' => 'Ese horario esta fuera del horario de atencion del taller'];
    }

    // Franja que contiene la hora pedida. Las franjas arrancan en la hora
    // de apertura y duran tgs_duracion_franja() minutos: una hora pedida
    // que cae entre dos franjas ocupa la primera de las dos.
    $duracion = tgs_duracion_franja() * 60;
    $indice = (int) floor(($hora_pedida - $inicio) / $duracion);
    $slot_inicio = $inicio + $indice * $duracion;
    $slot_fin = $slot_inicio + $duracion;

    // Si el turno no entra entero en la ventana de atencion, no se puede.
    if ($slot_fin > $fin) {
        return ['ok' => false, 'mensaje' => 'Ese horario no alcanza a completar el turno dentro del horario de atencion'];
    }

    $ocupadas = tgs_reservas_en_franja(
        $conexion,
        $id_taller,
        $fecha,
        date('H:i', $slot_inicio),
        date('H:i', $slot_fin),
        $id_reserva_excluir
    );
    $capacidad = tgs_capacidad_taller($conexion, $id_taller);

    if ($ocupadas >= $capacidad) {
        return ['ok' => false, 'mensaje' => 'Ese horario ya esta completo para ese taller'];
    }

    return ['ok' => true, 'mensaje' => 'Turno disponible'];
}
