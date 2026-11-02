<?php
/**
 * Smoke test: recorre todas las pantallas por rol contra la copia local.
 * Verifica codigo HTTP y que la salida no tenga errores de PHP.
 */
$base = 'http://127.0.0.1:8099';
$jar  = 'C:/Users/ttugo/AppData/Local/Temp/opencode/tgs-video/work/jar_' . getmypid() . '.txt';
@mkdir(dirname($jar), 0777, true);

function pedir(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string)$html];
}

function login(string $base, string $jar, string $email, string $pass): bool {
    @unlink($jar);
    [, $html] = pedir("$base/login.php", $jar);
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
    [$code] = pedir("$base/login.php", $jar, ['csrf_token' => $m[1] ?? '', 'email' => $email, 'password' => $pass]);
    return $code === 200;
}

$grupos = [
    'publico' => [null, null, [
        'index.php', 'taller.php?id=1', 'taller.php?id=2', 'registro.php', 'login.php',
    ]],
    'cliente' => ['carlos.gomez@gmail.com', 'Cliente123!', [
        'panel_cliente.php', 'reservas.php', 'pagos_cliente.php', 'presupuestos.php',
        'historial_cliente.php', 'perfil.php', 'factura.php?id=1',
    ]],
    'gestor' => ['santiiuyy12@gmail.com', 'Gestor123!', [
        'dashboard_gestor.php', 'agenda_gestor.php', 'detalle_reserva.php?id=16',
        'administrar_reservas.php', 'administrar_pagos.php', 'administrar_presupuestos.php',
        'notificaciones.php',
    ]],
    'admin' => ['admin@bluetech.com', 'Admin123!', [
        'dashboard_admin.php', 'administrar_usuarios.php', 'administrar_talleres.php',
        'administrar_servicios.php', 'permisos.php', 'datos_pago.php',
        'agregar_taller.php', 'editar_taller.php?id=1',
    ]],
];

$fallos = 0;
foreach ($grupos as $rol => [$email, $pass, $paginas]) {
    echo "\n=== $rol ===\n";
    if ($email !== null) {
        $ok = login($base, $jar, $email, $pass);
        echo "  login $email -> " . ($ok ? 'OK' : 'FALLO') . "\n";
        if (!$ok) { $fallos++; continue; }
    }
    foreach ($paginas as $pagina) {
        [$code, $html] = pedir("$base/$pagina", $jar);
        $problemas = [];
        if ($code !== 200) { $problemas[] = "HTTP $code"; }
        if (preg_match('/(Fatal error|Parse error|Warning:|Uncaught|Deprecated:)/i', $html, $m)) {
            $problemas[] = 'PHP: ' . substr(preg_replace('/\s+/', ' ', $m[0]), 0, 40);
        }
        if (stripos($html, 'error 403') !== false || stripos($html, 'Acceso denegado') !== false) {
            $problemas[] = '403';
        }
        if (count($problemas)) {
            $fallos++;
            printf("  %-32s %s  <-- %s\n", $pagina, $code, implode(' | ', $problemas));
            if (preg_match('/(Fatal error|Parse error|Warning:|Uncaught)[^<]{0,300}/i', $html, $m2)) {
                echo "        " . trim(preg_replace('/\s+/', ' ', $m2[0])) . "\n";
            }
        } else {
            printf("  %-32s %s  OK (%d bytes)\n", $pagina, $code, strlen($html));
        }
    }
}
@unlink($jar);
echo "\nPAGINAS CON PROBLEMAS: $fallos\n";
