<?php

declare(strict_types=1);

$required = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Configuración de base de datos incompleta.');
    }
    return $value;
};

$config = [
    'host' => $required('DB_HOST'),
    'port' => $required('DB_PORT'),
    'database' => $required('DB_DATABASE'),
    'username' => $required('DB_USERNAME'),
    'password' => $required('DB_PASSWORD'),
];

foreach (['host', 'database'] as $key) {
    if (!preg_match('/\A[a-zA-Z0-9_.-]+\z/', $config[$key])) {
        throw new RuntimeException('Configuración de conexión inválida.');
    }
}
if (!ctype_digit($config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535) {
    throw new RuntimeException('Puerto de conexión inválido.');
}
$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    ),
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]
);
$pdo->exec("SET time_zone = '+00:00'");
return $pdo;
