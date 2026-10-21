<?php
/**
 * TGS - Taller Global de Servicios
 * Matriz de permisos por rol.
 *
 * Pantalla donde el administrador ve y modifica que permisos tiene cada
 * rol (cliente, gestor, admin).
 *
 * El catalogo de permisos y los valores por defecto viven en auth.php
 * (tgs_matriz_permisos). Lo que se cambia desde esta pantalla se guarda
 * en la tabla 'permiso_rol' y tiene prioridad sobre esos valores:
 *   - una fila (rol, permiso) con activo = 1  -> permiso habilitado
 *   - una fila (rol, permiso) con activo = 0  -> permiso deshabilitado
 *   - sin fila                                 -> valor por defecto
 * "Restaurar" borra la fila y vuelve al valor por defecto.
 *
 * Reglas de negocio:
 * - Los permisos protegidos del rol admin (ver tgs_permisos_protegidos)
 *   no se pueden deshabilitar: hacen falta para administrar el sistema.
 * - Un permiso que no existe en el catalogo no se puede activar.
 *
 * Acceso: solo administradores con el permiso 'editar_matriz_permisos'.
 *
 * Dependencias: auth.php, conexion.php
 */
require_once 'auth.php';
verificarPermiso('editar_matriz_permisos');
require_once 'conexion.php';

// Gestion de idioma. Se guarda en sesion si es valido.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

// Roles que se muestran como columnas de la matriz.
$roles = ['cliente', 'gestor', 'admin'];

// Permisos que el admin no puede deshabilitar.
$protegidos = tgs_permisos_protegidos();

// Etiquetas legibles para cada permiso (clave interna => texto).
$descripciones = $lang_code === 'en' ? [
    'ver_panel_admin' => 'Open the admin panel',
    'ver_panel_gestor' => 'Open the manager panel',
    'ver_panel_cliente' => 'Open the customer panel',
    'ver_turnos_propios' => 'See own appointments',
    'crear_turno' => 'Book an appointment',
    'editar_turno_propio' => 'Edit own appointment',
    'cancelar_turno_propio' => 'Cancel own appointment',
    'gestionar_vehiculos' => 'Manage own vehicles',
    'administrar_turnos' => 'Manage all appointments',
    'cambiar_estado_reserva' => 'Change appointment status',
    'editar_perfil' => 'Edit own profile',
    'ver_presupuestos_propios' => 'See own budgets',
    'responder_presupuesto' => 'Accept or reject a budget',
    'administrar_presupuestos' => 'Create and edit budgets',
    'administrar_pagos' => 'Manage payments and billing',
    'ver_talleres' => 'See workshops',
    'administrar_talleres' => 'Manage workshops list',
    'crear_taller' => 'Create a workshop',
    'editar_taller' => 'Edit a workshop',
    'eliminar_taller' => 'Delete a workshop',
    'administrar_servicios' => 'Manage services',
    'administrar_usuarios' => 'Open user administration',
    'crear_usuarios' => 'Create users',
    'editar_usuarios' => 'Edit users',
    'eliminar_usuarios' => 'Delete users',
    'cambiar_roles' => 'Change user roles',
    'ver_matriz_permisos' => 'See the permission matrix',
    'editar_matriz_permisos' => 'Edit the permission matrix'
] : [
    'ver_panel_admin' => 'Entrar al panel de administración',
    'ver_panel_gestor' => 'Entrar al panel del gestor',
    'ver_panel_cliente' => 'Entrar al panel del cliente',
    'ver_turnos_propios' => 'Ver sus propios turnos',
    'crear_turno' => 'Reservar un turno',
    'editar_turno_propio' => 'Editar su propio turno',
    'cancelar_turno_propio' => 'Cancelar su propio turno',
    'gestionar_vehiculos' => 'Gestionar sus vehículos',
    'administrar_turnos' => 'Administrar todos los turnos',
    'cambiar_estado_reserva' => 'Cambiar el estado de una reserva',
    'editar_perfil' => 'Editar su perfil',
    'ver_presupuestos_propios' => 'Ver sus presupuestos',
    'responder_presupuesto' => 'Aceptar o rechazar un presupuesto',
    'administrar_presupuestos' => 'Crear y editar presupuestos',
    'administrar_pagos' => 'Administrar pagos y facturación',
    'ver_talleres' => 'Ver los talleres',
    'administrar_talleres' => 'Administrar el listado de talleres',
    'crear_taller' => 'Crear un taller',
    'editar_taller' => 'Editar un taller',
    'eliminar_taller' => 'Eliminar un taller',
    'administrar_servicios' => 'Administrar los servicios',
    'administrar_usuarios' => 'Entrar a la administración de usuarios',
    'crear_usuarios' => 'Crear usuarios',
    'editar_usuarios' => 'Editar usuarios',
    'eliminar_usuarios' => 'Eliminar usuarios',
    'cambiar_roles' => 'Cambiar el rol de un usuario',
    'ver_matriz_permisos' => 'Ver la matriz de permisos',
    'editar_matriz_permisos' => 'Editar la matriz de permisos'
];

$matriz = tgs_matriz_permisos();
$nombre_roles = ['cliente' => 'Cliente', 'gestor' => 'Gestor', 'admin' => 'Admin'];

// Permisos modificados guardados en la base (rol => permiso => estado).
$modificados = tgs_permisos_modificados();

$mensaje = '';
$tipo_alerta = 'alert-success';

/**
 * Estado efectivo de un par rol/permiso: lo configurado en la base si
 * existe, o el valor por defecto del catalogo.
 */
function permisoEfectivo(array $matriz, array $modificados, string $rol, string $permiso): bool
{
    if (isset($modificados[$rol][$permiso])) {
        return $modificados[$rol][$permiso];
    }
    return in_array($rol, $matriz[$permiso] ?? [], true);
}

// ---------------------------------------------------------------------------
// Acciones POST: guardar la matriz o restaurar los valores por defecto
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $mensaje = $lang_code === 'en' ? 'The session expired. Please reload the page.' : 'La sesión expiró. Recargá la página.';
        $tipo_alerta = 'alert-error';
    } elseif (isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        // Guardar la matriz completa: solo se guardan los permisos que
        // difieren del valor por defecto (si no, se borra la fila).
        if ($accion === 'guardar_matriz') {
            try {
                # Cada par rol|permiso llega dos veces: el campo oculto con
                # el valor 0 y el checkbox con el valor 1 (solo si esta
                # marcado). Si aparece un 1, el permiso queda habilitado.
                $peticionados = [];
                $entradas_validas = 0;
                foreach ((array) ($_POST['permisos'] ?? []) as $entrada) {
                    $partes = explode(':', (string) $entrada);
                    if (count($partes) !== 2) {
                        continue;
                    }
                    $entradas_validas++;
                    $clave = $partes[0];
                    if ((int) $partes[1] === 1) {
                        $peticionados[$clave] = true;
                    } elseif (!isset($peticionados[$clave])) {
                        $peticionados[$clave] = false;
                    }
                }

                # Red de seguridad: si el formulario no tiene el formato
                # esperado (por ejemplo, una pagina vieja guardada en el
                # navegador) no se toca nada. Sin este control, una carga
                # incompleta deshabilitaria todos los permisos.
                if ($entradas_validas === 0) {
                    $mensaje = $lang_code === 'en'
                        ? 'The form is out of date. Reload the page and try again.'
                        : 'El formulario está desactualizado. Recargá la página e intentá nuevamente.';
                    $tipo_alerta = 'alert-error';
                } else {
                    $stmt = $conexion->prepare('INSERT INTO permiso_rol (rol, permiso, activo) VALUES (:rol, :permiso, :activo) ON DUPLICATE KEY UPDATE activo = VALUES(activo)');
                    $borrar = $conexion->prepare('DELETE FROM permiso_rol WHERE rol = :rol AND permiso = :permiso');

                    $guardados = 0;
                    $restaurados = 0;

                    foreach ($roles as $rol) {
                        foreach ($matriz as $permiso => $roles_por_defecto) {
                            $clave = $rol . '|' . $permiso;
                            $peticionado = $peticionados[$clave] ?? false;
                            $por_defecto = in_array($rol, $roles_por_defecto, true);

                            // Un permiso protegido del admin no se puede quitar.
                            if ($rol === 'admin' && in_array($permiso, $protegidos, true)) {
                                $peticionado = true;
                            }

                            if ($peticionado === $por_defecto) {
                                // Vuelve al valor por defecto: se borra la fila.
                                $borrar->execute(['rol' => $rol, 'permiso' => $permiso]);
                                $restaurados++;
                            } else {
                                $stmt->execute(['rol' => $rol, 'permiso' => $permiso, 'activo' => $peticionado ? 1 : 0]);
                                $guardados++;
                            }
                        }
                    }

                    $mensaje = $lang_code === 'en'
                        ? 'Permissions saved: ' . $guardados . ' customized, ' . $restaurados . ' back to default.'
                        : 'Permisos guardados: ' . $guardados . ' personalizados, ' . $restaurados . ' restaurados al valor por defecto.';
                }
            } catch (PDOException $e) {
                $mensaje = $lang_code === 'en' ? 'The permissions could not be saved.' : 'No se pudieron guardar los permisos.';
                $tipo_alerta = 'alert-error';
            }
        }

        // Restaurar toda la matriz: borra todas las filas guardadas.
        if ($accion === 'restaurar_todo') {
            try {
                $conexion->exec('DELETE FROM permiso_rol');
                $mensaje = $lang_code === 'en'
                    ? 'All permissions were restored to their default values.'
                    : 'Se restauraron todos los permisos a sus valores por defecto.';
            } catch (PDOException $e) {
                $mensaje = $lang_code === 'en' ? 'The permissions could not be restored.' : 'No se pudieron restaurar los permisos.';
                $tipo_alerta = 'alert-error';
            }
        }
    }
}

// Se releen los cambios guardados para pintar el estado actual.
$modificados = tgs_permisos_modificados();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang_code === 'en' ? 'Permission Matrix' : 'Matriz de Permisos'; ?> - TGS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #0b1329; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        header { background-color: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 20px; font-weight: bold; color: #38bdf8; text-decoration: none; }
        nav { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        nav span { color: #94a3b8; font-size: 14px; }
        nav a.nav-link { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav a.nav-link:hover { color: #38bdf8; }
        nav a.btn-logout { background-color: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.3s; }
        nav a.btn-logout:hover { background-color: #dc2626; }
        .language-selector { display: flex; gap: 4px; }
        .language-selector a { color: #cbd5e1; border: 1px solid #475569; border-radius: 6px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .language-selector a.active, .language-selector a:hover { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
        .container { flex: 1; padding: 40px 20px; max-width: 1000px; width: 100%; margin: 0 auto; }
        .welcome-card { background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 12px; padding: 26px 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5); margin-bottom: 24px; }
        .welcome-card h2 { font-size: 22px; color: #38bdf8; margin-bottom: 8px; }
        .welcome-card p { color: #cbd5e1; font-size: 14px; }
        .tabla-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background-color: rgba(30, 41, 59, 0.95); border: 1px solid #334155; border-radius: 8px; overflow: hidden; }
        th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid #334155; font-size: 13px; }
        th { background-color: rgba(15, 23, 42, 0.95); color: #38bdf8; }
        td.centro { text-align: center; }
        .permiso-clave { font-family: Consolas, 'Courier New', monospace; font-size: 12px; color: #94a3b8; }
        .permiso-desc { color: #f8fafc; }
        .si { color: #4ade80; font-weight: 700; }
        .no { color: #64748b; }
        .casilla { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .casilla input { width: 15px; height: 15px; accent-color: #22c55e; cursor: pointer; }
        .casilla input:disabled { cursor: not-allowed; }
        .casilla .no { display: none; }
        .casilla input:not(:checked) ~ .no { display: inline; }
        .casilla input:not(:checked) ~ .si { display: none; }
        .marca-modificado { color: #fbbf24; font-weight: 700; }
        .acciones-matriz { margin: 18px 0 12px; }
        .btn-primario { background: #2563eb; color: white; border: none; border-radius: 8px; padding: 11px 20px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-primario:hover { background: #1d4ed8; }
        .btn-secundario { background: #334155; color: #cbd5e1; border: none; border-radius: 8px; padding: 11px 18px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-secundario:hover { background: #475569; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #22c55e; background: rgba(34, 197, 94, 0.1); color: #bbf7d0; font-size: 14px; }
        .alert-error { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .nota { font-size: 12px; color: #94a3b8; margin-top: 8px; }
        .volver { display: inline-block; margin-top: 20px; color: #38bdf8; text-decoration: none; font-size: 14px; font-weight: 600; }
        .volver:hover { text-decoration: underline; }
        footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; border-top: 1px solid #334155; background-color: rgba(15, 23, 42, 0.95); }
    </style>
    <link rel="stylesheet" href="css/tema-tgs.css">
    <link rel="stylesheet" href="css/movil.css">
    <script src="js/movil.js" defer></script>
</head>
<body>
    <header>
        <a href="dashboard_admin.php" class="logo">TGS · <?php echo $lang_code === 'en' ? 'Permission Matrix' : 'Matriz de Permisos'; ?></a>
        <nav>
            <a href="index.php" class="nav-link"><?php echo $lang_code === 'en' ? 'Home' : 'Inicio'; ?></a>
            <a href="dashboard_admin.php" class="nav-link" style="color: #38bdf8; font-weight: bold;"><?php echo $lang_code === 'en' ? 'Admin Panel' : 'Panel Admin'; ?></a>
            <!-- Selector de idioma de la matriz de permisos -->
            <div class="language-selector"><a href="permisos.php?lang=es" class="<?php echo $lang_code === 'es' ? 'active' : ''; ?>">ES</a><a href="permisos.php?lang=en" class="<?php echo $lang_code === 'en' ? 'active' : ''; ?>">EN</a></div>
            <span><?php echo $lang_code === 'en' ? 'Hello' : 'Hola'; ?>, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
            <a href="logout.php" class="btn-logout"><?php echo $lang_code === 'en' ? 'Sign Out' : 'Cerrar Sesión'; ?></a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome-card">
            <h2><?php echo $lang_code === 'en' ? 'Permissions by role' : 'Permisos por rol'; ?></h2>
            <p>
                <?php echo $lang_code === 'en'
                    ? 'Mark the permissions each role should have. Changes are stored in the database and apply immediately. Unmarked permissions keep the default value defined in auth.php (tgs_matriz_permisos).'
                    : 'Marcá los permisos que debe tener cada rol. Los cambios se guardan en la base de datos y se aplican de inmediato. Los permisos sin marcar conservan el valor por defecto definido en auth.php (tgs_matriz_permisos).'; ?>
            </p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_alerta; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <form method="POST" id="form-matriz">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="accion" value="guardar_matriz">

            <div class="tabla-scroll">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo $lang_code === 'en' ? 'Permission' : 'Permiso'; ?></th>
                            <?php foreach ($roles as $rol): ?>
                                <th class="centro"><?php echo htmlspecialchars($nombre_roles[$rol]); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matriz as $permiso => $roles_por_defecto): ?>
                            <?php $bloqueado_admin = in_array($permiso, $protegidos, true); ?>
                            <tr>
                                <td>
                                    <div class="permiso-desc"><?php echo htmlspecialchars($descripciones[$permiso] ?? $permiso); ?></div>
                                    <div class="permiso-clave"><?php echo htmlspecialchars($permiso); ?></div>
                                </td>
                                <?php foreach ($roles as $rol):
                                    $marcado = permisoEfectivo($matriz, $modificados, $rol, $permiso);
                                    $modificado = isset($modificados[$rol][$permiso]);
                                    $bloqueado = ($rol === 'admin' && $bloqueado_admin);
                                ?>
                                    <td class="centro">
                                        <label class="casilla">
                                            <!-- Campo oculto: el navegador solo envia
                                                 los checkboxes marcados, asi que el
                                                 valor 0 viaja siempre y el 1 solo
                                                 cuando la casilla esta activa. -->
                                            <input type="hidden" name="permisos[]" value="<?php echo htmlspecialchars($rol . '|' . $permiso); ?>:0">
                                            <input type="checkbox" name="permisos[]" value="<?php echo htmlspecialchars($rol . '|' . $permiso); ?>:1" <?php echo $marcado ? 'checked' : ''; ?> <?php echo $bloqueado ? 'disabled' : ''; ?>>
                                            <span class="si">&#10003;</span>
                                            <span class="no">&ndash;</span>
                                            <?php if ($modificado): ?>
                                                <span class="marca-modificado" title="<?php echo $lang_code === 'en' ? 'Custom value' : 'Valor personalizado'; ?>">&bull;</span>
                                            <?php endif; ?>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="acciones-matriz">
                <button type="submit" class="btn-primario"><?php echo $lang_code === 'en' ? 'Save permissions' : 'Guardar permisos'; ?></button>
                <span class="nota">
                    <?php echo $lang_code === 'en'
                        ? 'A bullet marks the permissions that differ from the default value.'
                        : 'El punto marca los permisos que difieren del valor por defecto.'; ?>
                </span>
            </div>
        </form>

        <form method="POST" onsubmit="return confirm('<?php echo $lang_code === 'en' ? 'Restore all default permissions?' : '¿Restaurar todos los permisos por defecto?'; ?>');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="accion" value="restaurar_todo">
            <button type="submit" class="btn-secundario"><?php echo $lang_code === 'en' ? 'Restore defaults' : 'Restaurar valores por defecto'; ?></button>
        </form>

        <a href="dashboard_admin.php" class="volver">&larr; <?php echo $lang_code === 'en' ? 'Back to Admin Panel' : 'Volver al Panel de Administración'; ?></a>
    </div>

    <footer>
        <p>&copy; 2026 TGS - Taller Global de Servicios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
