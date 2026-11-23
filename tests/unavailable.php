<?php

declare(strict_types=1);

// Ejecutar únicamente con db detenido. El DNS de Docker puede demorar al desaparecer el servicio.
$context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]);
$body = file_get_contents('http://127.0.0.1/health', false, $context);
if (
    !str_contains($http_response_header[0] ?? '', '503')
    || json_decode($body, true) !== ['status' => 'unavailable', 'database' => 'unavailable']
) {
    throw new RuntimeException('Se esperaba un 503 genérico sin detalles internos.');
}
echo "OK: MySQL detenido devuelve 503 con JSON genérico.\n";
