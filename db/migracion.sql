-- =====================================================================
-- TGS - Taller Global de Servicios
-- Migracion de base de datos: modulo de pago y facturacion
--
-- Para que sirve
--   Las instalaciones que ya tienen la base creada (el volumen del
--   contenedor ya existe) nunca vuelven a ejecutar db/schema.sql, porque
--   MySQL solo corre los scripts de /docker-entrypoint-initdb.d la primera
--   vez. Este archivo aplica en una base existente los mismos cambios que
--   agrega schema.sql, sin perder datos.
--
-- Como se aplica
--   docker exec -i tgs_db mysql -uroot -p<password> <base> < db/migracion.sql
--   (la base y el password son los del .env del proyecto)
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
--   - La tabla 'pago' se crea con CREATE TABLE IF NOT EXISTS.
--   - Las columnas nuevas se agregan solo si todavia no existen
--     (MySQL no tiene "ADD COLUMN IF NOT EXISTS", por eso se usa el
--     procedimiento temporal que consulta information_schema).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Tabla de cobros
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pago` (
  `id_pago`        INT           NOT NULL AUTO_INCREMENT,
  `id_reserva`     INT           NOT NULL,
  `monto`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `metodo`         VARCHAR(30)   NOT NULL DEFAULT 'efectivo',
  `estado`         VARCHAR(20)   NOT NULL DEFAULT 'pagado',
  `referencia`     VARCHAR(60)   DEFAULT NULL,
  `fecha_pago`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `registrado_por` VARCHAR(20)   DEFAULT NULL,
  `notas`          VARCHAR(255)  DEFAULT NULL,
  PRIMARY KEY (`id_pago`),
  KEY `pago_reserva_idx` (`id_reserva`),
  KEY `pago_estado_idx` (`estado`),
  CONSTRAINT `pago_fk_reserva`
    FOREIGN KEY (`id_reserva`) REFERENCES `reserva` (`id_reserva`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Columnas nuevas en la tabla reserva
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `tgs_agregar_columna`;

DELIMITER $$
CREATE PROCEDURE `tgs_agregar_columna`(
  IN p_tabla VARCHAR(64),
  IN p_columna VARCHAR(64),
  IN p_definicion TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_tabla
      AND COLUMN_NAME = p_columna
  ) THEN
    SET @sql_tgs = CONCAT('ALTER TABLE `', p_tabla, '` ADD COLUMN `', p_columna, '` ', p_definicion);
    PREPARE stmt_tgs FROM @sql_tgs;
    EXECUTE stmt_tgs;
    DEALLOCATE PREPARE stmt_tgs;
  END IF;
END$$
DELIMITER ;

CALL `tgs_agregar_columna`('reserva', 'precio_total', 'DECIMAL(10,2) DEFAULT NULL');
CALL `tgs_agregar_columna`('reserva', 'estado_pago', "VARCHAR(20) NOT NULL DEFAULT 'pendiente'");

DROP PROCEDURE IF EXISTS `tgs_agregar_columna`;

-- ---------------------------------------------------------------------
-- 3. Indice y backfill
-- ---------------------------------------------------------------------
-- El indice de estado_pago ayuda a filtrar las reservas por situacion
-- de cobro. MySQL no tiene "CREATE INDEX IF NOT EXISTS", asi que se
-- tolerate el error 1061 (ya existe) con un IF IGNORE no disponible:
-- se consulta information_schema y solo se crea si falta.
SET @indice_existe = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reserva'
    AND INDEX_NAME = 'reserva_estado_pago_idx'
);
SET @sql_indice = IF(
  @indice_existe = 0,
  'ALTER TABLE `reserva` ADD KEY `reserva_estado_pago_idx` (`estado_pago`)',
  'DO 0'
);
PREPARE stmt_indice FROM @sql_indice;
EXECUTE stmt_indice;
DEALLOCATE PREPARE stmt_indice;

-- Las reservas anteriores no tienen precio congelado: se completa con el
-- precio actual del servicio para que el saldo a cobrar no quede en NULL.
UPDATE `reserva` r
  JOIN `servicio` s ON s.`id_servicio` = r.`id_servicio`
  SET r.`precio_total` = s.`precio`
  WHERE r.`precio_total` IS NULL;

-- Las reservas que ya tienen cobros quedan como 'pagado' o 'parcial'
-- segun corresponda. Primero se anulan los pagos que estaban anulados.
UPDATE `reserva` r
  JOIN (
    SELECT `id_reserva`,
           SUM(CASE WHEN `estado` = 'pagado' THEN `monto` ELSE 0 END) AS total_pagado
    FROM `pago`
    GROUP BY `id_reserva`
  ) p ON p.`id_reserva` = r.`id_reserva`
  SET r.`estado_pago` = CASE
        WHEN p.total_pagado <= 0 THEN 'pendiente'
        WHEN r.`precio_total` IS NOT NULL AND p.total_pagado >= r.`precio_total` THEN 'pagado'
        ELSE 'parcial'
      END;

-- ---------------------------------------------------------------------
-- 4. Modulo de presupuestos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `presupuesto` (
  `id_presupuesto`   INT           NOT NULL AUTO_INCREMENT,
  `id_reserva`       INT           NOT NULL,
  `cedula_usuario`   VARCHAR(20)   NOT NULL,
  `fecha_presupuesto` DATE         NOT NULL,
  `fecha_vencimiento` DATE         DEFAULT NULL,
  `total`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado`           VARCHAR(20)   NOT NULL DEFAULT 'pendiente',
  `observaciones`    VARCHAR(255)  DEFAULT NULL,
  `fecha_respuesta`  DATETIME      DEFAULT NULL,
  `respondido_por`   VARCHAR(20)   DEFAULT NULL,
  `creado_por`       VARCHAR(20)   DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_presupuesto`),
  KEY `presupuesto_reserva_idx` (`id_reserva`),
  KEY `presupuesto_usuario_idx` (`cedula_usuario`),
  KEY `presupuesto_estado_idx` (`estado`),
  CONSTRAINT `presupuesto_fk_reserva`
    FOREIGN KEY (`id_reserva`) REFERENCES `reserva` (`id_reserva`)
    ON DELETE CASCADE,
  CONSTRAINT `presupuesto_fk_usuario`
    FOREIGN KEY (`cedula_usuario`) REFERENCES `usuario` (`cedula`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `presupuesto_detalle` (
  `id_detalle`      INT           NOT NULL AUTO_INCREMENT,
  `id_presupuesto`  INT           NOT NULL,
  `descripcion`     VARCHAR(255)  NOT NULL,
  `cantidad`        INT           NOT NULL DEFAULT 1,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_detalle`),
  KEY `detalle_presupuesto_idx` (`id_presupuesto`),
  CONSTRAINT `detalle_fk_presupuesto`
    FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuesto` (`id_presupuesto`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Permisos configurables por rol (panel de administracion)
-- ---------------------------------------------------------------------
-- La tabla arranca VACIA a proposito: mientras no haya filas, cada rol
-- usa los valores por defecto que declara tgs_matriz_permisos() en
-- auth.php. Solo se guardan los permisos que el administrador cambia
-- desde el panel, y para volver al valor por defecto alcanza con
-- borrar la fila (el boton "Restaurar" de permisos.php).
CREATE TABLE IF NOT EXISTS `permiso_rol` (
  `rol`        VARCHAR(50) NOT NULL,
  `permiso`    VARCHAR(80) NOT NULL,
  `activo`     TINYINT(1)  NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rol`, `permiso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Notificaciones (avisos al cliente)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notificacion` (
  `id_notificacion`     INT          NOT NULL AUTO_INCREMENT,
  `id_reserva`          INT          DEFAULT NULL,
  `cedula_usuario`      VARCHAR(20)  DEFAULT NULL,
  `email_destino`       VARCHAR(150) NOT NULL,
  `asunto`              VARCHAR(200) NOT NULL,
  `cuerpo`              TEXT         NOT NULL,
  `tipo`                VARCHAR(40)  NOT NULL DEFAULT 'general',
  `estado`              VARCHAR(20)  NOT NULL DEFAULT 'pendiente',
  `respuesta_transporte` VARCHAR(250) DEFAULT NULL,
  `intentos`            INT          NOT NULL DEFAULT 0,
  `fecha_envio`         DATETIME     DEFAULT NULL,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notificacion`),
  KEY `notificacion_reserva_idx` (`id_reserva`),
  KEY `notificacion_estado_idx` (`estado`),
  CONSTRAINT `notificacion_fk_reserva`
    FOREIGN KEY (`id_reserva`) REFERENCES `reserva` (`id_reserva`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Disponibilidad de los talleres
-- ----------------------------------------------------------------------- Horarios de atencion por sede y por dia de la semana.
CREATE TABLE IF NOT EXISTS `taller_horario` (
  `id_taller`   INT         NOT NULL,
  `dia_semana`  TINYINT     NOT NULL,
  `hora_inicio` TIME        NOT NULL,
  `hora_fin`    TIME        NOT NULL,
  `activo`      TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_taller`, `dia_semana`),
  KEY `horario_dia_idx` (`dia_semana`),
  CONSTRAINT `horario_fk_taller`
    FOREIGN KEY (`id_taller`) REFERENCES `taller` (`id_taller`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columna de capacidad (reservas simultaneas por franja horaria).
DROP PROCEDURE IF EXISTS `tgs_agregar_columna`;

DELIMITER $$
CREATE PROCEDURE `tgs_agregar_columna`(
  IN p_tabla VARCHAR(64),
  IN p_columna VARCHAR(64),
  IN p_definicion TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_tabla
      AND COLUMN_NAME = p_columna
  ) THEN
    SET @sql_tgs = CONCAT('ALTER TABLE `', p_tabla, '` ADD COLUMN `', p_columna, '` ', p_definicion);
    PREPARE stmt_tgs FROM @sql_tgs;
    EXECUTE stmt_tgs;
    DEALLOCATE PREPARE stmt_tgs;
  END IF;
END$$
DELIMITER ;

CALL `tgs_agregar_columna`('taller', 'capacidad', 'INT NOT NULL DEFAULT 2');

DROP PROCEDURE IF EXISTS `tgs_agregar_columna`;

-- Horario por defecto de los talleres que ya estan cargados: de lunes a
-- sabado, de 08:30 a 18:30 (el mismo horario que anuncia el sitio).
INSERT IGNORE INTO `taller_horario` (`id_taller`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`)
SELECT t.`id_taller`, d.`dia`, '08:30:00', '18:30:00', 1
FROM `taller` t
CROSS JOIN (SELECT 1 AS `dia` UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d;

-- ---------------------------------------------------------------------
-- 8. Cuentas bancarias para el pago del cliente
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `datos_pago` (
  `id_datos_pago` INT          NOT NULL AUTO_INCREMENT,
  `nombre`       VARCHAR(100) NOT NULL,
  `alias`        VARCHAR(60)  NOT NULL,
  `cbu`          VARCHAR(34)  DEFAULT NULL,
  `qr`           VARCHAR(500) DEFAULT NULL,
  `notas`        VARCHAR(255) DEFAULT NULL,
  `activo`       TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_en`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_datos_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuenta de demostracion, para que el cliente vea el flujo completo.
INSERT IGNORE INTO `datos_pago` (`id_datos_pago`, `nombre`, `alias`, `cbu`, `qr`, `notas`, `activo`)
  VALUES (1, 'TGS - Cuenta de demostracion', 'TGS.DEMO.ALIAS', '0000000000000000000000',
  'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=TGS.DEMO.ALIAS',
  'Cuenta de ejemplo. Reemplazar alias, CBU y QR por los datos reales.', 1);
