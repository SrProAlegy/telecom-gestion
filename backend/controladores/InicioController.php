<?php

declare(strict_types=1);

namespace Telcom\Controladores;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Telcom\Modelos\Sistema;
use Telcom\Modelos\Exportaciones;
use Telcom\Soporte\Excel;
use Telcom\Soporte\ErrorOperacion;
use Throwable;
use Telcom\Soporte\Sesion;

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

    public function exportar(): void
    {
        $this->mostrar('reportes', 'csv');
    }

    public function excelClientes(): void
    {
        $this->mostrar('clientes', 'xlsx');
    }
    public function excelReparaciones(): void
    {
        $this->mostrar('reparaciones', 'xlsx');
    }
    public function excelInventario(): void
    {
        $this->mostrar('inventario', 'xlsx');
    }
    public function excelVentas(): void
    {
        $this->mostrar('ventas', 'xlsx');
    }
    public function excelReportes(): void
    {
        $this->mostrar('reportes', 'xlsx');
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

    private function mostrar(string $pagina, string $formato = 'html'): void
    {
        Sesion::iniciar();
        $usuarioActual = null;
        $csrfToken = Sesion::token();
        $aviso = $_SESSION['aviso'] ?? '';
        unset($_SESSION['aviso']);
        $ready = false;
        $datos = [];
        $hayMas = false;
        $catalogos = ['sucursales' => [], 'estados' => []];
        $filtro = [];
        $error = '';
        try {
            $db = require dirname(__DIR__) . '/config/database.php';
            $usuarioActual = Sesion::usuario($db);
            $csrfToken = Sesion::token();
            $GLOBALS['usuarioSesion'] = $usuarioActual;
            if ($pagina !== 'acceso' && $usuarioActual === null) {
                header('Location: /acceso', true, 302);
                return;
            }
            if ($pagina !== 'acceso' && !Sesion::pagina($pagina, $usuarioActual)) {
                http_response_code(403);
                echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Acceso restringido</title>'
                    . '<h1>Acceso restringido</h1><p>Tu cuenta no tiene acceso a esta sección.</p>'
                    . '<a href="/">Volver al inicio</a></html>';
                return;
            }
            if ($pagina === 'acceso' && $usuarioActual !== null) {
                header('Location: /', true, 302);
                return;
            }
            $sistema = new Sistema($db);
            $ready = $sistema->comprobar();
            if (!$ready) {
                throw new \RuntimeException('Esquema no disponible.');
            }
            if ($pagina !== 'acceso') {
                $catalogos = $sistema->catalogos();
                $ambitoCompartido = Sesion::sucursalesCompartidas($pagina, $usuarioActual);
                if (!$ambitoCompartido) {
                    $catalogos['sucursales'] = array_values(array_filter(
                        $catalogos['sucursales'],
                        static fn ($s) => (int)$s['id_sucursal'] === (int)$usuarioActual['sucursal_id']
                    ));
                }
                $filtro = $this->filtros($pagina, $catalogos);
                if (
                    !$ambitoCompartido || ($pagina === 'ventas' && !isset($_GET['sucursal']))
                ) {
                    $filtro['sucursal'] ??= (int)$usuarioActual['sucursal_id'];
                }
                if ($ambitoCompartido && !isset($_GET['sucursal']) && $pagina !== 'ventas') {
                    $filtro['sucursal'] = null;
                }
                $filtro['solo_tecnico'] = $usuarioActual['rol'] === 'tecnico';
                if ($formato === 'xlsx') {
                    $this->descargarExcel($db, $pagina, $filtro, $catalogos);
                    return;
                }
                $datos = $sistema->consultar($pagina, $filtro);
                $listas = match ($pagina) {
                    'clientes' => ['clientes', 'equipos'], 'inventario' => ['productos'],
                    'reparaciones', 'ventas', 'usuarios' => ['filas'], default => [],
                };
                foreach ($listas as $lista) {
                    if (count($datos[$lista]) > 100) {
                        $hayMas = true;
                        $datos[$lista] = array_slice($datos[$lista], 0, 100);
                    }
                }
            }
            http_response_code(200);
        } catch (ErrorOperacion $exception) {
            $error = $exception->getMessage();
            http_response_code($exception->estado);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            http_response_code(400);
        } catch (Throwable $exception) {
            error_log('Telcom: consulta fallida (' . get_class($exception) . ').');
            $ready = false;
            $error = 'No se pudo consultar la información. Comprueba la conexión e intenta de nuevo.';
            http_response_code(503);
        }
        if ($formato === 'csv' && $ready && $error === '') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=telcom-reporte.csv');
            $archivo = fopen('php://output', 'w');
            fwrite($archivo, "\xEF\xBB\xBF");
            $escribir = static function (array $fila) use ($archivo): void {
                $fila = array_map(static function ($v): string {
                    $v = (string)($v ?? '');
                    return preg_match('/\A[\s]*[=+@-]/u', $v) ? "'" . $v : $v;
                }, $fila);
                fputcsv($archivo, $fila, ',', '"', '', "\r\n");
            };
            $escribir(['Periodo local', $filtro['desde'], $filtro['hasta']]);
            $escribir(['Ventas por sucursal', 'Operaciones', 'Importe']);
            foreach ($datos['sucursales'] as $r) {
                $escribir([$r['nombre'], $r['cantidad'], $r['total']]);
            }
            $escribir(['Reparaciones por estado', 'Cantidad']);
            foreach ($datos['estados'] as $r) {
                $escribir([$r['nombre'], $r['cantidad']]);
            }
            $escribir(['Stock bajo actual', 'Sucursal', 'Cantidad', 'Mínimo']);
            foreach ($datos['stock'] as $r) {
                $escribir([$r['nombre'], $r['sucursal'], $r['cantidad'], $r['stock_minimo']]);
            }
            fclose($archivo);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        require_once dirname(__DIR__, 2) . '/frontend/vistas/funciones.php';
        require dirname(__DIR__, 2) . '/frontend/vistas/plantilla.php';
    }

    private function descargarExcel(\PDO $db, string $pagina, array $filtro, array $catalogos): void
    {
        $criterios = ['Sección' => $pagina,
            'Generado' => (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))
                ->format('d/m/Y H:i:s'),
            'Zona horaria' => 'America/Mexico_City', 'Alcance' => 'Todos los registros que coinciden con los filtros'];
        if ($pagina !== 'clientes') {
            $criterios['Sucursal'] = 'Todas las sucursales registradas';
            foreach ($catalogos['sucursales'] as $s) {
                if ((int)$s['id_sucursal'] === $filtro['sucursal']) {
                    $criterios['Sucursal'] = $s['nombre'];
                }
            }
        }
        if (in_array($pagina, ['clientes', 'reparaciones', 'inventario'], true)) {
            $criterios['Búsqueda'] = $filtro['q'] === '' ? 'Sin restricción' : $filtro['q'];
        }
        if ($pagina === 'reparaciones') {
            $criterios['Estado'] = 'Todos los estados';
            foreach ($catalogos['estados'] as $e) {
                if ($e['codigo'] === $filtro['estado']) {
                    $criterios['Estado'] = $e['nombre'];
                }
            }
            $criterios['Técnico'] = $filtro['tecnico'] === 'sin_asignar' ? 'Sin asignar' : 'Todos los técnicos';
            foreach ($catalogos['tecnicos'] as $t) {
                if ((string)$t['id_tecnico'] === $filtro['tecnico']) {
                    $criterios['Técnico'] = $t['nombre'] . ' ' . $t['apellido'];
                }
            }
        }
        if (in_array($pagina, ['ventas', 'reportes'], true)) {
            $criterios['Fecha inicial'] = $filtro['desde'];
            $criterios['Fecha final inclusiva'] = $filtro['hasta'];
        }
        if (in_array($pagina, ['inventario', 'reportes'], true)) {
            $criterios['Inventario'] = 'Existencias actuales; no representa un corte histórico';
        }
        $archivo = null;
        try {
            $db->exec('SET TRANSACTION READ ONLY');
            $db->beginTransaction();
            $archivo = (new Excel())->crear((new Exportaciones($db))->hojas($pagina, $filtro), $criterios);
            $db->commit();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="telcom-' . $pagina . '.xlsx"');
            header('Content-Length: ' . filesize($archivo));
            header('Cache-Control: no-store');
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
                readfile($archivo);
            }
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($archivo !== null && is_file($archivo)) {
                unlink($archivo);
            }
        }
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
        $paginaLista = $texto('pagina', '1');
        if (!ctype_digit($paginaLista) || (int)$paginaLista < 1 || (int)$paginaLista > 1000000) {
            throw new InvalidArgumentException('La página de resultados no es válida.');
        }
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
        $tecnico = $pagina === 'reparaciones' ? $texto('tecnico') : '';
        if ($tecnico !== '' && $tecnico !== 'sin_asignar') {
            if (
                !ctype_digit($tecnico)
                || !in_array((int)$tecnico, array_column($catalogos['tecnicos'], 'id_tecnico'), true)
            ) {
                throw new InvalidArgumentException('Selecciona un técnico válido.');
            }
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
            'pagina' => (int)$paginaLista, 'tecnico' => $tecnico, 'sucursal' => $sucursal,
            'estado' => $estado, 'activo' => $activo,
                'q' => $texto('q'),
            'desde' => $desde, 'hasta' => $hasta,
            'desde_utc' => $fechaInicio->setTimezone($utc)->format('Y-m-d H:i:s'),
            'hasta_utc' => $fechaFin->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }
}
