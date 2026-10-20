-- =====================================================================
-- TGS - Taller Global de Servicios
-- Vistas SQL para los reportes del sistema
--
-- Las vistas son el requerimiento "Consultas Avanzadas: uso de JOINs,
-- subconsultas y vistas para generar reportes complejos, como el
-- historial de servicios de un vehiculo o la facturacion por taller"
-- de la catedra de Programacion Full Stack.
--
-- Se definen con CREATE OR REPLACE VIEW para que este archivo sea
-- idempotente: se puede volver a aplicar sin romper nada.
-- =====================================================================

-- ---------------------------------------------------------------------
-- v_historial_servicios: historial de servicios de los vehiculos de
-- un cliente, con sede, servicio, estado y costo. Es el reporte que
-- muestra la pantalla historial_cliente.php.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_historial_servicios` AS
SELECT
    r.`id_reserva`,
    u.`cedula`,
    u.`nombre`  AS cliente_nombre,
    u.`email`   AS cliente_email,
    v.`id_vehiculo`,
    v.`patente`,
    v.`marca`,
    v.`modelo`,
    v.`anio`,
    t.`id_taller`,
    t.`nombre`  AS taller_nombre,
    s.`nombre`  AS servicio_nombre,
    r.`fecha_reserva`,
    r.`hora_reserva`,
    r.`estado`,
    r.`precio_total`,
    r.`estado_pago`,
    IFNULL((SELECT SUM(p.`monto`) FROM `pago` p
             WHERE p.`id_reserva` = r.`id_reserva`
               AND p.`estado` = 'pagado'), 0) AS total_cobrado,
    IFNULL((SELECT SUM(p.`monto`) FROM `pago` p
             WHERE p.`id_reserva` = r.`id_reserva`
               AND p.`estado` = 'pagado'), 0)
        - IFNULL(r.`precio_total`, 0) AS saldo_pendiente
FROM `reserva` r
JOIN `usuario`  u ON u.`cedula`      = r.`cedula_usuario`
JOIN `vehiculo` v ON v.`id_vehiculo` = r.`id_vehiculo`
JOIN `taller`   t ON t.`id_taller`   = r.`id_taller`
JOIN `servicio` s ON s.`id_servicio` = r.`id_servicio`
WHERE r.`estado` <> 'Cancelada';

-- ---------------------------------------------------------------------
-- v_facturacion_por_taller: cuanto facturo y cuanto cobro cada sede.
-- Reporte de gestion del administrador.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_facturacion_por_taller` AS
SELECT
    t.`id_taller`,
    t.`nombre` AS taller_nombre,
    COUNT(DISTINCT r.`id_reserva`)                          AS turnos,
    COUNT(DISTINCT r.`cedula_usuario`)                      AS clientes,
    IFNULL(SUM(r.`precio_total`), 0)                         AS facturado,
    IFNULL(SUM(p.`total_cobrado`), 0)                       AS cobrado,
    IFNULL(SUM(r.`precio_total`), 0) - IFNULL(SUM(p.`total_cobrado`), 0) AS saldo_pendiente
FROM `taller` t
JOIN `reserva` r ON r.`id_taller` = t.`id_taller`
LEFT JOIN (
    SELECT `id_reserva`, SUM(`monto`) AS `total_cobrado`
      FROM `pago`
     WHERE `estado` = 'pagado'
     GROUP BY `id_reserva`
) p ON p.`id_reserva` = r.`id_reserva`
GROUP BY t.`id_taller`, t.`nombre`;

-- ---------------------------------------------------------------------
-- v_ocupacion_sedes: cuantas reservas hay por sede, por dia y por
-- franja, comparado con la capacidad de la sede. Sirve para ver la
-- ocupacion y los horarios libres.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_ocupacion_sedes` AS
SELECT
    t.`id_taller`,
    t.`nombre`   AS taller_nombre,
    t.`capacidad`,
    r.`fecha_reserva`,
    r.`hora_reserva`,
    COUNT(*)     AS reservas_en_la_franja,
    t.`capacidad` - COUNT(*) AS lugares_libres
FROM `reserva` r
JOIN `taller` t ON t.`id_taller` = r.`id_taller`
WHERE r.`estado` IN ('Pendiente', 'En curso', 'Finalizada')
GROUP BY t.`id_taller`, t.`nombre`, t.`capacidad`,
         r.`fecha_reserva`, r.`hora_reserva`;

-- ---------------------------------------------------------------------
-- v_cobros_por_metodo: como se pagan los servicios, para el informe
-- de administracion.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_cobros_por_metodo` AS
SELECT
    p.`metodo`,
    p.`estado`,
    COUNT(*)                AS cantidad,
    IFNULL(SUM(p.`monto`), 0) AS total,
    MIN(p.`fecha_pago`)     AS primer_cobro,
    MAX(p.`fecha_pago`)     AS ultimo_cobro
FROM `pago` p
GROUP BY p.`metodo`, p.`estado`;
