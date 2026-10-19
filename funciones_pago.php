<?php
/**
 * TGS - Taller Global de Servicios
 * Funciones compartidas del modulo de pagos.
 *
 * Se separan del resto del codigo porque las usan dos pantallas:
 * administrar_pagos.php (listado de cobros) y detalle_reserva.php
 * (detalle de un turno).
 *
 * Dependencias: conexion.php (para las consultas).
 */

/**
 * Recalcula el estado de cobro de una reserva a partir de los cobros
 * vigentes y actualiza la columna estado_pago de la tabla reserva.
 *
 * - Sin cobros vigentes  -> pendiente
 * - Cobrado menos que el total -> parcial
 * - Cobrado al menos el total  -> pagado
 */
function tgs_recalcular_estado_pago(PDO $conexion, int $id_reserva): void
{
    $suma = $conexion->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reserva = :id_reserva AND estado = 'pagado'");
    $suma->execute(['id_reserva' => $id_reserva]);
    $total_pagado = (float) $suma->fetchColumn();

    $precio = $conexion->prepare('SELECT precio_total FROM reserva WHERE id_reserva = :id_reserva');
    $precio->execute(['id_reserva' => $id_reserva]);
    $total_reserva = $precio->fetchColumn();

    if ($total_pagado <= 0) {
        $estado = 'pendiente';
    } elseif ($total_reserva !== null && $total_reserva !== false && $total_pagado >= (float) $total_reserva) {
        $estado = 'pagado';
    } else {
        $estado = 'parcial';
    }

    $actualizar = $conexion->prepare('UPDATE reserva SET estado_pago = :estado_pago WHERE id_reserva = :id_reserva');
    $actualizar->execute(['estado_pago' => $estado, 'id_reserva' => $id_reserva]);
}

/**
 * Importe pendiente de cobro de una reserva (total menos lo cobrado).
 */
function tgs_saldo_reserva(PDO $conexion, int $id_reserva): float
{
    $consulta = $conexion->prepare(
        "SELECT COALESCE(r.precio_total, 0) - COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = 'pagado'), 0) AS saldo
         FROM reserva r WHERE r.id_reserva = :id_reserva LIMIT 1"
    );
    $consulta->execute(['id_reserva' => $id_reserva]);
    $saldo = $consulta->fetchColumn();
    return $saldo === false || $saldo === null ? 0.0 : (float) $saldo;
}
