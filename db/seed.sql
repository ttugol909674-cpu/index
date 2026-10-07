-- =====================================================================
-- TGS - Taller Global de Servicios
-- Datos iniciales del sistema
--
-- Se carga automaticamente despues de db/schema.sql en la primera
-- creacion del contenedor. Usa INSERT IGNORE para que pueda ejecutarse
-- mas de una vez sin duplicar filas.
--
-- CONTRASENAS DE DEMOSTRACION
--   admin@bluetech.com      Admin123!
--   santiiuyy12@gmail.com   Gestor123!
--   carlos.gomez@gmail.com  Cliente123!
--   mariana.silva@gmail.com Cliente123!
--   bysantiiuyy@gmail.com   Cliente123!
-- Son contrasenas de prueba para el video: cambialas antes de usar el
-- sistema con datos reales.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Sede
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `taller` (`id_taller`, `nombre`, `direccion`, `telefono`, `email`, `mapa`) VALUES
(1, 'TGS Centro', 'Av. Italia 2485, Montevideo', '099 123 456', 'contacto@tgs.com.uy',
 '<iframe src="https://www.google.com/maps?q=Av.+Italia+2485+Montevideo&output=embed" width="100%" height="220" style="border:0" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>'),
(2, 'TGS Maldonado', 'Peatonal Saravia 505, Maldonado', '099 654 321', 'maldonado@tgs.com.uy',
 '<iframe src="https://www.google.com/maps?q=Peatonal+Saravia+505+Maldonado&output=embed" width="100%" height="220" style="border:0" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>');

-- ---------------------------------------------------------------------
-- Catalogo de servicios
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `servicio` (`id_servicio`, `nombre`, `descripcion`, `precio`, `activo`) VALUES
(1, 'Mantenimiento preventivo', 'Aceite, filtros, fluidos y revision general', 2500.00, 1),
(2, 'Diagnostico computarizado', 'Revision electronica y deteccion de fallas', 1800.00, 1),
(3, 'Mecanica general', 'Frenos, suspension, transmision y reparaciones', 3500.00, 1);

-- ---------------------------------------------------------------------
-- Usuarios: 1 administrador, 1 gestor y 3 clientes
-- El campo password guarda el hash bcrypt, nunca la contrasena en claro.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `usuario` (`cedula`, `nombre`, `apellido`, `email`, `telefono`, `rol`, `password`) VALUES
('00000000',   'Administrador', 'TGS',       'admin@bluetech.com',      '099000000', 'admin',
 '$2y$12$1MygGXTRRX7Y0ibfCTpZvuVwJahSwWNKZ5i9.4ZUkqD5idYSgj0YW'),
('699542442', 'Juan',           'Cruz',      'santiiuyy12@gmail.com',   NULL,        'gestor',
 '$2y$12$kUG3GPTM4JJ2qu26iODJMOsHsfvjEb3KYP3Q89eS3rTp5JtiTcEwu'),
('11111111',  'Carlos',         'Gomez',     'carlos.gomez@gmail.com',  '099123456', 'cliente',
 '$2y$12$B3rhYZcMFvkGFRSD2nk3RO0kg6stp/wvq5tpW/wkWjYW7Sg49MOJW'),
('22222222',  'Mariana',        'Silva',     'mariana.silva@gmail.com', '098765432', 'cliente',
 '$2y$12$B3rhYZcMFvkGFRSD2nk3RO0kg6stp/wvq5tpW/wkWjYW7Sg49MOJW'),
('57779741',  'Santiago',       'Figueroa',  'bysantiiuyy@gmail.com',   '096 368 033', 'cliente',
 '$2y$12$B3rhYZcMFvkGFRSD2nk3RO0kg6stp/wvq5tpW/wkWjYW7Sg49MOJW');

-- ---------------------------------------------------------------------
-- Vehiculos de los clientes
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `vehiculo` (`id_vehiculo`, `cedula_usuario`, `patente`, `marca`, `modelo`, `anio`) VALUES
(1, '11111111', 'ABC1234', 'Toyota',    'Corolla', 2021),
(2, '22222222', 'DEF5678', 'Volkswagen','Gol',     2019),
(3, '57779741', 'GHI9012', 'Chevrolet', 'Onix',    2023);

-- ---------------------------------------------------------------------
-- Una reserva de ejemplo, para que el panel no arranque vacio
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `reserva`
  (`id_reserva`, `cedula_usuario`, `id_vehiculo`, `id_taller`, `id_servicio`, `fecha_reserva`, `hora_reserva`, `estado`)
VALUES
(1, '11111111', 1, 1, 1, '2026-10-05', '10:00:00', 'Pendiente');
