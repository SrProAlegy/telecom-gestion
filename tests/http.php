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
