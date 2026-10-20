<?php
/**
 * TGS - Taller Global de Servicios
 * Conexion PDO a la base de datos.
 *
 * Antes este archivo traia usuario y contrasena escritos a mano, lo que
 * hacia imposible desplegar el proyecto en otro equipo sin editar codigo.
 * Ahora la configuracion sale de .env y .env.local a traves de config.php.
 */

require_once __DIR__ . '/config.php';

// Enlace PDO con los parametros resueltos desde el entorno.
$conexion = tgs_pdo();

/**
 * El campo telefono se agrego despues del primer despliegue, asi que
 * se comprueba antes de intentar crearlo. Es idempotente: el ALTER
 * solo llega a ejecutarse una vez, en la primera peticion.
 */
try {
    $columna_telefono = $conexion->query("SHOW COLUMNS FROM usuario LIKE 'telefono'");

    if (!$columna_telefono->fetch(PDO::FETCH_ASSOC)) {
        $conexion->exec('ALTER TABLE usuario ADD COLUMN telefono VARCHAR(30) NULL');
    }
} catch (PDOException $e) {
    // Si la tabla usuario aun no existe (base recien creada) no es un
    // error: el esquema definitivo llega desde db/schema.sql.
}

/**
 * Modulo de pago y facturacion: deja el esquema listo para que las
 * paginas funcionen aunque la base todavia no tenga la migracion aplicada
 * (db/migracion.sql). Es idempotente, como la comprobacion de telefono:
 * la tabla se crea con IF NOT EXISTS y las columnas de la reserva solo
 * se agregan si no existen.
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS pago (
        id_pago INT AUTO_INCREMENT PRIMARY KEY,
        id_reserva INT NOT NULL,
        monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        metodo VARCHAR(30) NOT NULL DEFAULT 'efectivo',
        estado VARCHAR(20) NOT NULL DEFAULT 'pagado',
        referencia VARCHAR(60) NULL,
        fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        registrado_por VARCHAR(20) NULL,
        notas VARCHAR(255) NULL,
        KEY pago_reserva_idx (id_reserva),
        KEY pago_estado_idx (estado),
        CONSTRAINT pago_fk_reserva FOREIGN KEY (id_reserva) REFERENCES reserva (id_reserva) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Columnas de la reserva: precio congelado y estado del cobro.
    $columna_precio_total = $conexion->query("SHOW COLUMNS FROM reserva LIKE 'precio_total'");
    if (!$columna_precio_total->fetch(PDO::FETCH_ASSOC)) {
        $conexion->exec('ALTER TABLE reserva ADD COLUMN precio_total DECIMAL(10,2) NULL');
    }

    $columna_estado_pago = $conexion->query("SHOW COLUMNS FROM reserva LIKE 'estado_pago'");
    if (!$columna_estado_pago->fetch(PDO::FETCH_ASSOC)) {
        $conexion->exec("ALTER TABLE reserva ADD COLUMN estado_pago VARCHAR(20) NOT NULL DEFAULT 'pendiente'");
    }
} catch (PDOException $e) {
    // Si la tabla reserva aun no existe (base recien creada) no es un
    // error: el esquema definitivo llega desde db/schema.sql.
}

/**
 * Modulo de presupuestos: la propuesta de trabajo que el taller envia al
 * cliente. Misma logica idempotente que el modulo de pago: las tablas se
 * crean con IF NOT EXISTS y no se tocan los datos existentes.
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS presupuesto (
        id_presupuesto INT AUTO_INCREMENT PRIMARY KEY,
        id_reserva INT NOT NULL,
        cedula_usuario VARCHAR(20) NOT NULL,
        fecha_presupuesto DATE NOT NULL,
        fecha_vencimiento DATE NULL,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
        observaciones VARCHAR(255) NULL,
        fecha_respuesta DATETIME NULL,
        respondido_por VARCHAR(20) NULL,
        creado_por VARCHAR(20) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY presupuesto_reserva_idx (id_reserva),
        KEY presupuesto_usuario_idx (cedula_usuario),
        KEY presupuesto_estado_idx (estado),
        CONSTRAINT presupuesto_fk_reserva FOREIGN KEY (id_reserva) REFERENCES reserva (id_reserva) ON DELETE CASCADE,
        CONSTRAINT presupuesto_fk_usuario FOREIGN KEY (cedula_usuario) REFERENCES usuario (cedula) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conexion->exec("CREATE TABLE IF NOT EXISTS presupuesto_detalle (
        id_detalle INT AUTO_INCREMENT PRIMARY KEY,
        id_presupuesto INT NOT NULL,
        descripcion VARCHAR(255) NOT NULL,
        cantidad INT NOT NULL DEFAULT 1,
        precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        KEY detalle_presupuesto_idx (id_presupuesto),
        CONSTRAINT detalle_fk_presupuesto FOREIGN KEY (id_presupuesto) REFERENCES presupuesto (id_presupuesto) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Si las tablas base aun no existen (base recien creada) no es un
    // error: el esquema definitivo llega desde db/schema.sql.
}

/**
 * Permisos por rol configurables desde el panel: guarda los cambios que
 * el administrador hace en permisos.php. Una fila (rol, permiso) con
 * activo = 0 deja el permiso deshabilitado para ese rol; si no hay fila,
 * se usa el valor por defecto de la matriz del codigo (auth.php).
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS permiso_rol (
        rol VARCHAR(50) NOT NULL,
        permiso VARCHAR(80) NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (rol, permiso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Sin tabla de permisos el sitio sigue con los valores por defecto.
}

/**
 * Avisos al cliente: se registran siempre, se envian si hay transporte de
 * correo configurado y quedan pendientes si no lo hay.
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS notificacion (
        id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
        id_reserva INT NULL,
        cedula_usuario VARCHAR(20) NULL,
        email_destino VARCHAR(150) NOT NULL,
        asunto VARCHAR(200) NOT NULL,
        cuerpo TEXT NOT NULL,
        tipo VARCHAR(40) NOT NULL DEFAULT 'general',
        estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
        respuesta_transporte VARCHAR(250) NULL,
        intentos INT NOT NULL DEFAULT 0,
        fecha_envio DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY notificacion_reserva_idx (id_reserva),
        KEY notificacion_estado_idx (estado),
        CONSTRAINT notificacion_fk_reserva FOREIGN KEY (id_reserva) REFERENCES reserva (id_reserva) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Sin la tabla de notificaciones el resto del sitio funciona igual.
}

/**
 * Disponibilidad de los talleres: horarios de atencion por dia de la semana
 * y capacidad de reservas simultaneas por sede.
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS taller_horario (
        id_taller INT NOT NULL,
        dia_semana TINYINT NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id_taller, dia_semana),
        KEY horario_dia_idx (dia_semana),
        CONSTRAINT horario_fk_taller FOREIGN KEY (id_taller) REFERENCES taller (id_taller) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columna_capacidad = $conexion->query("SHOW COLUMNS FROM taller LIKE 'capacidad'");
    if (!$columna_capacidad->fetch(PDO::FETCH_ASSOC)) {
        $conexion->exec('ALTER TABLE taller ADD COLUMN capacidad INT NOT NULL DEFAULT 2');
    }
} catch (PDOException $e) {
    // Si la tabla reserva aun no existe (base recien creada) el esquema
    // definitivo llega desde db/schema.sql.
}

/**
 * Cuentas bancarias para que el cliente pueda transferir y reportar el
 * pago desde su pantalla.
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS datos_pago (
        id_datos_pago INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        alias VARCHAR(60) NOT NULL,
        cbu VARCHAR(34) NULL,
        qr VARCHAR(500) NULL,
        notas VARCHAR(255) NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Si la tabla no se puede crear, el sitio sigue funcionando igual.
}

/**
 * Factura electronica: comprobante descargable que se emite al cliente
 * cuando un pago queda concretado.
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS factura (
        id_factura INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(30) NOT NULL,
        id_reserva INT NOT NULL,
        cedula_usuario VARCHAR(20) NOT NULL,
        fecha_emision DATE NOT NULL,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        estado VARCHAR(20) NOT NULL DEFAULT 'emitida',
        forma_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
        notas VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY factura_numero_unico (numero),
        KEY factura_reserva_idx (id_reserva),
        KEY factura_usuario_idx (cedula_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Si la tabla no se puede crear, el sitio sigue funcionando igual.
}

/**
 * Filtros de talleres: marcas que atiende cada sede y tipos de servicio
 * que ofrece. Son tablas puente porque una sede atiende varias marcas y
 * ofrece varios servicios (relacion N:M).
 */
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS taller_marca (
        id_taller INT NOT NULL,
        marca VARCHAR(50) NOT NULL,
        PRIMARY KEY (id_taller, marca),
        KEY taller_marca_idx (marca)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $conexion->exec("CREATE TABLE IF NOT EXISTS taller_servicio (
        id_taller INT NOT NULL,
        id_servicio INT NOT NULL,
        PRIMARY KEY (id_taller, id_servicio),
        KEY taller_servicio_servicio_idx (id_servicio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Si las tablas no se pueden crear, el sitio sigue funcionando igual.
}

/**
 * Saldo de la cuenta prepaga del cliente: le permite pagar directamente
 * desde la aplicacion, sin pasar por el taller.
 */
try {
    $columna_prepago = $conexion->query("SHOW COLUMNS FROM usuario LIKE 'saldo_prepago'");
    if (!$columna_prepago->fetch(PDO::FETCH_ASSOC)) {
        $conexion->exec('ALTER TABLE usuario ADD COLUMN saldo_prepago DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    }
} catch (PDOException $e) {
    // Si la tabla usuario aun no existe (base recien creada) el esquema
    // definitivo llega desde db/schema.sql.
}
