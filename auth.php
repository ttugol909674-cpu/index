<?php
/**
 * TGS - Taller Global de Servicios
 * Punto central de autenticación y autorización por rol.
 *
 * Este archivo se incluye en todas las paginas privadas. Responsabilidades:
 *  - Iniciar la sesion PHP si no existe, con cookies reforzadas.
 *  - Normalizar los nombres de rol que vienen de la base de datos.
 *  - Verificar que el usuario este logueado y tenga el rol permitido
 *    para la pagina que quiere abrir, redirigiendo a login o a error_403.
 *
 * Convencion de uso: en cada pagina privada se llama a
 *   verificarPermiso('administrar_usuarios')  para pedir un permiso concreto, o
 *   verificarRol('admin')                     para un solo rol, o
 *   verificarRol(['admin','gestor'])          para varios.
 *
 * La matriz de permisos (tgs_matriz_permisos) es la fuente unica de la
 * autorizacion: cada permiso declara que roles lo tienen. Las paginas
 * nuevas deben pedir permisos, no roles, para que cambiar un permiso sea
 * un cambio en un solo lugar.
 */
if (session_status() === PHP_SESSION_NONE) {
    # httponly y SameSite=Lax reducen el robo de sesion (XSS y CSRF
    # basico); secure solo se activa si hay HTTPS, porque en localhost
    # HTTP hay que poder loguearse igual.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
}

# Se carga la configuracion para poder leer los permisos configurados en la
# base de datos. tgs_pdo() es perezoso y reutiliza la misma conexion que
# usa conexion.php, asi que no se abre un enlace extra.
require_once __DIR__ . '/config.php';

# Normaliza los nombres de rol de la base de datos a los identificadores
# internos ('admin', 'gestor', 'cliente'). La base puede guardar variantes
# ("administrador", "Gestor de taller", "customer"), y el resto del codigo
# compara siempre contra el identificador normalizado.
function normalizarRol($rol)
{
    $rol = strtolower(trim((string) $rol));
    $equivalencias = [
        'administrador' => 'admin',
        'administrator' => 'admin',
        'admin' => 'admin',
        'gestor de taller' => 'gestor',
        'manager' => 'gestor',
        'gestor' => 'gestor',
        'cliente' => 'cliente',
        'customer' => 'cliente'
    ];

    return $equivalencias[$rol] ?? $rol;
}

# Genera un token CSRF para la sesion actual y lo devuelve. Si ya existe,
# se reutiliza el mismo valor.
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

# Valida el token CSRF recibido en un POST. Compara en tiempo constante
# para no filtrar informacion por el tiempo de respuesta.
function validarCsrf($tokenRecibido)
{
    if (empty($_SESSION['csrf_token']) || !is_string($tokenRecibido) || $tokenRecibido === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $tokenRecibido);
}

# Matriz de permisos por rol: fuente unica de la autorizacion del sitio.
# Cada clave es un permiso y el valor son los roles que lo tienen.
# Roles validos: 'cliente', 'gestor', 'admin'.
#
# Esta matriz es el CATALOGO de permisos y los valores por defecto. Desde
# el panel de administracion (permisos.php) se pueden cambiar los permisos
# de cada rol: esos cambios se guardan en la tabla 'permiso_rol' y tienen
# prioridad sobre los valores de aqui. Un permiso sin fila en la tabla usa
# el valor por defecto de esta matriz.
function tgs_matriz_permisos(): array
{
    return [
        # Paneles
        'ver_panel_admin'   => ['admin'],
        'ver_panel_gestor'  => ['gestor', 'admin'],
        'ver_panel_cliente' => ['cliente'],
        # Turnos del propio usuario
        'ver_turnos_propios'    => ['cliente', 'gestor', 'admin'],
        'crear_turno'           => ['cliente', 'gestor', 'admin'],
        'editar_turno_propio'   => ['cliente', 'gestor', 'admin'],
        'cancelar_turno_propio' => ['cliente', 'gestor', 'admin'],
        'gestionar_vehiculos'   => ['cliente', 'gestor', 'admin'],
        # Turnos de todos los usuarios (paneles de operacion)
        'administrar_turnos' => ['gestor', 'admin'],
        # Perfil
        'editar_perfil' => ['cliente', 'gestor', 'admin'],
        # Baja de cuenta: solo el cliente puede dar de baja su propia cuenta.
        'eliminar_cuenta' => ['cliente'],
        # Presupuestos
        'ver_presupuestos_propios'  => ['cliente', 'gestor', 'admin'],
        'responder_presupuesto'     => ['cliente', 'gestor', 'admin'],
        'administrar_presupuestos'  => ['gestor', 'admin'],
        # Pagos y facturacion
        'administrar_pagos' => ['gestor', 'admin'],
        # Talleres
        'ver_talleres'         => ['cliente', 'gestor', 'admin'],
        'administrar_talleres' => ['gestor', 'admin'],
        'crear_taller'         => ['gestor', 'admin'],
        'editar_taller'        => ['gestor', 'admin'],
        'eliminar_taller'      => ['gestor', 'admin'],
        # Servicios
        'administrar_servicios' => ['gestor', 'admin'],
        # Usuarios
        'administrar_usuarios' => ['admin'],
        'crear_usuarios'       => ['admin'],
        'editar_usuarios'      => ['admin'],
        'eliminar_usuarios'    => ['admin'],
        'cambiar_roles'        => ['admin'],
        # Permisos (ver la matriz) y gestion de la sesion
        'ver_matriz_permisos' => ['admin'],
        'editar_matriz_permisos' => ['admin'],
        'cambiar_estado_reserva' => ['gestor', 'admin']
    ];
}

# Permisos que el rol admin no puede deshabilitar: hacen falta para poder
# seguir administrando el sistema (y entrar a esta misma pantalla).
function tgs_permisos_protegidos(): array
{
    return ['ver_panel_admin', 'ver_matriz_permisos', 'editar_matriz_permisos'];
}

# Rol normalizado del usuario de la sesion actual.
function rolActual(): string
{
    return normalizarRol($_SESSION['rol'] ?? '');
}

# Permisos modificados desde el panel de administracion.
# Devuelve [rol][permiso] => true/false con lo que se guardo en la tabla
# 'permiso_rol'. Si la base no esta disponible, devuelve un arreglo vacio
# y se siguen usando los valores por defecto de la matriz del codigo.
function tgs_permisos_modificados(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $pdo = null;
        if (isset($GLOBALS['conexion']) && $GLOBALS['conexion'] instanceof PDO) {
            $pdo = $GLOBALS['conexion'];
        } elseif (function_exists('tgs_pdo')) {
            # verificarPermiso() se ejecuta antes de que las paginas carguen
            # conexion.php, asi que aqui se abre (o reutiliza) el enlace.
            $pdo = tgs_pdo();
        }

        if ($pdo instanceof PDO) {
            $stmt = $pdo->query('SELECT rol, permiso, activo FROM permiso_rol');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $cache[$fila['rol']][$fila['permiso']] = ((int) $fila['activo'] === 1);
            }
        }
    } catch (Throwable $e) {
        // Sin base de datos no se pueden leer los cambios: se usan los
        // valores por defecto y el sitio sigue funcionando.
        $cache = [];
    }

    return $cache;
}

# Indica si el rol indicado (o el de la sesion) tiene el permiso pedido.
# Prioridad: lo configurado en el panel (tabla permiso_rol) y, si no hay
# configuracion para ese par rol/permiso, el valor por defecto del codigo.
# Un permiso que no existe en el catalogo se considera denegado.
function tienePermiso(string $permiso, ?string $rol = null): bool
{
    $matriz = tgs_matriz_permisos();
    if (!isset($matriz[$permiso])) {
        return false;
    }
    $rol = ($rol === null || $rol === '') ? rolActual() : normalizarRol($rol);

    $modificados = tgs_permisos_modificados();
    if (isset($modificados[$rol][$permiso])) {
        return $modificados[$rol][$permiso];
    }

    return in_array($rol, $matriz[$permiso], true);
}

# Exige que exista una sesion iniciada. Redirige al login si no la hay.
function exigirSesion(): void
{
    if (!isset($_SESSION['cedula']) && !isset($_SESSION['id_usuario'])) {
        header("Location: login.php?error=acceso_denegado");
        exit();
    }
}

# Protege una pagina exigiendo un permiso concreto de la matriz.
# Sin sesion manda al login; con sesion pero sin permiso, a error_403.
function verificarPermiso(string $permiso): void
{
    exigirSesion();
    if (!tienePermiso($permiso)) {
        header("Location: error_403.php");
        exit();
    }
}

# Función para proteger páginas según el rol del usuario
# Ejemplo: verificarRol('admin') o verificarRol(['admin','gestor'])
function verificarRol($rolesPermitidos) {
    # Este método es la forma antigua de autorizar (por rol). Se mantiene
    # por compatibilidad, pero las paginas nuevas deben usar
    # verificarPermiso() con la matriz de permisos.
    exigirSesion();

    # Si el parámetro recibido no es un arreglo, lo convierto en uno
    # para que funcione igual tanto con un valor como con varios
    if (!is_array($rolesPermitidos)) {
        $rolesPermitidos = [$rolesPermitidos];
    }

    # Obtiene el rol actual del usuario desde la sesión y lo convierte a minúsculas
    $rolActual = rolActual();

    # Convierte todos los roles permitidos también a minúsculas
    # para evitar errores por mayúsculas/minúsculas
    $rolesPermitidos = array_map('strtolower', $rolesPermitidos);

    # Si el rol del usuario no está dentro de los permitidos, lo bloquea
    if (!in_array($rolActual, $rolesPermitidos, true)) {
        # Redirige a una página de acceso prohibido
        header("Location: error_403.php");
        exit();
    }
}
?>
