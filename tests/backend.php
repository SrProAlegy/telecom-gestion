<?php

declare(strict_types=1);

if (getenv('DB_DATABASE') !== 'telcom_pruebas' || PHP_SAPI !== 'cli') {
    throw new RuntimeException('Esta prueba requiere tests/compose.yaml y la base aislada telcom_pruebas.');
}
require __DIR__ . '/../backend/bootstrap.php';
require __DIR__ . '/excel.php';
$db = require __DIR__ . '/../backend/config/database.php';
if ((int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() !== 0) {
    throw new RuntimeException('La base de pruebas debe estar vacía.');
}
$checks = 0;
function verificar(bool $ok, string $descripcion): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($descripcion);
    }
    $checks++;
    echo "OK: $descripcion\n";
}
function pedir(
    array &$cliente,
    string $ruta,
    string $metodo = 'GET',
    mixed $datos = null,
    bool $csrf = true,
    int $port = 8099
): array {
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if (isset($cliente['cookie'])) {
        $headers[] = 'Cookie: ' . $cliente['cookie'];
    }
    if ($csrf && isset($cliente['csrf'])) {
        $headers[] = 'X-CSRF-Token: ' . $cliente['csrf'];
    }
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'ignore_errors' => true, 'timeout' => 10,
        'follow_location' => 0, 'header' => implode("\r\n", $headers),
        'content' => $datos === null ? '' : json_encode($datos, JSON_THROW_ON_ERROR)]]);
    $body = file_get_contents("http://127.0.0.1:$port$ruta", false, $ctx);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
    foreach ($http_response_header as $h) {
        if (preg_match('/^Set-Cookie: (telcom_sesion=[^;]+)/i', $h, $c)) {
                $cliente['cookie'] = $c[1];
        }
    }
    if (preg_match('/name="csrf-token" content="([^"]+)"/', $body, $mToken)) {
        $cliente['csrf'] = $mToken[1];
    }
    return ['status' => (int)$m[1], 'body' => $body, 'json' => json_decode($body, true),
        'headers' => $http_response_header];
}
function api(array &$c, string $recurso, array $datos, ?int $id = null, int $expected = 0): array
{
    global $db;
    if ($recurso === 'ventas') {
        $datos['operacion'] ??= bin2hex(random_bytes(16));
    }
    if ($recurso === 'reparaciones' && $id !== null && !array_key_exists('revision', $datos)) {
        $datos['revision'] = (int)$db->query("SELECT COALESCE(MAX(id_historial), 0)
            FROM historial_reparaciones WHERE reparacion_id = $id")->fetchColumn();
    }
    if ($recurso === 'inventario' && !array_key_exists('cantidad_anterior', $datos)) {
        $q = $db->prepare('SELECT cantidad FROM inventario WHERE producto_id = ? AND sucursal_id = ?');
        $q->execute([$datos['producto_id'], $datos['sucursal_id']]);
        $datos['cantidad_anterior'] = (int)($q->fetchColumn() ?: 0);
    }
    $r = pedir($c, '/api/' . $recurso . ($id === null ? '' : "/$id"), $id === null ? 'POST' : 'PATCH', $datos);
    $expected = $expected ?: ($id === null ? 201 : 200);
    verificar($r['status'] === $expected, "$recurso: HTTP $expected (recibido {$r['status']})");
    if ($expected < 300) {
        verificar(($r['json']['ok'] ?? false) === true, "$recurso confirma persistencia");
    }
    return $r['json'] ?? [];
}
function login(string $correo, string $password): array
{
    $c = [];
    verificar(pedir($c, '/acceso')['status'] === 200, 'Acceso disponible');
    $anterior = $c['cookie'];
    $r = pedir($c, '/api/sesion', 'POST', ['correo' => $correo, 'password' => $password]);
    verificar($r['status'] === 200 && ($r['json']['ok'] ?? false), 'Autenticación válida');
    verificar($c['cookie'] !== $anterior, 'La autenticación regenera el identificador de sesión');
    verificar(pedir($c, '/')['status'] === 200, 'Panel autenticado');
    return $c;
}
$tmp = sys_get_temp_dir() . '/telcom-test-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
$servidores = [];
$password = getenv('TELCOM_TEST_PASSWORD') ?: 'Pruebas-' . bin2hex(random_bytes(12));
$root = dirname(__DIR__);
try {
    $cmd = [PHP_BINARY, 'backend/crear-admin.php', '--correo=admin@telcom.test', '--nombre=Admin',
        '--apellido=Pruebas', '--sucursal=Sucursal A', '--password-stdin'];
    $p = proc_open(
        $cmd,
        [['pipe', 'r'], ['file', "$tmp/alta.log", 'a'], ['file', "$tmp/alta.log", 'a']],
        $pipes,
        $root
    );
    fwrite($pipes[0], $password . "\n");
    fclose($pipes[0]);
    verificar(proc_close($p) === 0, 'Alta inicial por consola');
    verificar((int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 1, 'Una sola cuenta inicial');
    $p = proc_open(
        $cmd,
        [['pipe', 'r'], ['file', "$tmp/alta.log", 'a'], ['file', "$tmp/alta.log", 'a']],
        $pipes,
        $root
    );
    fwrite($pipes[0], $password . "\n");
    fclose($pipes[0]);
    verificar(proc_close($p) !== 0, 'El alta inicial no se puede repetir');
    foreach ([8099, 8100] as $port) {
        $servidores[] = proc_open(
            [PHP_BINARY, '-d', "session.save_path=$tmp", '-S', "127.0.0.1:$port",
            '-t', 'frontend/public', 'tests/router.php'],
            [['file', '/dev/null', 'r'], ['file', "$tmp/web-$port.log", 'a'], ['file', "$tmp/web-$port.log", 'a']],
            $pipes,
            $root
        );
        $deadline = microtime(true) + 5;
        do {
            $socket = @fsockopen('127.0.0.1', $port);
            if ($socket) {
                fclose($socket);
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
    }
    $anon = [];
    verificar(pedir($anon, '/')['status'] === 302, 'Los datos requieren sesión');
    verificar(pedir($anon, '/api/clientes', 'POST', ['nombre' => 'X'])['status'] === 401, 'La API requiere sesión');
    pedir($anon, '/acceso');
    verificar(pedir(
        $anon,
        '/api/sesion',
        'POST',
        ['correo' => 'admin@telcom.test', 'password' => $password],
        false
    )['status'] === 419, 'CSRF obligatorio al iniciar sesión');
    verificar(pedir($anon, '/api/sesion', 'POST', ['correo' => 'admin@telcom.test',
        'password' => 'incorrecta'])['status'] === 401, 'Credenciales incorrectas rechazadas');
    $a = login('admin@telcom.test', $password);
    verificar(
        pedir($a, '/api/clientes', 'POST', ['nombre' => 'Sin token'], false)['status'] === 419,
        'CSRF obligatorio en escrituras'
    );
    foreach (
        ['/', '/clientes-equipos', '/reparaciones', '/inventario', '/ventas', '/reportes',
        '/usuarios-sucursales'] as $ruta
    ) {
        verificar(pedir($a, $ruta)['status'] === 200, "Vista autenticada $ruta");
    }
    verificar(pedir($a, '/reportes?desde=2026-02-31')['status'] === 400, 'Filtros de fecha estrictos');
    verificar(pedir($a, '/api/clientes', 'POST', [1, 2])['status'] === 400, 'Se exige un objeto JSON');
    verificar(pedir($a, '/api/clientes/1', 'DELETE')['status'] === 405, 'Sin borrado destructivo de operaciones');
    $branchA = (int)$db->query('SELECT sucursal_id FROM usuarios LIMIT 1')->fetchColumn();
    $branchB = api($a, 'sucursales', ['nombre' => 'Sucursal B', 'activo' => 1])['id'];
    api($a, 'sucursales', ['nombre' => 'Sucursal B', 'direccion' => 'Calle B', 'activo' => 1], $branchB);
    $cliente = api($a, 'clientes', ['nombre' => '<script>Cliente</script>', 'correo' => 'cliente@telcom.test'])['id'];
    api($a, 'clientes', ['nombre' => 'Cliente actualizado', 'telefono' => '5550001234'], $cliente);
    api($a, 'clientes', ['nombre' => 'Inválido', 'correo' => 'sin-arroba'], null, 422);
    api($a, 'clientes', ['nombre' => ['inyeccion']], null, 422);
    api($a, 'clientes', ['nombre' => str_repeat('Á', 201)], null, 422);
    $equipo = api($a, 'equipos', ['cliente_id' => $cliente, 'imei' => '012345678901234',
        'marca' => 'Teléfono'])['id'];
    api($a, 'equipos', ['cliente_id' => $cliente, 'imei' => '123'], null, 422);
    $categoria = api($a, 'categorias', ['nombre' => 'Accesorios'])['id'];
    api($a, 'categorias', ['nombre' => 'Accesorios'], null, 409);
    api($a, 'categorias', ['nombre' => 'Accesorios', 'descripcion' => 'Catálogo'], $categoria);
    $producto = api($a, 'productos', ['nombre' => 'Cable', 'categoria_id' => $categoria,
        'precio' => '0.29', 'stock_minimo' => 2, 'activo' => 1])['id'];
    api($a, 'productos', ['nombre' => 'Cable', 'categoria_id' => $categoria,
        'precio' => '0.29', 'stock_minimo' => 3, 'activo' => 1], $producto);
    api($a, 'productos', ['nombre' => 'Inválido', 'categoria_id' => $categoria,
        'precio' => '0.299', 'stock_minimo' => 1, 'activo' => 1], null, 422);
    api($a, 'inventario', ['producto_id' => $producto, 'sucursal_id' => $branchA, 'cantidad' => 5]);
    api($a, 'inventario', ['producto_id' => $producto, 'sucursal_id' => $branchB, 'cantidad' => 2]);
    api($a, 'inventario', ['producto_id' => $producto, 'sucursal_id' => $branchA, 'cantidad' => -1], null, 422);
    api($a, 'inventario', ['producto_id' => $producto, 'sucursal_id' => $branchA, 'cantidad' => 9,
        'cantidad_anterior' => 0], null, 409);
    $tecnico = api($a, 'tecnicos', ['nombre' => 'Técnico', 'apellido' => 'Pruebas', 'activo' => 1])['id'];
    api($a, 'tecnicos', ['nombre' => 'Técnico', 'apellido' => 'Pruebas', 'activo' => 1,
        'especialidad' => 'Móviles'], $tecnico);
    $cuentas = [];
    foreach (['recepcion', 'tecnico', 'ventas', 'ventas2'] as $rol) {
        $cuentas[$rol] = api($a, 'usuarios', ['nombre' => $rol, 'apellido' => 'Pruebas',
            'correo' => "$rol@telcom.test",
            'password' => $password, 'rol' => $rol === 'ventas2' ? 'ventas' : $rol,
            'sucursal_id' => $branchB, 'activo' => 1])['id'];
    }
    $b = login('recepcion@telcom.test', $password);
    $t = login('tecnico@telcom.test', $password);
    $v = login('ventas@telcom.test', $password);
    $v2 = login('ventas2@telcom.test', $password);
    verificar(pedir($b, '/usuarios-sucursales')['status'] === 403, 'Administración restringida por rol');
    verificar(pedir($t, '/ventas')['status'] === 403, 'Técnico sin acceso a ventas');
    verificar(pedir($b, "/inventario?sucursal=$branchA")['status'] === 400, 'No se permite consultar otra sucursal');
    api($b, 'sucursales', ['nombre' => 'Intrusión', 'activo' => 1], null, 403);
    api($t, 'clientes', ['nombre' => 'Intrusión'], null, 403);
    api($v, 'inventario', ['producto_id' => $producto, 'sucursal_id' => $branchB, 'cantidad' => 100], null, 403);
    $rep = api($b, 'reparaciones', ['equipo_id' => $equipo, 'sucursal_id' => $branchB,
        'problema_reportado' => 'No enciende'])['id'];
    api(
        $b,
        'reparaciones',
        ['equipo_id' => $equipo, 'sucursal_id' => $branchA, 'problema_reportado' => 'X'],
        null,
        403
    );
    api($b, 'reparaciones', ['estado' => 'entregado', 'tecnico_id' => $tecnico], $rep, 422);
    foreach (['diagnostico', 'en_reparacion', 'listo_entrega'] as $estado) {
        api($t, 'reparaciones', ['estado' => $estado, 'tecnico_id' => $tecnico,
            'diagnostico' => 'Conector dañado', 'observaciones' => 'Seguimiento'], $rep);
    }
    api($t, 'reparaciones', ['estado' => 'listo_entrega', 'tecnico_id' => $tecnico,
        'revision' => 0, 'diagnostico' => 'Captura antigua'], $rep, 409);
    api(
        $t,
        'reparaciones',
        ['estado' => 'entregado', 'tecnico_id' => $tecnico, 'diagnostico' => 'Reparado'],
        $rep,
        403
    );
    api($b, 'reparaciones', ['estado' => 'entregado', 'tecnico_id' => $tecnico, 'diagnostico' => 'Reparado'], $rep);
    api($b, 'reparaciones', ['estado' => 'diagnostico', 'tecnico_id' => $tecnico], $rep, 409);
    api($b, 'reparaciones', ['estado' => 'entregado', 'tecnico_id' => $tecnico, 'revision' => 0], $rep, 409);
    $h = pedir($b, "/api/reparaciones/$rep/historial");
    verificar(count($h['json']['registros']) === 5, 'Historial registra recepción y cada cambio confirmado');
    verificar(
        (bool)$db->query("SELECT fecha_entrega FROM reparaciones WHERE id_reparacion = $rep")->fetchColumn(),
        'Entrega con fecha del servidor'
    );
    api($a, 'equipos', ['cliente_id' => 4294967295], $equipo, 409);
    api($a, 'usuarios', ['nombre' => 'Recepción', 'apellido' => 'Pruebas', 'correo' => 'recepcion@telcom.test',
        'rol' => 'recepcion', 'sucursal_id' => $branchA, 'activo' => 1], $cuentas['recepcion'], 409);
    api($a, 'sucursales', ['nombre' => 'Sucursal A', 'activo' => 0], $branchA, 409);
    $adminId = (int)$db->query("SELECT id_usuario FROM usuarios WHERE rol = 'administrador'")->fetchColumn();
    api($a, 'usuarios', ['nombre' => 'Admin', 'apellido' => 'Pruebas', 'correo' => 'admin@telcom.test',
        'rol' => 'administrador', 'sucursal_id' => $branchA, 'activo' => 0], $adminId, 409);
    $venta = ['operacion' => bin2hex(random_bytes(16)), 'sucursal_id' => $branchB,
        'cliente_id' => $cliente, 'metodo_pago' => 'efectivo',
        'total' => '0.00', 'usuario_id' => $adminId, 'detalles' => [['producto_id' => $producto,
            'cantidad' => 2, 'precio' => '0.00']]];
    api($a, 'ventas', $venta, null, 403);
// Dos servidores y sesiones diferentes compiten por las dos últimas unidades.
    $sockets = [];
    foreach ([[$v, 8099], [$v2, 8100]] as [$c, $port]) {
        $sock = fsockopen('127.0.0.1', $port);
        stream_set_timeout($sock, 10);
        $json = json_encode($venta);
        fwrite($sock, "POST /api/ventas HTTP/1.1\r\nHost: localhost\r\nCookie: {$c['cookie']}"
            . "\r\nX-CSRF-Token: {$c['csrf']}"
            . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json)
            . "\r\nConnection: close\r\n\r\n$json");
        $sockets[] = $sock;
    }
    $statuses = [];
    foreach ($sockets as $sock) {
        $respuesta = stream_get_contents($sock);
            fclose($sock);
        preg_match('/HTTP\/1\.1 (\d+)/', $respuesta, $match);
        $statuses[] = (int)$match[1];
    }
    sort($statuses);
    verificar($statuses === [201, 409], 'Concurrencia: una venta confirma y la otra rechaza stock insuficiente');
    verificar(
        (string)$db->query('SELECT total FROM ventas LIMIT 1')->fetchColumn() === '0.58',
        'Total calculado en servidor con precisión decimal'
    );
    verificar(
        (int)$db->query("SELECT cantidad FROM inventario WHERE sucursal_id = $branchB")->fetchColumn() === 0,
        'Stock descontado una sola vez'
    );
    verificar(
        (int)$db->query('SELECT COUNT(*) FROM ventas')->fetchColumn() === 1,
        'Sin venta parcial tras conflicto'
    );
    $idVenta = (int)$db->query('SELECT id_venta FROM ventas LIMIT 1')->fetchColumn();
    verificar(count(pedir($v, "/api/ventas/$idVenta")['json']['registros']) === 1, 'Detalle de venta consultable');
    verificar(pedir($t, "/api/ventas/$idVenta")['status'] === 403, 'Detalle protegido por rol');
    $repA = api($a, 'reparaciones', ['equipo_id' => $equipo, 'sucursal_id' => $branchA,
        'problema_reportado' => 'Nueva recepción'])['id'];
    verificar(pedir($b, "/api/reparaciones/$repA/historial")['status'] === 404, 'Historial protegido por sucursal');
    api($b, 'reparaciones', ['estado' => 'diagnostico', 'tecnico_id' => $tecnico], $repA, 403);
    $branchC = api($a, 'sucursales', ['nombre' => 'Sucursal C', 'activo' => 1])['id'];
    $recepcionC = api($a, 'usuarios', ['nombre' => 'Recepción C', 'apellido' => 'Pruebas',
        'correo' => 'recepcionc@telcom.test', 'password' => $password, 'rol' => 'recepcion',
        'sucursal_id' => $branchC, 'activo' => 1])['id'];
    $c = login('recepcionc@telcom.test', $password);
    $repC = api($c, 'reparaciones', ['equipo_id' => $equipo, 'sucursal_id' => $branchC,
        'tecnico_id' => $tecnico, 'problema_reportado' => 'Atención técnica desde otra sede'])['id'];
    $folioC = 'REP-' . str_pad((string)$repC, 4, '0', STR_PAD_LEFT);
    verificar(str_contains(pedir($t, '/reparaciones')['body'], $folioC), 'Técnico B consulta la cola de sucursal C');
    verificar(
        str_contains(pedir($t, "/reparaciones?sucursal=$branchA")['body'], 'Nueva recepción'),
        'Técnico B consulta también sucursal A'
    );
    verificar(!str_contains(pedir($t, '/')['body'], 'Ventas del día'), 'Panel técnico sin indicadores financieros');
    verificar(pedir($t, '/inventario')['status'] === 403, 'Área técnica sin acceso a inventario');
    verificar(pedir($t, '/reportes')['status'] === 403, 'Área técnica sin acceso a reportes financieros');
    verificar(
        pedir($t, '/reparaciones?tecnico=999999999')['status'] === 400,
        'Filtro de técnico inexistente rechazado'
    );
    verificar(
        pedir($t, '/reparaciones?tecnico[]=1')['status'] === 400,
        'Filtro de técnico con formato inválido rechazado'
    );
    verificar(
        !str_contains(pedir($t, '/reparaciones?tecnico=sin_asignar')['body'], $folioC),
        'Cola sin asignar excluye reparaciones asignadas'
    );
    verificar(
        str_contains(pedir($t, "/reparaciones?sucursal=$branchC&tecnico=$tecnico")['body'], $folioC),
        'Filtros de sucursal y técnico combinados'
    );
    verificar(
        pedir($b, "/reparaciones?sucursal=$branchC")['status'] === 400,
        'Recepción B no consulta otra sucursal al ampliar el área técnica'
    );
    api($b, 'reparaciones', ['estado' => 'diagnostico', 'tecnico_id' => $tecnico], $repC, 403);
    api($t, 'reparaciones', ['equipo_id' => $equipo, 'sucursal_id' => $branchB,
        'problema_reportado' => 'Recepción no autorizada'], null, 403);
    foreach (['enviado_tecnico', 'diagnostico', 'en_reparacion', 'listo_entrega'] as $estado) {
        api($t, 'reparaciones', ['estado' => $estado, 'tecnico_id' => $tecnico,
            'diagnostico' => 'Revisión y reparación central', 'observaciones' => 'Operador de sucursal B',
            'sucursal_id' => $branchB, 'usuario_id' => $cuentas['tecnico'], 'equipo_id' => 4294967295], $repC);
    }
    api($t, 'reparaciones', ['estado' => 'entregado', 'tecnico_id' => $tecnico,
        'diagnostico' => 'Revisión y reparación central'], $repC, 403);
    api($t, 'reparaciones', ['estado' => 'cancelado', 'tecnico_id' => $tecnico], $repC, 403);
    $recibida = $db->query("SELECT * FROM reparaciones WHERE id_reparacion = $repC")->fetch();
    verificar((int)$recibida['sucursal_id'] === $branchC && (int)$recibida['usuario_id'] === $recepcionC
        && (int)$recibida['equipo_id'] === $equipo, 'El seguimiento central conserva origen, receptor y equipo');
    $history = pedir($t, "/api/reparaciones/$repC/historial");
    verificar(
        $history['status'] === 200 && count($history['json']['registros']) === 5,
        'Técnico consulta el historial de otra sucursal'
    );
    verificar(
        $history['json']['registros'][4]['sucursal'] === 'Sucursal B'
        && $history['json']['registros'][4]['sucursal_receptora'] === 'Sucursal C',
        'Historial distingue sucursal del operador y sucursal receptora'
    );
    verificar(
        str_contains(pedir($c, '/')['body'], 'Listo para entrega'),
        'Recepción C ve el seguimiento realizado desde sucursal B'
    );
    api($c, 'reparaciones', ['estado' => 'entregado', 'tecnico_id' => $tecnico,
        'diagnostico' => 'Revisión y reparación central'], $repC);
    verificar(
        count(pedir($c, "/api/reparaciones/$repC/historial")['json']['registros']) === 6,
        'Recepción C entrega y conserva los seis eventos entre sedes'
    );
    api($t, 'reparaciones', ['estado' => 'diagnostico', 'tecnico_id' => $tecnico], $repC, 409);
    verificar(
        str_contains(pedir($c, '/')['body'], 'Entregado'),
        'Actividad de origen incluye el último evento y mantiene el seguimiento'
    );
    api($a, 'inventario', ['producto_id' => $producto, 'sucursal_id' => $branchA, 'cantidad' => 5]);
    $producto2 = api($a, 'productos', ['nombre' => 'Sin stock', 'categoria_id' => $categoria,
        'precio' => '1.00', 'stock_minimo' => 0, 'activo' => 1])['id'];
    api($a, 'ventas', ['sucursal_id' => $branchA, 'metodo_pago' => 'tarjeta', 'detalles' => [
        ['producto_id' => $producto, 'cantidad' => 1], ['producto_id' => $producto2, 'cantidad' => 1]]], null, 409);
    verificar(
        (int)$db->query("SELECT cantidad FROM inventario WHERE sucursal_id = $branchA")->fetchColumn() === 5,
        'Rollback conserva stock al fallar una partida'
    );
    api($a, 'clientes', ['nombre' => '   '], null, 422);
    api($a, 'clientes', ['nombre' => 'Teléfono inválido', 'telefono' => 'abc'], null, 422);
    api($a, 'clientes', ['nombre' => 'Teléfono corto', 'telefono' => '123456'], null, 422);
    api($a, 'clientes', ['nombre' => 'Teléfono válido', 'telefono' => '+52 (55) 1234-5678']);
    api($a, 'clientes', ['nombre' => "Control\x01inválido"], null, 422);
    api($a, 'equipos', ['cliente_id' => $cliente], null, 422);
    api($a, 'usuarios', ['nombre' => 'Blanco', 'apellido' => 'Pruebas', 'correo' => 'blanco@telcom.test',
        'rol' => 'ventas', 'sucursal_id' => $branchA, 'activo' => 1, 'password' => '    '], null, 422);
    $solicitud = ['operacion' => bin2hex(random_bytes(16)), 'sucursal_id' => $branchA,
        'metodo_pago' => 'efectivo', 'detalles' => [['producto_id' => $producto, 'cantidad' => 1]]];
    $primera = api($a, 'ventas', $solicitud);
    $conteoAntes = (int)$db->query('SELECT COUNT(*) FROM ventas')->fetchColumn();
    $repetida = pedir($a, '/api/ventas', 'POST', $solicitud);
    verificar(
        $repetida['status'] === 200 && $repetida['json']['id'] === $primera['id'],
        'Un reenvío de venta devuelve el registro confirmado'
    );
    verificar(
        (int)$db->query('SELECT COUNT(*) FROM ventas')->fetchColumn() === $conteoAntes,
        'Un reenvío no crea otra venta ni descuenta inventario'
    );
    $solicitud['detalles'][0]['cantidad'] = 2;
    api($a, 'ventas', $solicitud, null, 409);
    verificar(pedir($a, '/ventas?sucursal=')['status'] === 200, 'Consulta global explícita de ventas');
    $global = pedir($a, '/ventas?sucursal=')['body'];
    verificar(str_contains($global, 'Consulta de otras sucursales.'), 'Consulta global no permite captura de ventas');
    verificar(
        preg_match('~href="/ventas/exportar\.xlsx\?[^"]*sucursal=&amp;~', $global) === 1,
        'El enlace Excel conserva la consulta global explícita de ventas'
    );
    $csv = pedir($a, '/reportes/exportar.csv');
    verificar($csv['status'] === 200 && str_contains($csv['body'], 'Ventas por sucursal'), 'Reporte CSV real');
    $page = pedir($a, '/clientes-equipos?q=%3Cscript%3E')['body'];
    verificar(
        !str_contains($page, '<script>Cliente</script>') && str_contains($page, '&lt;script&gt;'),
        'Escape HTML de filtros y registros'
    );
    verificar(
        !str_contains(pedir($a, '/usuarios-sucursales')['body'], 'password_hash'),
        'La interfaz no expone hashes'
    );
    api($a, 'usuarios', ['nombre' => 'Ventas', 'apellido' => 'Pruebas', 'correo' => 'ventas@telcom.test',
        'rol' => 'ventas', 'sucursal_id' => $branchB, 'activo' => 0], $cuentas['ventas']);
    verificar(pedir($v, '/api/ventas/' . $idVenta)['status'] === 401, 'Desactivar una cuenta revoca sus sesiones');
    verificar(pedir($b, '/api/sesion', 'DELETE', new stdClass())['status'] === 200, 'Cierre real de sesión');
    verificar(pedir($b, '/')['status'] === 302, 'Una sesión cerrada no consulta datos');
    $db->beginTransaction();
    $q = $db->prepare('INSERT INTO clientes (nombre) VALUES (?)');
    for ($i = 0; $i < 101; $i++) {
        $q->execute(['Paginado ' . str_pad((string)$i, 3, '0', STR_PAD_LEFT)]);
    }
    $db->commit();
    $pag1 = pedir($a, '/clientes-equipos?q=Paginado')['body'];
    $pag2 = pedir($a, '/clientes-equipos?q=Paginado&pagina=2')['body'];
    verificar(
        str_contains($pag1, 'Siguiente') && str_contains($pag2, 'Paginado 100'),
        'Paginación accesible después de cien registros'
    );
    $xlsx = libro($a, '/clientes-equipos/exportar.xlsx?q=Paginado&pagina=2');
    verificar(count($xlsx['Clientes']) === 102, 'Excel incluye los 101 clientes y no sólo una página');
    verificar(count($xlsx['Equipos']) === 1, 'Hoja sin coincidencias conserva encabezados');
    $formulaCliente = api($a, 'clientes', ['nombre' => '=SUM(1,1)', 'telefono' => '+52 5512345678'])['id'];
    api($a, 'equipos', ['cliente_id' => $formulaCliente, 'marca' => 'Marca', 'modelo' => 'Modelo',
        'imei' => '001234567890123', 'estado_fisico' => 'Sin daños']);
    $xlsx = libro($a, '/clientes-equipos/exportar.xlsx?q=%3DSUM');
    verificar(
        $xlsx['Clientes'][1][1] === '=SUM(1,1)' && $xlsx['Clientes'][1][2] === '+52 5512345678',
        'Nombre y teléfono conservan texto literal'
    );
    verificar($xlsx['Equipos'][1][4] === '001234567890123', 'IMEI conserva ceros iniciales');
    $xlsx = libro($t, "/reparaciones/exportar.xlsx?sucursal=$branchC&tecnico=$tecnico&estado=entregado");
    verificar(
        count($xlsx['Reparaciones']) === 2 && $xlsx['Reparaciones'][1][0] === $folioC,
        'Exportación técnica aplica sucursal, técnico y estado'
    );
    verificar(count($xlsx['Historial']) === 7 && $xlsx['Historial'][2][2] === 'Sucursal C'
        && $xlsx['Historial'][2][3] === 'Sucursal B', 'Excel conserva trazabilidad entre sucursales');
    $fechaEsperada = (new DateTimeImmutable($recibida['fecha_recepcion'], new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i');
    verificar(
        $xlsx['Reparaciones'][1][12] instanceof DateTimeInterface
        && $xlsx['Reparaciones'][1][12]->format('Y-m-d H:i') === $fechaEsperada,
        'Fechas Excel son fechas reales con hora local'
    );
    $xlsx = libro($c, '/reparaciones/exportar.xlsx');
    verificar(
        count($xlsx['Reparaciones']) === 2 && $xlsx['Reparaciones'][1][5] === 'Sucursal C',
        'Recepción exporta exclusivamente su sucursal'
    );
    verificar(
        pedir($c, "/reparaciones/exportar.xlsx?sucursal=$branchA")['status'] === 400,
        'Excel rechaza ampliación de sucursal no autorizada'
    );
    foreach (['ventas', 'inventario', 'reportes'] as $restringida) {
        verificar(
            pedir($t, "/$restringida/exportar.xlsx")['status'] === 403,
            "Técnico no descarga datos restringidos: $restringida"
        );
    }
    $xlsx = libro($a, "/inventario/exportar.xlsx?sucursal=$branchA");
    verificar(is_numeric($xlsx['Productos'][1][4]), 'Precios Excel son celdas numéricas');
    foreach (array_slice($xlsx['Existencias'], 1) as $row) {
        verificar($row[3] === 'Sucursal A', 'Inventario Excel respeta la sucursal seleccionada');
    }
    $xlsx = libro($a, '/ventas/exportar.xlsx?sucursal=');
    $sum = 0;
    foreach (array_slice($xlsx['Ventas'], 1) as $row) {
        $sum += (int)round($row[7] * 100);
    }
    $detalleSum = 0;
    foreach (array_slice($xlsx['Detalle de ventas'], 1) as $row) {
        $detalleSum += (int)round($row[6] * 100);
        verificar(
            (int)round($row[5] * 100) * $row[4] === (int)round($row[6] * 100),
            'Detalle Excel conserva precio y subtotal exactos'
        );
    }
    verificar($sum === $detalleSum && $sum > 0, 'Totales Excel coinciden con sus partidas');
    $xlsx = libro($a, '/reportes/exportar.xlsx');
    verificar((int)round($xlsx['Resumen'][1][1] * 100) === $sum, 'Reporte Excel coincide con ventas del periodo');
    verificar(count($xlsx) === 5, 'Reporte incluye criterios, resumen y tres desgloses');
    $invalido = pedir($a, '/ventas/exportar.xlsx?desde=2026-02-31');
    verificar(
        $invalido['status'] === 400 && !str_starts_with($invalido['body'], 'PK'),
        'Filtros inválidos no producen una descarga corrupta'
    );
    $anonimo = [];
    verificar(
        pedir($anonimo, '/reparaciones/exportar.xlsx')['status'] === 302,
        'Las descargas requieren autenticación'
    );
    api(
        $a,
        'usuarios',
        ['nombre' => 'Ventas dos', 'apellido' => 'Pruebas', 'correo' => 'ventas2@telcom.test',
        'password' => $password . '-cambio', 'rol' => 'ventas', 'sucursal_id' => $branchB, 'activo' => 1],
        $cuentas['ventas2']
    );
    verificar(
        pedir($v2, '/api/ventas/' . $idVenta)['status'] === 401,
        'Cambiar la contraseña revoca sesiones anteriores'
    );
    api($a, 'sucursales', ['nombre' => 'Sucursal B', 'activo' => 0], $branchB);
    verificar(pedir($t, '/')['status'] === 302, 'Desactivar la sucursal revoca acceso de sus cuentas');
    api($a, 'usuarios', ['nombre' => 'Técnico', 'apellido' => 'Pruebas', 'correo' => 'tecnico@telcom.test',
        'rol' => 'tecnico', 'sucursal_id' => $branchB, 'activo' => 0], $cuentas['tecnico']);

    api($a, 'sucursales', ['nombre' => 'Sucursal B', 'activo' => 1], $branchB);
    $limite = [];
    pedir($limite, '/acceso');
    for ($i = 0; $i < 11; $i++) {
        $r = pedir($limite, '/api/sesion', 'POST', ['correo' => 'nadie@telcom.test', 'password' => 'incorrecta']);
    }
    verificar($r['status'] === 429, 'Límite de intentos de acceso');
    echo "COMPLETADO: $checks comprobaciones de backend sobre base aislada.\n";
} finally {
    foreach ($servidores as $server) {
            proc_terminate($server);
            proc_close($server);
    }
}
