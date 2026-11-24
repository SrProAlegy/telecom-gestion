<?php

declare(strict_types=1);

namespace Telcom\Soporte;

final class ErrorOperacion extends \RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly int $estado = 422,
        public readonly array $campos = []
    ) {
        parent::__construct($mensaje);
    }
}
