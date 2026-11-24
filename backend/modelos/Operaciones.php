<?php

declare(strict_types=1);

namespace Telcom\Modelos;

use PDO;
use Throwable;
use Telcom\Soporte\Entrada;
use Telcom\Soporte\ErrorOperacion;
use Telcom\Soporte\Sesion;

final class Operaciones
{
    private const TABLAS = ['clientes' => 'id_cliente', 'equipos' => 'id_equipo',
        'categorias' => 'id_categoria', 'productos' => 'id_producto', 'sucursales' => 'id_sucursal',
        'usuarios' => 'id_usuario', 'tecnicos' => 'id_tecnico'];
    public const TRANSICIONES = [
        'recibido' => ['enviado_tecnico', 'diagnostico', 'cancelado'],
        'enviado_tecnico' => ['diagnostico', 'cancelado'],
        'diagnostico' => ['en_reparacion', 'no_reparable', 'cancelado'],
        'en_reparacion' => ['diagnostico', 'listo_entrega', 'no_reparable', 'cancelado'],
        'listo_entrega' => ['entregado', 'en_reparacion', 'cancelado'],
        'entregado' => [], 'cancelado' => [], 'no_reparable' => [],
    ];
    public function __construct(private readonly PDO $db)
    {
    }

    private function ejecutar(string $sql, array $valores = []): \PDOStatement
    {
        $q = $this->db->prepare($sql);
        $q->execute($valores);
        return $q;
    }

    private function fila(string $tabla, string $pk, int $id, bool $activo = false): array
    {
        $r = $this->ejecutar("SELECT * FROM $tabla WHERE $pk = ? FOR UPDATE", [$id])->fetch();
        if (!$r || ($activo && !(int)$r['activo'])) {
            throw new ErrorOperacion('El registro no existe o está inactivo.', 409);
        }
        return $r;
    }

    private function insertar(string $tabla, array $campos): int
    {
        $columnas = implode(', ', array_keys($campos));
        $marcas = implode(', ', array_fill(0, count($campos), '?'));
        $this->ejecutar("INSERT INTO $tabla ($columnas) VALUES ($marcas)", array_values($campos));
        return (int)$this->db->lastInsertId();
    }

    private function actualizar(string $tabla, string $pk, int $id, array $campos): void
    {
        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($campos)));
        $this->ejecutar("UPDATE $tabla SET $set WHERE $pk = ?", [...array_values($campos), $id]);
    }

    public function guardar(string $recurso, ?int $id, array $datos, array $usuario): int
    {
        if (!Sesion::permite($recurso, $usuario, $id !== null)) {
            throw new ErrorOperacion('No tienes permiso para realizar esta operación.', 403);
        }
        $this->db->beginTransaction();
        try {
        // Orden común de bloqueo: configuración, operador, sucursal y registros de negocio.
            if (in_array($recurso, ['usuarios', 'sucursales'], true)) {
                $this->ejecutar('SELECT version FROM schema_version WHERE version = 1 FOR UPDATE');
            }
            $operador = $this->fila('usuarios', 'id_usuario', (int)$usuario['id_usuario'], true);
            if (
                $operador['rol'] !== $usuario['rol']
                || (int)$operador['sucursal_id'] !== (int)$usuario['sucursal_id']
            ) {
                throw new ErrorOperacion('Tu cuenta cambió. Recarga la página.', 409);
            }
            $this->fila('sucursales', 'id_sucursal', (int)$operador['sucursal_id'], true);
            $e = new Entrada($datos);
            $guardado = match ($recurso) {
                'reparaciones' => $this->reparacion($id, $e, $usuario),
                'inventario' => $this->inventario($e),
                'ventas' => $this->venta($e, $usuario),
                default => $this->catalogo($recurso, $id, $e, $usuario),
            };
            $this->db->commit();
            return $guardado;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function catalogo(string $tabla, ?int $id, Entrada $e, array $u): int
    {
        $pk = self::TABLAS[$tabla] ?? throw new ErrorOperacion('Recurso no encontrado.', 404);
        $antes = $id === null ? null : $this->fila($tabla, $pk, $id);
        $campos = match ($tabla) {
            'clientes' => ['nombre' => $e->texto('nombre', 200, true),
                'telefono' => $e->telefono(), 'correo' => $e->correo()],
            'equipos' => ['cliente_id' => $e->entero('cliente_id'), 'marca' => $e->texto('marca', 80),
                'modelo' => $e->texto('modelo', 100), 'imei' => $e->texto('imei', 15),
                'color' => $e->texto('color', 60), 'estado_fisico' => $e->texto('estado_fisico', 10000)],
            'categorias' => ['nombre' => $e->texto('nombre', 100, true),
                'descripcion' => $e->texto('descripcion', 10000)],
            'productos' => ['nombre' => $e->texto('nombre', 150, true),
                'categoria_id' => $e->entero('categoria_id'), 'descripcion' => $e->texto('descripcion', 10000),
                'precio' => $e->precio(), 'stock_minimo' => $e->entero('stock_minimo', 0),
                'activo' => $e->entero('activo', 0, 1)],
            'sucursales' => ['nombre' => $e->texto('nombre', 120, true),
                'direccion' => $e->texto('direccion', 255), 'telefono' => $e->telefono(),
                'activo' => $e->entero('activo', 0, 1)],
            'tecnicos' => ['nombre' => $e->texto('nombre', 100, true), 'apellido' => $e->texto('apellido', 150, true),
                'telefono' => $e->telefono(), 'especialidad' => $e->texto('especialidad', 150),
                'activo' => $e->entero('activo', 0, 1)],
            'usuarios' => ['nombre' => $e->texto('nombre', 100, true), 'apellido' => $e->texto('apellido', 150, true),
                'correo' => $e->correo(true), 'rol' => $e->opcion('rol', array_keys(Sesion::PAGINAS)),
                'sucursal_id' => $e->entero('sucursal_id'), 'activo' => $e->entero('activo', 0, 1)],
        };
        if ($tabla === 'equipos') {
            $this->fila('clientes', 'id_cliente', $campos['cliente_id']);
            if ($campos['marca'] === null && $campos['modelo'] === null && $campos['imei'] === null) {
                $e->error('marca', 'Registra al menos la marca, el modelo o el IMEI para identificar el equipo.');
            }
            if ($campos['imei'] !== null && !preg_match('/\A[0-9]{15}\z/', $campos['imei'])) {
                $e->error('imei', 'El IMEI debe contener exactamente 15 dígitos.');
            }
            if (
                $antes && (int)$antes['cliente_id'] !== $campos['cliente_id']
                && $this->ejecutar('SELECT 1 FROM reparaciones WHERE equipo_id = ? LIMIT 1', [$id])->fetch()
            ) {
                throw new ErrorOperacion('No se puede cambiar el cliente de un equipo con reparaciones.', 409);
            }
        }
        if ($tabla === 'productos') {
            $this->fila('categorias', 'id_categoria', $campos['categoria_id']);
        }
        if ($tabla === 'usuarios') {
            $this->fila('sucursales', 'id_sucursal', $campos['sucursal_id'], $campos['activo'] === 1);
            if ($e->tiene('password') || $id === null) {
                $campos['password_hash'] = $e->password();
            }
            if ($id === (int)$u['id_usuario'] && (!$campos['activo'] || $campos['rol'] !== 'administrador')) {
                throw new ErrorOperacion('Tu cuenta debe conservar el acceso de administrador.', 409);
            }
            if ($antes && (int)$antes['sucursal_id'] !== $campos['sucursal_id']) {
                foreach (['ventas', 'reparaciones', 'historial_reparaciones'] as $t) {
                    if ($this->ejecutar("SELECT 1 FROM $t WHERE usuario_id = ? LIMIT 1", [$id])->fetch()) {
                        throw new ErrorOperacion('El usuario tiene operaciones y debe conservar su sucursal.', 409);
                    }
                }
            }
        }
        $result = $id ?? $this->insertar($tabla, $campos);
        if ($id !== null) {
            $this->actualizar($tabla, $pk, $id, $campos);
        }
        if (in_array($tabla, ['usuarios', 'sucursales'], true)) {
            $administrador = $this->ejecutar("SELECT u.id_usuario FROM usuarios u
                JOIN sucursales s ON s.id_sucursal = u.sucursal_id
                WHERE u.activo = 1 AND s.activo = 1 AND u.rol = 'administrador' LIMIT 1")->fetch();
            if (!$administrador) {
                throw new ErrorOperacion('Debe permanecer al menos un administrador en una sucursal activa.', 409);
            }
        }
        return $result;
    }

    private function inventario(Entrada $e): int
    {
        $producto = $e->entero('producto_id');
        $sucursal = $e->entero('sucursal_id');
        $cantidad = $e->entero('cantidad', 0, 2147483647);
        $this->fila('sucursales', 'id_sucursal', $sucursal, true);
        $this->fila('productos', 'id_producto', $producto, true);
        $anterior = $e->entero('cantidad_anterior', 0, 2147483647);
        $actual = $this->ejecutar('SELECT cantidad FROM inventario
            WHERE producto_id = ? AND sucursal_id = ? FOR UPDATE', [$producto, $sucursal])->fetchColumn();
        if ($anterior !== (int)($actual === false ? 0 : $actual)) {
            throw new ErrorOperacion('Las existencias cambiaron. Consulta la cantidad actual antes de guardar.', 409);
        }
        $this->ejecutar(
            'INSERT INTO inventario (producto_id, sucursal_id, cantidad) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE cantidad = ?, fecha_ultimo_mov = UTC_TIMESTAMP()',
            [$producto, $sucursal, $cantidad, $cantidad]
        );
        return (int)$this->ejecutar('SELECT id_inventario FROM inventario
            WHERE producto_id = ? AND sucursal_id = ?', [$producto, $sucursal])->fetchColumn();
    }

    private function reparacion(?int $id, Entrada $e, array $u): int
    {
        $tecnico = $e->entero('tecnico_id', 1, 4294967295, true);
        if ($id === null) {
            $sucursal = $e->entero('sucursal_id');
            if ($sucursal !== (int)$u['sucursal_id']) {
                throw new ErrorOperacion('Registra la recepción en la sucursal de tu cuenta.', 403);
            }
            $equipo = $e->entero('equipo_id');
            $this->fila('equipos', 'id_equipo', $equipo);
            if ($tecnico !== null) {
                $this->fila('tecnicos', 'id_tecnico', $tecnico, true);
            }
            $campos = ['equipo_id' => $equipo, 'sucursal_id' => $sucursal, 'usuario_id' => (int)$u['id_usuario'],
                'tecnico_id' => $tecnico, 'problema_reportado' => $e->texto('problema_reportado', 10000, true),
                'estado' => 'recibido'];
            $id = $this->insertar('reparaciones', $campos);
        } else {
            $antes = $this->fila('reparaciones', 'id_reparacion', $id);
            if (!Sesion::verOperacion('reparaciones', $u, (int)$antes['sucursal_id'])) {
                throw new ErrorOperacion('No tienes acceso a esta reparación.', 403);
            }
            $revision = $e->entero('revision', 0, PHP_INT_MAX);
            $actual = (int)$this->ejecutar('SELECT COALESCE(MAX(id_historial), 0)
                FROM historial_reparaciones WHERE reparacion_id = ?', [$id])->fetchColumn();
            if ($revision !== $actual) {
                throw new ErrorOperacion('El seguimiento cambió. Recarga la página antes de editarlo.', 409);
            }
            $estado = $e->opcion('estado', array_keys(self::TRANSICIONES));
            if (self::TRANSICIONES[$antes['estado']] === []) {
                throw new ErrorOperacion('La reparación está cerrada y conserva su historial.', 409);
            }
            if ($estado !== $antes['estado'] && !in_array($estado, self::TRANSICIONES[$antes['estado']], true)) {
                $e->error('estado', 'El cambio no corresponde al estado actual de la reparación.');
            }
            if ($u['rol'] === 'tecnico' && in_array($estado, ['entregado', 'cancelado'], true)) {
                throw new ErrorOperacion('La entrega y cancelación corresponden a recepción.', 403);
            }
            if ($tecnico !== null && $tecnico !== (int)$antes['tecnico_id']) {
                $this->fila('tecnicos', 'id_tecnico', $tecnico, true);
            }
            if (
                in_array(
                    $estado,
                    ['enviado_tecnico', 'diagnostico', 'en_reparacion', 'listo_entrega', 'entregado', 'no_reparable'],
                    true
                )
                && $tecnico === null
            ) {
                $e->error('tecnico_id', 'Asigna un técnico para continuar.');
            }
            $diagnostico = $e->texto('diagnostico', 10000);
            if (
                in_array($estado, ['en_reparacion', 'listo_entrega', 'entregado', 'no_reparable'], true)
                && $diagnostico === null
            ) {
                $e->error('diagnostico', 'Registra el diagnóstico antes de continuar.');
            }
            $campos = ['tecnico_id' => $tecnico, 'estado' => $estado, 'diagnostico' => $diagnostico,
                'observaciones' => $e->texto('observaciones', 10000)];
            if ($estado === 'entregado') {
                $campos['fecha_entrega'] = gmdate('Y-m-d H:i:s');
            }
            $this->actualizar('reparaciones', 'id_reparacion', $id, $campos);
        }
        $this->insertar('historial_reparaciones', ['reparacion_id' => $id,
            'usuario_id' => (int)$u['id_usuario'], 'sucursal_id' => (int)$u['sucursal_id'],
            'tecnico_id' => $tecnico, 'estado' => $campos['estado'],
            'diagnostico' => $campos['diagnostico'] ?? null, 'observaciones' => $campos['observaciones'] ?? null]);
        return $id;
    }

    private function venta(Entrada $e, array $u): int
    {
        $sucursal = $e->entero('sucursal_id');
        if ($sucursal !== (int)$u['sucursal_id']) {
            throw new ErrorOperacion('Registra la venta en la sucursal de tu cuenta.', 403);
        }
        $cliente = $e->entero('cliente_id', 1, 4294967295, true);
        if ($cliente !== null) {
            $this->fila('clientes', 'id_cliente', $cliente);
        }
        $metodo = $e->opcion('metodo_pago', ['efectivo', 'tarjeta', 'transferencia']);
        $partidas = [];
        foreach ($e->lista('detalles') as $fila) {
            if (!is_array($fila)) {
                $e->error('detalles', 'Revisa los productos de la venta.');
            }
            $item = new Entrada($fila);
            $producto = $item->entero('producto_id');
            if (isset($partidas[$producto])) {
                $e->error('detalles', 'Cada producto debe aparecer una sola vez.');
            }
            $partidas[$producto] = ['cantidad' => $item->entero('cantidad', 1, 2147483647)];
        }
        ksort($partidas, SORT_NUMERIC);
        $total = 0;
        foreach ($partidas as $producto => &$item) {
            $p = $this->fila('productos', 'id_producto', $producto, true);
            $stock = $this->ejecutar('SELECT cantidad FROM inventario
                WHERE producto_id = ? AND sucursal_id = ? FOR UPDATE', [$producto, $sucursal])->fetchColumn();
            if ($stock === false || (int)$stock < $item['cantidad']) {
                throw new ErrorOperacion('Las existencias cambiaron. Revisa las cantidades de la venta.', 409);
            }
            $centavos = (int)str_replace('.', '', $p['precio']);
            if ($centavos !== 0 && $item['cantidad'] > intdiv(999999999999 - $total, $centavos)) {
                $e->error('detalles', 'El total excede el importe permitido.');
            }
            $total += $centavos * $item['cantidad'];
            $item['precio'] = $p['precio'];
        }
        unset($item);
        $id = $this->insertar('ventas', ['sucursal_id' => $sucursal, 'usuario_id' => (int)$u['id_usuario'],
            'cliente_id' => $cliente, 'metodo_pago' => $metodo,
            'total' => intdiv($total, 100) . '.' . str_pad((string)($total % 100), 2, '0', STR_PAD_LEFT)]);
        foreach ($partidas as $producto => $item) {
            $this->insertar('detalle_venta', ['venta_id' => $id, 'producto_id' => $producto,
                'cantidad' => $item['cantidad'], 'precio_unitario' => $item['precio']]);
            $this->ejecutar('UPDATE inventario SET cantidad = cantidad - ?, fecha_ultimo_mov = UTC_TIMESTAMP()
                WHERE producto_id = ? AND sucursal_id = ?', [$item['cantidad'], $producto, $sucursal]);
        }
        return $id;
    }
}
