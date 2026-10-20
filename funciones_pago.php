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
 * Metodos de pago admitidos. 'prepago' es el pago con el saldo de la
 * cuenta del cliente: se cobra directo desde la aplicacion.
 */
function tgs_metodos_pago(): array
{
    return [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'prepago' => 'Cuenta prepaga'
    ];
}

/**
 * Emite (o recupera) la factura electronica de una reserva.
 *
 * La factura se genera cuando el pago queda concretado. Si ya existe una
 * emitida para esa reserva, se devuelve la misma: nunca se duplica.
 * Devuelve el id de la factura o null si no se pudo emitir.
 */
function tgs_generar_factura(PDO $conexion, int $id_reserva, string $forma_pago = 'efectivo', ?string $notas = null): ?int
{
    try {
        // Si ya hay una factura emitida para la reserva, se reutiliza.
        $buscar = $conexion->prepare("SELECT id_factura FROM factura WHERE id_reserva = :id_reserva AND estado = 'emitida' LIMIT 1");
        $buscar->execute(['id_reserva' => $id_reserva]);
        $existente = $buscar->fetchColumn();
        if ($existente !== false && $existente !== null) {
            return (int) $existente;
        }

        // Datos de la reserva para armar el comprobante.
        $consulta = $conexion->prepare(
            'SELECT r.id_reserva, r.cedula_usuario, r.precio_total, r.estado,
                    COALESCE((SELECT SUM(p.monto) FROM pago p WHERE p.id_reserva = r.id_reserva AND p.estado = :estado_pago), 0) AS total_pagado
             FROM reserva r WHERE r.id_reserva = :id_reserva LIMIT 1'
        );
        $consulta->execute(['id_reserva' => $id_reserva, 'estado_pago' => 'pagado']);
        $reserva = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            return null;
        }

        // El total facturado es el precio congelado de la reserva; si no
        // hay, se usa lo efectivamente cobrado.
        $total = $reserva['precio_total'] !== null ? (float) $reserva['precio_total'] : (float) $reserva['total_pagado'];
        if ($total <= 0) {
            $total = (float) $reserva['total_pagado'];
        }

        // Numero correlativo del ejercicio: F-AAAA-NNNN.
        $contar = $conexion->query("SELECT COUNT(*) FROM factura WHERE numero LIKE 'F-" . date('Y') . "-%'");
        $siguiente = (int) ($contar ? $contar->fetchColumn() : 0) + 1;
        $numero = 'F-' . date('Y') . '-' . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);

        $insertar = $conexion->prepare(
            'INSERT INTO factura (numero, id_reserva, cedula_usuario, fecha_emision, total, estado, forma_pago, notas)
             VALUES (:numero, :id_reserva, :cedula_usuario, :fecha_emision, :total, :estado, :forma_pago, :notas)'
        );
        $insertar->execute([
            'numero' => $numero,
            'id_reserva' => $id_reserva,
            'cedula_usuario' => $reserva['cedula_usuario'],
            'fecha_emision' => date('Y-m-d'),
            'total' => number_format($total, 2, '.', ''),
            'estado' => 'emitida',
            'forma_pago' => $forma_pago,
            'notas' => $notas
        ]);

        return (int) $conexion->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Factura emitida de una reserva (si existe).
 */
function tgs_factura_de_reserva(PDO $conexion, int $id_reserva): ?array
{
    try {
        $consulta = $conexion->prepare("SELECT * FROM factura WHERE id_reserva = :id_reserva AND estado = 'emitida' ORDER BY id_factura DESC LIMIT 1");
        $consulta->execute(['id_reserva' => $id_reserva]);
        $factura = $consulta->fetch(PDO::FETCH_ASSOC);
        return $factura ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Saldo de la cuenta prepaga del cliente.
 */
function tgs_saldo_prepago(PDO $conexion, string $cedula): float
{
    try {
        $consulta = $conexion->prepare('SELECT COALESCE(saldo_prepago, 0) FROM usuario WHERE cedula = :cedula LIMIT 1');
        $consulta->execute(['cedula' => $cedula]);
        return (float) $consulta->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
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
