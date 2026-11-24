<?php

declare(strict_types=1);

namespace Telcom\Soporte;

final class Entrada
{
    public function __construct(private array $datos)
    {
    }

    public function tiene(string $campo): bool
    {
        return array_key_exists($campo, $this->datos);
    }

    public function texto(string $campo, int $max, bool $requerido = false): ?string
    {
        $v = $this->datos[$campo] ?? null;
        if ($v === null && !$requerido) {
            return null;
        }
        if (!is_string($v) || !preg_match('//u', $v)) {
            $this->error($campo, 'Indica un texto válido.');
        }
        $v = trim($v);
        preg_match_all('/./us', $v, $chars);
        if (
            ($requerido && $v === '') || count($chars[0]) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v)
            || ($max < 10000 && preg_match('/[\r\n\t]/', $v))
        ) {
            $this->error($campo, "Revisa este campo (máximo $max caracteres).");
        }
        return $v === '' ? null : $v;
    }

    public function entero(string $campo, int $min = 1, int $max = 4294967295, bool $opcional = false): ?int
    {
        $v = $this->datos[$campo] ?? null;
        if ($v === null && $opcional) {
            return null;
        }
        if (!is_int($v) || $v < $min || $v > $max) {
            $this->error($campo, "Indica un número entero entre $min y $max.");
        }
        return $v;
    }

    public function telefono(): ?string
    {
        $v = $this->texto('telefono', 25);
        if ($v === null) {
            return null;
        }
        $digitos = preg_replace('/[^0-9]/', '', $v);
        if (
            !preg_match('/\A\+?[0-9(][0-9 ().-]*\z/', $v)
            || strlen($digitos) < 7 || strlen($digitos) > 15
        ) {
            $this->error(
                'telefono',
                'Indica de 7 a 15 dígitos; puedes usar +, espacios, paréntesis, puntos o guiones.'
            );
        }
        return $v;
    }

    public function correo(bool $requerido = false): ?string
    {
        $v = $this->texto('correo', 254, $requerido);
        if ($v !== null && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->error('correo', 'Indica un correo electrónico válido.');
        }
        return $v === null ? null : strtolower($v);
    }

    public function opcion(string $campo, array $opciones): string
    {
        $v = $this->texto($campo, 50, true);
        if (!in_array($v, $opciones, true)) {
            $this->error($campo, 'Selecciona una opción válida.');
        }
        return $v;
    }

    public function password(): string
    {
        $v = $this->datos['password'] ?? null;
        if (!is_string($v) || strlen($v) < 4 || trim($v) === '' || strlen($v) > 72 || str_contains($v, "\0")) {
            $this->error('password', 'Usa entre 4 y 72 bytes para la contraseña.');
        }
        return password_hash($v, PASSWORD_DEFAULT);
    }

    public function precio(): string
    {
        $v = $this->datos['precio'] ?? null;
        if (!is_string($v) || !preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/', $v)) {
            $this->error('precio', 'Indica un precio válido con hasta dos decimales.');
        }
        [$a, $b] = array_pad(explode('.', $v), 2, '');
        return (string)(int)$a . '.' . str_pad($b, 2, '0');
    }

    public function lista(string $campo): array
    {
        $v = $this->datos[$campo] ?? null;
        if (!is_array($v) || !array_is_list($v) || count($v) < 1 || count($v) > 100) {
            $this->error($campo, 'Incluye entre 1 y 100 partidas.');
        }
        return $v;
    }

    public function error(string $campo, string $mensaje): never
    {
        throw new ErrorOperacion('Revisa los datos del formulario.', 422, [$campo => $mensaje]);
    }
}
