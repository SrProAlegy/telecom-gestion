<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(__DIR__ . '/../frontend/public');
$file = realpath($public . '/' . rawurldecode($path));
if ($file && str_starts_with($file, $public . '/') && is_file($file)) {
    return false;
}
require $public . '/index.php';
