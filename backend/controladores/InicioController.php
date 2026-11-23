<?php

declare(strict_types=1);

namespace Telcom\Controladores;

use Telcom\Modelos\Sistema;
use Throwable;

final class InicioController
{
    public function inicio(): void
    {
        $ready = $this->comprobarConexion();
        http_response_code($ready ? 200 : 503);
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 2) . '/frontend/vistas/inicio.php';
    }

    public function estado(): void
    {
        $ready = $this->comprobarConexion();
        http_response_code($ready ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => $ready ? 'ok' : 'unavailable', 'database' => $ready ? 'ready' : 'unavailable']);
    }

    private function comprobarConexion(): bool
    {
        try {
            $db = require dirname(__DIR__) . '/config/database.php';
            return (new Sistema($db))->comprobar();
        } catch (Throwable $error) {
            error_log('Telcom: comprobación fallida (' . get_class($error) . ').');
            return false;
        }
    }
}
