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

DROP TABLE IF EXISTS `factura`;
DROP TABLE IF EXISTS `taller_marca`;
DROP TABLE IF EXISTS `taller_servicio`;
DROP TABLE IF EXISTS `datos_pago`;
DROP TABLE IF EXISTS `taller_horario`;
DROP TABLE IF EXISTS `notificacion`;
DROP TABLE IF EXISTS `permiso_rol`;
DROP TABLE IF EXISTS `presupuesto_detalle`;
DROP TABLE IF EXISTS `presupuesto`;
DROP TABLE IF EXISTS `pago`;
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
  -- Saldo de la cuenta prepaga del cliente: es con lo que puede pagar
  -- directamente desde la aplicacion sin pasar por el taller.
  `saldo_prepago` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
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
  -- Cantidad de reservas simultaneas que acepta el taller por franja
  -- horaria. Con 1 el taller trabaja de a un vehiculo por vez.
  `capacidad` INT          NOT NULL DEFAULT 2,
  PRIMARY KEY (`id_taller`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- taller_horario: dias y horas de atencion de cada sede.
-- Un registro por dia de la semana (1 = lunes ... 7 = domingo). Las
-- reservas solo se pueden tomar dentro de la ventana configurada y si
-- la franja todavia tiene capacidad libre.
-- ---------------------------------------------------------------------
CREATE TABLE `taller_horario` (
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
  -- Precio del servicio congelado al momento de reservar: si el catalogo
  -- cambia despues, la reserva mantiene lo que el cliente acepto.
  `precio_total`   DECIMAL(10,2) DEFAULT NULL,
  -- Estado del cobro: pendiente | parcial | pagado
  `estado_pago`    VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id_reserva`),
  KEY `reserva_usuario_idx` (`cedula_usuario`),
  KEY `reserva_vehiculo_idx` (`id_vehiculo`),
  KEY `reserva_taller_idx`   (`id_taller`),
  KEY `reserva_servicio_idx` (`id_servicio`),
  KEY `reserva_estado_pago_idx` (`estado_pago`),
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

-- ---------------------------------------------------------------------
-- pago: cobros asociados a una reserva
-- Cada fila es un cobro (puede haber varios por reserva: pagos parciales).
-- El estado 'anulado' sirve para anular un cobro mal registrado sin
-- borrar el rastro, por eso se marca en lugar de eliminarse.
-- ---------------------------------------------------------------------
CREATE TABLE `pago` (
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
-- presupuesto: propuesta de trabajo y precio que el taller envia al
-- cliente. Nace en estado 'pendiente' y el cliente la acepta o la rechaza.
-- El total se recalcula a partir de presupuesto_detalle.
-- ---------------------------------------------------------------------
CREATE TABLE `presupuesto` (
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

-- ---------------------------------------------------------------------
-- presupuesto_detalle: items del presupuesto (repuestos, mano de obra).
-- Una fila por concepto, con cantidad y precio unitario.
-- ---------------------------------------------------------------------
CREATE TABLE `presupuesto_detalle` (
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
-- permiso_rol: permisos efectivos de cada rol, configurables desde el
-- panel de administracion (permisos.php). Una fila (rol, permiso) con
-- activo = 0 deja ese permiso deshabilitado para el rol. Si no existe
-- fila, se aplica el valor por defecto de la matriz del codigo
-- (tgs_matriz_permisos en auth.php).
-- ---------------------------------------------------------------------
CREATE TABLE `permiso_rol` (
  `rol`        VARCHAR(50) NOT NULL,
  `permiso`    VARCHAR(80) NOT NULL,
  `activo`     TINYINT(1)  NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rol`, `permiso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notificacion: avisos enviados (o pendientes) al cliente.
-- Cada fila es un aviso generado por el sistema, por ejemplo cuando el
-- taller marca una reserva como "Finalizada". El estado indica si el
-- correo salio (enviado), fallo (error) o quedo pendiente de reintento
-- (pendiente, por ejemplo cuando no hay SMTP configurado).
-- ---------------------------------------------------------------------
CREATE TABLE `notificacion` (
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
-- datos_pago: cuentas bancarias para que el cliente pueda transferir.
-- El cliente las ve en su pantalla de pagos, junto al codigo QR (que se
-- guarda como una URL o un fragmento <img>, igual que el mapa de las
-- sedes). Puede haber mas de una cuenta; se muestran las activas.
-- ---------------------------------------------------------------------
CREATE TABLE `datos_pago` (
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

-- ---------------------------------------------------------------------
-- factura: comprobante que se emite al cliente cuando un pago queda
-- concretado. Guarda el numero correlativo, la reserva facturada, el
-- total y la forma de pago, de modo que el documento se pueda volver a
-- descargar sin recalcularlo.
-- ---------------------------------------------------------------------
CREATE TABLE `factura` (
  `id_factura`    INT           NOT NULL AUTO_INCREMENT,
  `numero`        VARCHAR(30)   NOT NULL,
  `id_reserva`    INT           NOT NULL,
  `cedula_usuario` VARCHAR(20)  NOT NULL,
  `fecha_emision` DATE          NOT NULL,
  `total`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado`        VARCHAR(20)   NOT NULL DEFAULT 'emitida',
  `forma_pago`    VARCHAR(30)   NOT NULL DEFAULT 'efectivo',
  `notas`         VARCHAR(255)  DEFAULT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_factura`),
  UNIQUE KEY `factura_numero_unico` (`numero`),
  KEY `factura_reserva_idx` (`id_reserva`),
  KEY `factura_usuario_idx` (`cedula_usuario`),
  CONSTRAINT `factura_fk_reserva`
    FOREIGN KEY (`id_reserva`) REFERENCES `reserva` (`id_reserva`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- taller_marca: marcas de vehiculo que atiende cada sede. Permite
-- filtrar los talleres por marca, como pide el enunciado.
-- ---------------------------------------------------------------------
CREATE TABLE `taller_marca` (
  `id_taller` INT         NOT NULL,
  `marca`     VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id_taller`, `marca`),
  KEY `taller_marca_idx` (`marca`),
  CONSTRAINT `taller_marca_fk_taller`
    FOREIGN KEY (`id_taller`) REFERENCES `taller` (`id_taller`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- taller_servicio: tipos de servicio que ofrece cada sede. Permite
-- filtrar los talleres por tipo de servicio.
-- ---------------------------------------------------------------------
CREATE TABLE `taller_servicio` (
  `id_taller`   INT NOT NULL,
  `id_servicio` INT NOT NULL,
  PRIMARY KEY (`id_taller`, `id_servicio`),
  KEY `taller_servicio_servicio_idx` (`id_servicio`),
  CONSTRAINT `taller_servicio_fk_taller`
    FOREIGN KEY (`id_taller`) REFERENCES `taller` (`id_taller`)
    ON DELETE CASCADE,
  CONSTRAINT `taller_servicio_fk_servicio`
    FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
