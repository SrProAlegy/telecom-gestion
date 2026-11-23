<?php

declare(strict_types=1);

namespace Telcom\Controladores;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Telcom\Modelos\Sistema;
use Throwable;

final class InicioController
{
    public function inicio(): void
    {
        $this->mostrar('inicio');
    }

    public function clientes(): void
    {
        $this->mostrar('clientes');
    }

    public function reparaciones(): void
    {
        $this->mostrar('reparaciones');
    }

    public function inventario(): void
    {
        $this->mostrar('inventario');
    }

    public function ventas(): void
    {
        $this->mostrar('ventas');
    }

    public function reportes(): void
    {
        $this->mostrar('reportes');
    }

    public function usuarios(): void
    {
        $this->mostrar('usuarios');
    }

    public function acceso(): void
    {
        $this->mostrar('acceso');
    }

    public function estado(): void
    {
        try {
            $db = require dirname(__DIR__) . '/config/database.php';
            $ready = (new Sistema($db))->comprobar();
        } catch (Throwable $error) {
            error_log('Telcom: comprobación fallida (' . get_class($error) . ').');
            $ready = false;
        }
        http_response_code($ready ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => $ready ? 'ok' : 'unavailable', 'database' => $ready ? 'ready' : 'unavailable']);
    }

    private function mostrar(string $pagina): void
    {
        $ready = false;
        $datos = [];
        $catalogos = ['sucursales' => [], 'estados' => []];
        $filtro = [];
        $error = '';
        try {
            $db = require dirname(__DIR__) . '/config/database.php';
            $sistema = new Sistema($db);
            $ready = $sistema->comprobar();
            if (!$ready) {
                throw new \RuntimeException('Esquema no disponible.');
            }
            $catalogos = $sistema->catalogos();
            $filtro = $this->filtros($pagina, $catalogos);
            $datos = $sistema->consultar($pagina, $filtro);
            http_response_code(200);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            http_response_code(400);
        } catch (Throwable $exception) {
            error_log('Telcom: consulta fallida (' . get_class($exception) . ').');
            $ready = false;
            $error = 'No se pudo consultar la información. Comprueba la conexión e intenta de nuevo.';
            http_response_code(503);
        }
        header('Content-Type: text/html; charset=utf-8');
        require_once dirname(__DIR__, 2) . '/frontend/vistas/funciones.php';
        require dirname(__DIR__, 2) . '/frontend/vistas/plantilla.php';
    }

    private function filtros(string $pagina, array $catalogos): array
    {
        $texto = static function (string $campo, string $default = ''): string {
            $valor = $_GET[$campo] ?? $default;
            if (!is_string($valor) || preg_match('/\A.{0,200}\z/us', $valor) !== 1) {
                throw new InvalidArgumentException('El filtro enviado no tiene un formato válido.');
            }
            return trim($valor);
        };
        $sucursal = $texto('sucursal');
        if ($sucursal !== '' && (!ctype_digit($sucursal) || (int) $sucursal < 1)) {
            throw new InvalidArgumentException('Selecciona una sucursal válida.');
        }
        $sucursal = $sucursal === '' ? null : (int) $sucursal;
        if ($sucursal !== null && !in_array($sucursal, array_column($catalogos['sucursales'], 'id_sucursal'))) {
            throw new InvalidArgumentException('La sucursal seleccionada no existe.');
        }
        $estado = $texto('estado');
        if ($estado !== '' && !in_array($estado, array_column($catalogos['estados'], 'codigo'), true)) {
            throw new InvalidArgumentException('Selecciona un estado de reparación válido.');
        }
        $activo = $texto('activo');
        if (!in_array($activo, ['', '0', '1'], true)) {
            throw new InvalidArgumentException('Selecciona un estado de cuenta válido.');
        }
        $zona = new DateTimeZone('America/Mexico_City');
        $hoy = new DateTimeImmutable('today', $zona);
        $inicio = $pagina === 'inicio' ? $hoy : $hoy->modify('first day of this month');
        $usaPeriodo = in_array($pagina, ['ventas', 'reportes'], true);
        $desde = $usaPeriodo ? $texto('desde', $inicio->format('Y-m-d')) : $inicio->format('Y-m-d');
        $hasta = $usaPeriodo ? $texto('hasta', $hoy->format('Y-m-d')) : $hoy->format('Y-m-d');
        $fechaInicio = DateTimeImmutable::createFromFormat('!Y-m-d', $desde, $zona);
        $fechaFin = DateTimeImmutable::createFromFormat('!Y-m-d', $hasta, $zona);
        if (
            !$fechaInicio || !$fechaFin || $fechaInicio->format('Y-m-d') !== $desde
            || $fechaFin->format('Y-m-d') !== $hasta || $fechaFin < $fechaInicio
        ) {
            throw new InvalidArgumentException('Indica un periodo válido: la fecha final debe ser igual o posterior.');
        }
        $utc = new DateTimeZone('UTC');
        return [
            'sucursal' => $sucursal, 'estado' => $estado, 'activo' => $activo, 'q' => $texto('q'),
            'desde' => $desde, 'hasta' => $hasta,
            'desde_utc' => $fechaInicio->setTimezone($utc)->format('Y-m-d H:i:s'),
            'hasta_utc' => $fechaFin->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }
}
