<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
use Telcom\Soporte\Entrada;
use Telcom\Soporte\ErrorOperacion;
$opciones = getopt('', ['correo:', 'nombre:', 'apellido:', 'sucursal:', 'password-stdin']);
$db = null;
try {
    $e = new Entrada($opciones);
    $correo = $e->correo(true);
    $nombre = $e->texto('nombre', 100, true);
    $apellido = $e->texto('apellido', 150, true);
    $sucursal = $e->texto('sucursal', 120, true);
    $ocultar = !array_key_exists('password-stdin', $opciones);
    if ($ocultar) {
        fwrite(STDOUT, 'Contraseña del administrador (4–72 bytes): ');
        system('stty -echo');
    }
    try {
        $password = rtrim(fgets(STDIN, 1026) ?: '', "\r\n");
    } finally {
        if ($ocultar) {
            system('stty echo');
            fwrite(STDOUT, "\n");
        }
    }
    $hash = (new Entrada(['password' => $password]))->password();
    unset($password);
    $db = require __DIR__ . '/config/database.php';
    $db->beginTransaction();
    $db->query('SELECT version FROM schema_version WHERE version = 1 FOR UPDATE');
    if ((int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() !== 0) {
        throw new RuntimeException('Ya existen usuarios. Administra las cuentas desde la aplicación.');
    }
    $q = $db->prepare('SELECT id_sucursal FROM sucursales WHERE nombre = ? AND activo = 1
        ORDER BY id_sucursal LIMIT 1');
    $q->execute([$sucursal]);
    $id = $q->fetchColumn();
    if ($id === false) {
        $q = $db->prepare('INSERT INTO sucursales (nombre) VALUES (?)');
        $q->execute([$sucursal]);
        $id = $db->lastInsertId();
    }
    $q = $db->prepare("INSERT INTO usuarios (nombre, apellido, correo, password_hash, rol, sucursal_id)
        VALUES (?, ?, ?, ?, 'administrador', ?)");
    $q->execute([$nombre, $apellido, $correo, $hash, $id]);
    $db->commit();
    fwrite(STDOUT, "Administrador inicial creado. Ya puedes iniciar sesión.\n");
} catch (Throwable $error) {
    if ($db?->inTransaction()) {
        $db->rollBack();
    }
    $mensaje = $error instanceof ErrorOperacion
        ? implode(' ', $error->campos) : ($error instanceof PDOException
            ? 'No se pudo crear la cuenta.' : $error->getMessage());
    fwrite(STDERR, $mensaje . "\n");
    exit(1);
}
