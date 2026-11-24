<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/backend/bootstrap.php';
header('Cache-Control: no-store');
$routes = [
    '/' => [Telcom\Controladores\InicioController::class, 'inicio'],
    '/clientes-equipos' => [Telcom\Controladores\InicioController::class, 'clientes'],
    '/reparaciones' => [Telcom\Controladores\InicioController::class, 'reparaciones'],
    '/inventario' => [Telcom\Controladores\InicioController::class, 'inventario'],
    '/ventas' => [Telcom\Controladores\InicioController::class, 'ventas'],
    '/reportes/exportar.csv' => [Telcom\Controladores\InicioController::class, 'exportar'],
    '/reportes' => [Telcom\Controladores\InicioController::class, 'reportes'],
    '/usuarios-sucursales' => [Telcom\Controladores\InicioController::class, 'usuarios'],
    '/acceso' => [Telcom\Controladores\InicioController::class, 'acceso'],
    '/clientes-equipos/exportar.xlsx' => [Telcom\Controladores\InicioController::class, 'excelClientes'],
    '/reparaciones/exportar.xlsx' => [Telcom\Controladores\InicioController::class, 'excelReparaciones'],
    '/inventario/exportar.xlsx' => [Telcom\Controladores\InicioController::class, 'excelInventario'],
    '/ventas/exportar.xlsx' => [Telcom\Controladores\InicioController::class, 'excelVentas'],
    '/reportes/exportar.xlsx' => [Telcom\Controladores\InicioController::class, 'excelReportes'],
    '/health' => [Telcom\Controladores\InicioController::class, 'estado'],
];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (is_string($path) && str_starts_with($path, '/api/')) {
    Telcom\Soporte\Sesion::iniciar();
    (new Telcom\Controladores\ApiController())->atender($path, $requestMethod);
    exit;
}

if (!is_string($path) || !isset($routes[$path])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>No encontrado</title>'
        . '<h1>Página no encontrada</h1><a href="/">Volver al inicio</a></html>';
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
try {
    [$class, $method] = $routes[$path];
    (new $class())->$method();
} catch (Throwable $error) {
    error_log('Telcom: error de aplicación (' . get_class($error) . ').');
    http_response_code(500);
    echo 'No fue posible atender la solicitud. Revisa los registros del servicio.';
}
