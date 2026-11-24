<?php

declare(strict_types=1);

$request = static function (string $path, string $method = 'GET'): array {
    $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true,
        'timeout' => 5, 'follow_location' => 0]]);
    $body = file_get_contents('http://127.0.0.1' . $path, false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int) $match[1], $body];
};
$cases = [
    ['/', 'GET', 302],
    ['/health', 'GET', 200],
    ['/clientes-equipos/exportar.xlsx', 'GET', 302],
    ['/reparaciones/exportar.xlsx', 'GET', 302],
    ['/inventario/exportar.xlsx', 'GET', 302],
    ['/ventas/exportar.xlsx', 'GET', 302],
    ['/reportes/exportar.xlsx', 'GET', 302],
    ['/backend/composer.lock', 'GET', 404],
    ['/clientes-equipos', 'GET', 302],
    ['/reparaciones', 'GET', 302],
    ['/inventario', 'GET', 302],
    ['/ventas', 'GET', 302],
    ['/reportes', 'GET', 302],
    ['/usuarios-sucursales', 'GET', 302],
    ['/acceso', 'GET', 200],
    ['/acceso', 'POST', 405],
    ['/ventas', 'POST', 405],
    ['/reparaciones?estado=desconocido', 'GET', 302],
    ['/reportes?desde=2026-02-31', 'GET', 302],
    ['/reportes?desde=2026-11-25&hasta=2026-11-01', 'GET', 302],
    ['/reportes?desde[]=2026-11-01', 'GET', 302],
    ['/usuarios-sucursales?activo=2', 'GET', 302],
    ['/inventario?sucursal=0', 'GET', 302],
    ['/inventario?sucursal=4294967295', 'GET', 302],
    ['/reparaciones?estado=diagnostico', 'GET', 302],
    ['/clientes-equipos?q=%27%20OR%201%3D1--', 'GET', 302],
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

echo "OK: rutas privadas protegidas y recursos públicos disponibles.\n";
