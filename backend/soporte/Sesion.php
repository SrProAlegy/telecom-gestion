<?php

declare(strict_types=1);

namespace Telcom\Soporte;

use PDO;

final class Sesion
{
    public const PAGINAS = [
        'administrador' => ['inicio', 'clientes', 'reparaciones', 'inventario', 'ventas', 'reportes', 'usuarios'],
        'recepcion' => ['inicio', 'clientes', 'reparaciones', 'inventario', 'reportes'],
        'tecnico' => ['inicio', 'clientes', 'reparaciones'],
        'ventas' => ['inicio', 'clientes', 'inventario', 'ventas', 'reportes'],
    ];
    public static function iniciar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('telcom_sesion');
            session_start(['cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        }
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function token(): string
    {
        return $_SESSION['csrf'];
    }

    public static function csrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals(self::token(), $token)) {
            throw new ErrorOperacion('La sesión del formulario venció. Recarga la página.', 419);
        }
    }

    public static function usuario(PDO $db): ?array
    {
        if (empty($_SESSION['usuario_id'])) {
            return null;
        }
        if (time() - ($_SESSION['actividad'] ?? 0) > 1800 || time() - ($_SESSION['inicio'] ?? 0) > 28800) {
            self::salir();
            return null;
        }
        $q = $db->prepare('SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.rol, u.sucursal_id,
            u.password_hash, s.nombre AS sucursal_nombre FROM usuarios u
            JOIN sucursales s ON s.id_sucursal = u.sucursal_id
            WHERE u.id_usuario = ? AND u.activo = 1 AND s.activo = 1');
        $q->execute([$_SESSION['usuario_id']]);
        $u = $q->fetch();
        if (!$u || !hash_equals($_SESSION['firma'] ?? '', hash('sha256', $u['password_hash']))) {
            self::salir();
            return null;
        }
        unset($u['password_hash']);
        $_SESSION['actividad'] = time();
        return $u;
    }

    public static function entrar(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION = ['usuario_id' => (int)$u['id_usuario'], 'firma' => hash('sha256', $u['password_hash']),
            'inicio' => time(), 'actividad' => time(), 'csrf' => bin2hex(random_bytes(32))];
    }

    public static function salir(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function pagina(string $pagina, ?array $u): bool
    {
        return $u !== null && in_array($pagina, self::PAGINAS[$u['rol']] ?? [], true);
    }

    public static function sucursalesCompartidas(string $pagina, array $u): bool
    {
        return $u['rol'] === 'administrador'
            || ($u['rol'] === 'tecnico' && in_array($pagina, ['inicio', 'reparaciones'], true));
    }

    public static function verOperacion(string $recurso, array $u, int $sucursal): bool
    {
        return self::pagina($recurso, $u)
            && (self::sucursalesCompartidas($recurso, $u) || (int)$u['sucursal_id'] === $sucursal);
    }

    public static function permite(string $recurso, ?array $u, bool $edicion = false): bool
    {
        $rol = $u['rol'] ?? '';
        if ($rol === 'administrador') {
            return true;
        }
        return match ($recurso) {
            'clientes', 'equipos' => in_array($rol, ['recepcion', 'ventas'], true),
            'reparaciones' => $rol === 'recepcion' || ($edicion && $rol === 'tecnico'),
            'ventas' => $rol === 'ventas' && !$edicion,
            default => false,
        };
    }
}
