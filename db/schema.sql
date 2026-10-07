-- =====================================================================
-- TGS - Taller Global de Servicios
-- Esquema de la base de datos
--
-- Este archivo lo carga el contenedor de MySQL automaticamente en
-- /docker-entrypoint-initdb.d la PRIMERA vez que se crea el volumen.
-- Si el volumen ya tiene datos, MySQL lo ignora (eso es lo esperado:
-- en ese caso el restore es el que manda).
--
-- NOTA SOBRE EL ESQUEMA
-- Antes coexistian dos tablas de servicios: "servicio" (la que usa todo
-- el codigo) y "servicios" (la vieja, con las columnas nombre_servicio y
-- costo). La clave foranea de "reserva" apuntaba por error a la vieja,
-- por lo que un servicio creado desde el panel no podia reservarse.
-- Aqui queda una sola tabla "servicio" y la FK correctamente apuntada.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `reserva_historial`;
DROP TABLE IF EXISTS `reserva`;
DROP TABLE IF EXISTS `vehiculo`;
DROP TABLE IF EXISTS `taller`;
DROP TABLE IF EXISTS `servicio`;
DROP TABLE IF EXISTS `usuario`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- usuario: cuenta de acceso al sistema, con rol admin/gestor/cliente
-- La cedula es la clave primaria, como en el modelo original.
-- ---------------------------------------------------------------------
CREATE TABLE `usuario` (
  `cedula`   VARCHAR(20)  NOT NULL,
  `nombre`   VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) DEFAULT NULL,
  `email`    VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `telefono` VARCHAR(30)  DEFAULT NULL,
  `rol`      VARCHAR(50)  NOT NULL DEFAULT 'cliente',
  PRIMARY KEY (`cedula`),
  UNIQUE KEY `usuario_email_unico` (`email`),
  KEY `usuario_rol_idx` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- taller: sedes donde se realiza el servicio
-- ---------------------------------------------------------------------
CREATE TABLE `taller` (
  `id_taller` INT         NOT NULL AUTO_INCREMENT,
  `nombre`    VARCHAR(100) NOT NULL,
  `direccion` VARCHAR(200) NOT NULL,
  `mapa`      TEXT         DEFAULT NULL,
  `telefono`  VARCHAR(30)  DEFAULT NULL,
  `email`     VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id_taller`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- servicio: catalogo de servicios ofrecidos
-- ---------------------------------------------------------------------
CREATE TABLE `servicio` (
  `id_servicio`  INT           NOT NULL AUTO_INCREMENT,
  `nombre`       VARCHAR(120)  NOT NULL,
  `descripcion`  VARCHAR(255)  DEFAULT NULL,
  `precio`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `activo`       TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_servicio`),
  KEY `servicio_activo_idx` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- vehiculo: autos registrados por cada cliente
-- ---------------------------------------------------------------------
CREATE TABLE `vehiculo` (
  `id_vehiculo`    INT         NOT NULL AUTO_INCREMENT,
  `cedula_usuario` VARCHAR(20) NOT NULL,
  `patente`        VARCHAR(20) NOT NULL,
  `marca`          VARCHAR(50) NOT NULL,
  `modelo`         VARCHAR(50) NOT NULL,
  `anio`           INT         DEFAULT NULL,
  PRIMARY KEY (`id_vehiculo`),
  KEY `vehiculo_usuario_idx` (`cedula_usuario`),
  CONSTRAINT `vehiculo_fk_usuario`
    FOREIGN KEY (`cedula_usuario`) REFERENCES `usuario` (`cedula`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reserva: turno reservado por un cliente
-- La FK a servicio ahora apunta a la tabla que realmente usa el codigo.
-- ---------------------------------------------------------------------
CREATE TABLE `reserva` (
  `id_reserva`     INT         NOT NULL AUTO_INCREMENT,
  `cedula_usuario` VARCHAR(20) NOT NULL,
  `id_vehiculo`    INT         NOT NULL,
  `id_taller`      INT         NOT NULL,
  `id_servicio`    INT         NOT NULL,
  `fecha_reserva`  DATE        NOT NULL,
  `hora_reserva`   TIME        NOT NULL,
  `estado`         VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
  PRIMARY KEY (`id_reserva`),
  KEY `reserva_usuario_idx` (`cedula_usuario`),
  KEY `reserva_vehiculo_idx` (`id_vehiculo`),
  KEY `reserva_taller_idx`   (`id_taller`),
  KEY `reserva_servicio_idx` (`id_servicio`),
  CONSTRAINT `reserva_fk_usuario`
    FOREIGN KEY (`cedula_usuario`) REFERENCES `usuario` (`cedula`)
    ON DELETE CASCADE,
  CONSTRAINT `reserva_fk_vehiculo`
    FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculo` (`id_vehiculo`)
    ON DELETE CASCADE,
  CONSTRAINT `reserva_fk_taller`
    FOREIGN KEY (`id_taller`) REFERENCES `taller` (`id_taller`)
    ON DELETE CASCADE,
  CONSTRAINT `reserva_fk_servicio`
    FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reserva_historial: auditoria de cambios de estado y reprogramaciones
-- ---------------------------------------------------------------------
CREATE TABLE `reserva_historial` (
  `id_historial`     INT          NOT NULL AUTO_INCREMENT,
  `id_reserva`       INT          NOT NULL,
  `campo`            VARCHAR(50)  NOT NULL,
  `valor_anterior`   TEXT         DEFAULT NULL,
  `valor_nuevo`      TEXT         DEFAULT NULL,
  `fecha_cambio`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario`          VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id_historial`),
  KEY `historial_reserva_idx` (`id_reserva`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
