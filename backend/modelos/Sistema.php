<?php

declare(strict_types=1);

namespace Telcom\Modelos;

use PDO;

final class Sistema
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function comprobar(): bool
    {
        $statement = $this->db->prepare('SELECT version FROM schema_version WHERE version = :version');
        $statement->execute(['version' => 1]);
        if ((int) $statement->fetchColumn() !== 1) {
            return false;
        }
        // Detectar también una inicialización parcial sin catálogos básicos.
        return (int) $this->db->query('SELECT COUNT(*) FROM estados_reparacion')->fetchColumn() === 8;
    }
}
