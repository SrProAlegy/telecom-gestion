<?php

declare(strict_types=1);

$request = static function (string $path, string $method = 'GET'): array {
    $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 5]]);
    $body = file_get_contents('http://127.0.0.1' . $path, false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int) $match[1], $body];
};
$cases = [
    ['/', 'GET', 200],
    ['/health', 'GET', 200],
    ['/clientes-equipos', 'GET', 200],
    ['/reparaciones', 'GET', 200],
    ['/inventario', 'GET', 200],
    ['/ventas', 'GET', 200],
    ['/reportes', 'GET', 200],
    ['/usuarios-sucursales', 'GET', 200],
    ['/acceso', 'GET', 200],
    ['/acceso', 'POST', 405],
    ['/ventas', 'POST', 405],
    ['/reparaciones?estado=desconocido', 'GET', 400],
    ['/reportes?desde=2026-02-31', 'GET', 400],
    ['/reportes?desde=2026-11-25&hasta=2026-11-01', 'GET', 400],
    ['/reportes?desde[]=2026-11-01', 'GET', 400],
    ['/usuarios-sucursales?activo=2', 'GET', 400],
    ['/inventario?sucursal=0', 'GET', 400],
    ['/inventario?sucursal=4294967295', 'GET', 400],
    ['/reparaciones?estado=diagnostico', 'GET', 200],
    ['/clientes-equipos?q=%27%20OR%201%3D1--', 'GET', 200],
    ['/frontend/vistas/plantilla.php', 'GET', 404],
    ['/assets/css/app.css', 'GET', 200],
    ['/assets/js/app.js', 'GET', 200],
    ['/missing', 'GET', 404],
    ['/health', 'POST', 405],
    ['/.env', 'GET', 403],
    ['/backend/config/database.php', 'GET', 404],
    ['/frontend/vistas/inicio.php', 'GET', 404],
    ['/tests/herramientas/composer.json', 'GET', 404],
    ['/database/schema.sql', 'GET', 404],
    ['/assets/bootstrap/bootstrap.min.css', 'GET', 200],
];
foreach ($cases as [$path, $method, $expected]) {
    [$code, $body] = $request($path, $method);
    if ($code !== $expected) {
        throw new RuntimeException("$method $path: esperado $expected, obtenido $code");
    }
    if ($path === '/health' && $method === 'GET' && (json_decode($body, true)['status'] ?? '') !== 'ok') {
        throw new RuntimeException('Health no disponible');
    }
    echo "OK: $method $path -> $code\n";
}

[$code, $body] = $request('/clientes-equipos?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E');
if ($code !== 200 || str_contains($body, '<script>alert(1)</script>')) {
    throw new RuntimeException('La búsqueda no escapó la entrada de texto.');
}
if (!str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt;')) {
    throw new RuntimeException('El filtro de búsqueda no se conservó con escape HTML.');
}
echo "OK: búsqueda con escape HTML y consultas preparadas.\n";
