<?php

declare(strict_types=1);

namespace Telcom\Modelos;

use PDO;
use Telcom\Soporte\ErrorOperacion;
use Telcom\Soporte\Sesion;

final class Autenticacion
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function entrar(array $datos): void
    {
        $correo = $datos['correo'] ?? null;
        $password = $datos['password'] ?? null;
        if (
            !is_string($correo) || strlen($correo) > 254 || !filter_var($correo, FILTER_VALIDATE_EMAIL)
            || !is_string($password) || strlen($password) > 1024 || str_contains($password, "\0")
        ) {
            throw new ErrorOperacion('Correo o contraseña incorrectos.', 401);
        }
        $directorio = sys_get_temp_dir() . '/telcom-acceso-' . substr(hash('sha256', getenv('DB_DATABASE')), 0, 12);
        if (!is_dir($directorio) && !mkdir($directorio, 0700, true) && !is_dir($directorio)) {
            throw new \RuntimeException('No se pudo preparar el control de acceso.');
        }
        $archivo = $directorio . '/' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
        $lock = fopen($archivo, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('No se pudo bloquear el control de acceso.');
        }
        try {
            $intentos = json_decode(stream_get_contents($lock), true) ?: [];
            $intentos = array_values(array_filter($intentos, static fn ($t) => is_int($t) && $t > time() - 300));
            if (count($intentos) >= 10) {
                header('Retry-After: 300');
                throw new ErrorOperacion('Espera unos minutos antes de volver a intentarlo.', 429);
            }
            $q = $this->db->prepare('SELECT u.* FROM usuarios u JOIN sucursales s ON s.id_sucursal = u.sucursal_id
                WHERE u.correo = ? AND u.activo = 1 AND s.activo = 1');
            $q->execute([strtolower(trim($correo))]);
            $u = $q->fetch();
            $valida = password_verify($password, $u['password_hash']
                ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (!$u || !$valida) {
                $intentos[] = time();
                rewind($lock);
                ftruncate($lock, 0);
                fwrite($lock, json_encode($intentos));
                throw new ErrorOperacion('Correo o contraseña incorrectos.', 401);
            }
            ftruncate($lock, 0);
            Sesion::entrar($u);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
