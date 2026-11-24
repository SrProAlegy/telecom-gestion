<?php

declare(strict_types=1);

namespace Telcom\Controladores;

use PDOException;
use Telcom\Modelos\Autenticacion;
use Telcom\Modelos\Operaciones;
use Telcom\Soporte\ErrorOperacion;
use Telcom\Soporte\Sesion;
use Throwable;

final class ApiController
{
    public function atender(string $path, string $metodo): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $db = require dirname(__DIR__) . '/config/database.php';
            $u = Sesion::usuario($db);
            if ($path === '/api/sesion' && in_array($metodo, ['POST', 'DELETE'], true)) {
                Sesion::csrf();
                if ($metodo === 'POST') {
                    (new Autenticacion($db))->entrar($this->datos());
                } else {
                    Sesion::salir();
                }
                $this->responder(['ok' => true, 'redirect' => $metodo === 'POST' ? '/' : '/acceso']);
                return;
            }
            if ($u === null) {
                throw new ErrorOperacion('Inicia sesión para continuar.', 401);
            }
            if ($path === '/api/inventario' && $metodo === 'GET') {
                if (!Sesion::permite('inventario', $u)) {
                    throw new ErrorOperacion('No tienes acceso a esta consulta.', 403);
                }
                $valores = [];
                foreach (['producto_id', 'sucursal_id'] as $campo) {
                    $v = $_GET[$campo] ?? '';
                    if (!is_string($v) || !ctype_digit($v) || (int)$v < 1 || (int)$v > 4294967295) {
                        throw new ErrorOperacion('Selecciona un producto y una sucursal válidos.', 422);
                    }
                    $valores[] = (int)$v;
                }
                $q = $db->prepare('SELECT cantidad FROM inventario WHERE producto_id = ? AND sucursal_id = ?');
                $q->execute($valores);
                $cantidad = $q->fetchColumn();
                $this->responder(['ok' => true, 'cantidad' => $cantidad === false ? 0 : (int)$cantidad]);
                return;
            }
            $patronDetalle = '#\A/api/(reparaciones|ventas)/([1-9][0-9]{0,9})(/historial)?\z#';
            if ($metodo === 'GET' && preg_match($patronDetalle, $path, $m)) {
                $tipo = $m[1];
                if (
                    ($tipo === 'reparaciones' && ($m[3] ?? '') !== '/historial')
                    || ($tipo === 'ventas' && isset($m[3]))
                ) {
                    throw new ErrorOperacion('Recurso no encontrado.', 404);
                }
                if (!Sesion::pagina($tipo, $u)) {
                    throw new ErrorOperacion('No tienes acceso a esta consulta.', 403);
                }
                $pk = $tipo === 'ventas' ? 'id_venta' : 'id_reparacion';
                $q = $db->prepare("SELECT sucursal_id FROM $tipo WHERE $pk = ?");
                $q->execute([(int)$m[2]]);
                $sucursal = $q->fetchColumn();
                if (
                    $sucursal === false || !Sesion::verOperacion($tipo, $u, (int)$sucursal)
                ) {
                    throw new ErrorOperacion('Registro no encontrado.', 404);
                }
                $sql = $tipo === 'ventas'
                ? 'SELECT p.nombre AS producto, d.cantidad, d.precio_unitario, d.subtotal
                        FROM detalle_venta d JOIN productos p ON p.id_producto = d.producto_id
                        WHERE d.venta_id = ? ORDER BY d.id_detalle'
                : 'SELECT er.nombre AS estado, h.diagnostico, h.observaciones, h.fecha, u.nombre AS usuario,
                        u.apellido, s.nombre AS sucursal, sr.nombre AS sucursal_receptora,
                        t.nombre AS tecnico, t.apellido AS tecnico_apellido
                        FROM historial_reparaciones h JOIN estados_reparacion er ON er.codigo = h.estado
                        JOIN reparaciones r ON r.id_reparacion = h.reparacion_id
                        JOIN sucursales sr ON sr.id_sucursal = r.sucursal_id
                        JOIN usuarios u ON u.id_usuario = h.usuario_id
                        JOIN sucursales s ON s.id_sucursal = h.sucursal_id
                        LEFT JOIN tecnicos t ON t.id_tecnico = h.tecnico_id
                        WHERE h.reparacion_id = ? ORDER BY h.fecha, h.id_historial';
                $q = $db->prepare($sql);
                $q->execute([(int)$m[2]]);
                $this->responder(['ok' => true, 'registros' => $q->fetchAll()]);
                return;
            }
            $patron = '#\A/api/(clientes|equipos|reparaciones|productos|categorias|inventario|'
                . 'usuarios|sucursales|tecnicos|ventas)(?:/([1-9][0-9]{0,9}))?\z#';
            if (!preg_match($patron, $path, $m)) {
                throw new ErrorOperacion('Recurso no encontrado.', 404);
            }
            $id = isset($m[2]) ? (int)$m[2] : null;
            $permitido = $id === null ? 'POST' : 'PATCH';
            if ($metodo !== $permitido || ($id !== null && in_array($m[1], ['ventas', 'inventario'], true))) {
                header('Allow: ' . $permitido);
                throw new ErrorOperacion('Método no permitido.', 405);
            }
            if ($id !== null && $id > 4294967295) {
                throw new ErrorOperacion('Registro no encontrado.', 404);
            }
            Sesion::csrf();
            if (!Sesion::permite($m[1], $u, $id !== null)) {
                throw new ErrorOperacion('No tienes permiso para realizar esta operación.', 403);
            }
            $datos = $this->datos();
            $claveVenta = null;
            $firmaVenta = null;
            if ($m[1] === 'ventas') {
                $claveVenta = $datos['operacion'] ?? null;
                if (!is_string($claveVenta) || !preg_match('/\A[a-f0-9]{32}\z/', $claveVenta)) {
                    throw new ErrorOperacion('Recarga el formulario de venta antes de continuar.', 422);
                }
                unset($datos['operacion']);
                $firmaVenta = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
                $previa = $_SESSION['ventas_confirmadas'][$claveVenta] ?? null;
                if ($previa !== null) {
                    if ($previa['firma'] !== $firmaVenta) {
                        throw new ErrorOperacion(
                            'Esta solicitud ya registró una venta. Consulta las ventas antes de iniciar otra.',
                            409
                        );
                    }
                    $_SESSION['aviso'] = 'Venta registrada correctamente.';
                    $this->responder(['ok' => true, 'id' => $previa['id'], 'mensaje' => $_SESSION['aviso']]);
                    return;
                }
            }
            $guardado = (new Operaciones($db))->guardar($m[1], $id, $datos, $u);
            if ($claveVenta !== null) {
                $_SESSION['ventas_confirmadas'][$claveVenta] = ['firma' => $firmaVenta, 'id' => $guardado];
            }
            $_SESSION['aviso'] = $m[1] === 'ventas'
                ? 'Venta registrada correctamente.' : 'Registro guardado correctamente.';
            $this->responder(['ok' => true, 'id' => $guardado, 'mensaje' => $_SESSION['aviso']], $id === null
                ? 201 : 200);
        } catch (ErrorOperacion $e) {
            $this->responder(
                ['ok' => false, 'mensaje' => $e->getMessage(), 'errores' => (object)$e->campos],
                $e->estado
            );
        } catch (PDOException $e) {
            error_log('Telcom API: error PDO (' . $e->getCode() . ').');
            $conflicto = in_array((string)$e->getCode(), ['23000', '40001'], true);
            $this->responder(['ok' => false, 'mensaje' => $conflicto
                ? 'El registro está duplicado, relacionado o cambió durante la operación. Revisa los datos.'
                : 'No se pudo completar la operación. Inténtalo de nuevo.'], $conflicto ? 409 : 503);
        } catch (Throwable $e) {
            error_log('Telcom API: error de aplicación (' . get_class($e) . ').');
            $this->responder(['ok' => false,
                'mensaje' => 'No se pudo completar la operación. Inténtalo de nuevo.'], 500);
        }
    }

    private function datos(): array
    {
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            throw new ErrorOperacion('Envía los datos en formato JSON.', 415);
        }
        $raw = file_get_contents('php://input', false, null, 0, 131073);
        if (strlen($raw) > 131072) {
            throw new ErrorOperacion('La solicitud supera el tamaño permitido.', 413);
        }
        try {
            $objeto = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
            if (!$objeto instanceof \stdClass) {
                throw new \JsonException();
            }
            return json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ErrorOperacion('El contenido JSON no es válido.', 400);
        }
    }

    private function responder(array $datos, int $estado = 200): void
    {
        http_response_code($estado);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
