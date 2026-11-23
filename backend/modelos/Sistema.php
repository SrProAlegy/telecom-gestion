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
        return (int) $this->valor('SELECT version FROM schema_version WHERE version = ?', [1]) === 1
            && (int) $this->valor('SELECT COUNT(*) FROM estados_reparacion') === 8;
    }

    public function catalogos(): array
    {
        return [
            'sucursales' => $this->filas('SELECT id_sucursal, nombre, direccion, telefono, activo
                FROM sucursales ORDER BY nombre'),
            'clientes' => $this->filas('SELECT id_cliente, nombre FROM clientes ORDER BY nombre'),
            'equipos' => $this->filas('SELECT id_equipo, cliente_id, marca, modelo, imei FROM equipos
                ORDER BY marca, modelo'),
            'categorias' => $this->filas('SELECT id_categoria, nombre, descripcion FROM categorias ORDER BY nombre'),
            'tecnicos' => $this->filas('SELECT id_tecnico, nombre, apellido, telefono, especialidad, activo
                FROM tecnicos ORDER BY nombre, apellido'),
            'productos' => $this->filas('SELECT id_producto, nombre, activo FROM productos ORDER BY nombre'),
            'estados' => $this->filas('SELECT codigo, nombre FROM estados_reparacion ORDER BY orden'),
        ];
    }

    public function consultar(string $pagina, array $filtro): array
    {
        return match ($pagina) {
            'inicio' => $this->panel($filtro),
            'clientes' => $this->clientes($filtro),
            'reparaciones' => $this->reparaciones($filtro),
            'inventario' => $this->inventario($filtro),
            'ventas' => $this->ventas($filtro),
            'reportes' => $this->reportes($filtro),
            'usuarios' => $this->usuarios($filtro),
            default => [],
        };
    }

    private function panel(array $f): array
    {
        [$where, $params] = $this->sucursal('r', $f);
        $recientes = $this->filas(
            'SELECT r.id_reparacion, r.equipo_id, r.sucursal_id, r.tecnico_id, c.nombre AS cliente,
                e.marca, e.modelo, s.nombre AS sucursal,
                r.estado, er.nombre AS estado_nombre, r.fecha_recepcion
             FROM reparaciones r JOIN equipos e ON e.id_equipo = r.equipo_id
             JOIN clientes c ON c.id_cliente = e.cliente_id
             JOIN sucursales s ON s.id_sucursal = r.sucursal_id
             JOIN estados_reparacion er ON er.codigo = r.estado
             WHERE ' . $where . ' ORDER BY r.fecha_recepcion DESC, r.id_reparacion DESC LIMIT 5',
            $params
        );
        return ['resumen' => $this->resumen($f), 'reparaciones' => $recientes, 'actividad' => $this->actividad($f)];
    }

    private function resumen(array $f): array
    {
        [$where, $params] = $this->sucursal('r', $f);
        $activas = $this->valor(
            "SELECT COUNT(*) FROM reparaciones r WHERE $where
                AND r.estado NOT IN ('entregado', 'cancelado', 'no_reparable')",
            $params
        );
        $listas = $this->valor(
            "SELECT COUNT(*) FROM reparaciones r WHERE $where AND r.estado = 'listo_entrega'",
            $params
        );
        [$salesWhere, $salesParams] = $this->sucursal('v', $f);
        $ventas = $this->valor(
            "SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE $salesWhere AND v.fecha >= ? AND v.fecha < ?",
            [...$salesParams, $f['desde_utc'], $f['hasta_utc']]
        );
        [$stockWhere, $stockParams] = $this->sucursal('i', $f);
        $bajo = $this->valor(
            "SELECT COUNT(DISTINCT i.producto_id) FROM inventario i
             JOIN productos p ON p.id_producto = i.producto_id
             WHERE $stockWhere AND p.activo = 1 AND i.cantidad < p.stock_minimo",
            $stockParams
        );
        return ['activas' => (int) $activas, 'listas' => (int) $listas, 'ventas' => $ventas, 'bajo' => (int) $bajo];
    }

    private function clientes(array $f): array
    {
        $search = '%' . $f['q'] . '%';
        return [
            'totales' => [
                'clientes' => (int) $this->valor('SELECT COUNT(*) FROM clientes'),
                'equipos' => (int) $this->valor('SELECT COUNT(*) FROM equipos'),
                'con_equipo' => (int) $this->valor('SELECT COUNT(DISTINCT cliente_id) FROM equipos'),
            ],
            'clientes' => $this->filas(
                'SELECT c.id_cliente, c.nombre, c.telefono, c.correo,
                    (SELECT COUNT(*) FROM equipos e WHERE e.cliente_id = c.id_cliente) AS equipos
                 FROM clientes c WHERE c.nombre LIKE ? OR c.telefono LIKE ? OR c.correo LIKE ?
                 ORDER BY c.nombre, c.id_cliente LIMIT 100',
                [$search, $search, $search]
            ),
            'equipos' => $this->filas(
                'SELECT e.id_equipo, e.cliente_id, c.nombre AS cliente,
                    e.marca, e.modelo, e.imei, e.color, e.estado_fisico
                 FROM equipos e JOIN clientes c ON c.id_cliente = e.cliente_id
                 WHERE c.nombre LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ? OR e.imei LIKE ?
                 ORDER BY e.id_equipo DESC LIMIT 100',
                [$search, $search, $search, $search]
            ),
        ];
    }

    private function reparaciones(array $f): array
    {
        [$where, $params] = $this->sucursal('r', $f);
        $counts = $this->filas("SELECT r.estado, COUNT(*) AS cantidad FROM reparaciones r WHERE $where
            GROUP BY r.estado", $params);
        if ($f['estado'] !== '') {
            $where .= ' AND r.estado = ?';
            $params[] = $f['estado'];
        }
        $where .= ' AND (CAST(r.id_reparacion AS CHAR) LIKE ? OR c.nombre LIKE ? OR e.modelo LIKE ?)';
        $search = '%' . $f['q'] . '%';
        return ['conteos' => array_column($counts, 'cantidad', 'estado'), 'filas' => $this->filas(
            'SELECT r.id_reparacion, r.equipo_id, r.sucursal_id, r.tecnico_id, c.nombre AS cliente,
                e.marca, e.modelo, s.nombre AS sucursal,
                t.nombre AS tecnico_nombre, t.apellido AS tecnico_apellido, r.problema_reportado,
                r.diagnostico, r.observaciones, r.fecha_recepcion, r.fecha_entrega, r.estado,
                er.nombre AS estado_nombre
             FROM reparaciones r JOIN equipos e ON e.id_equipo = r.equipo_id
             JOIN clientes c ON c.id_cliente = e.cliente_id
             JOIN sucursales s ON s.id_sucursal = r.sucursal_id
             JOIN estados_reparacion er ON er.codigo = r.estado
             LEFT JOIN tecnicos t ON t.id_tecnico = r.tecnico_id
             WHERE ' . $where . ' ORDER BY r.fecha_recepcion DESC, r.id_reparacion DESC LIMIT 100',
            [...$params, $search, $search, $search]
        )];
    }

    private function inventario(array $f): array
    {
        [$where, $params] = $this->sucursal('i', $f);
        $search = '%' . $f['q'] . '%';
        $productos = $this->filas(
            'SELECT p.id_producto, p.categoria_id, p.nombre, p.descripcion, p.precio, p.stock_minimo, p.activo,
                c.nombre AS categoria FROM productos p JOIN categorias c ON c.id_categoria = p.categoria_id
             WHERE p.nombre LIKE ? OR CAST(p.id_producto AS CHAR) LIKE ? OR c.nombre LIKE ?
             ORDER BY p.nombre, p.id_producto LIMIT 100',
            [$search, $search, $search]
        );
        $stock = [];
        if ($productos !== []) {
            $ids = array_column($productos, 'id_producto');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            foreach (
                $this->filas(
                    "SELECT i.producto_id, i.sucursal_id, i.cantidad FROM inventario i
                 WHERE $where AND i.producto_id IN ($placeholders)",
                    [...$params, ...$ids]
                ) as $fila
            ) {
                $stock[$fila['producto_id']][$fila['sucursal_id']] = (int) $fila['cantidad'];
            }
        }
        return [
            'productos' => $productos,
            'stock' => $stock,
            'catalogo' => (int) $this->valor('SELECT COUNT(*) FROM productos'),
            'unidades' => (int) $this->valor("SELECT COALESCE(SUM(i.cantidad), 0) FROM inventario i
                WHERE $where", $params),
            'bajo' => $this->resumen($f)['bajo'],
        ];
    }

    private function ventas(array $f): array
    {
        [$where, $params] = $this->sucursal('v', $f);
        $where .= ' AND v.fecha >= ? AND v.fecha < ?';
        $params = [...$params, $f['desde_utc'], $f['hasta_utc']];
        [$stockWhere, $stockParams] = $this->sucursal('i', $f);
        $productos = $this->filas(
            "SELECT p.id_producto, p.nombre, p.precio,
                COALESCE(SUM(i.cantidad), 0) AS cantidad
             FROM productos p LEFT JOIN inventario i ON i.producto_id = p.id_producto AND $stockWhere
             WHERE p.activo = 1 GROUP BY p.id_producto, p.nombre, p.precio ORDER BY p.nombre LIMIT 100",
            $stockParams
        );
        return [
            'productos' => $productos,
            'total' => $this->valor("SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE $where", $params),
            'cantidad' => (int) $this->valor("SELECT COUNT(*) FROM ventas v WHERE $where", $params),
            'filas' => $this->filas(
                'SELECT v.id_venta, v.fecha, v.total, v.metodo_pago, u.nombre, u.apellido,
                    s.nombre AS sucursal, c.nombre AS cliente,
                    (SELECT COUNT(*) FROM detalle_venta d WHERE d.venta_id = v.id_venta) AS partidas
                 FROM ventas v JOIN usuarios u ON u.id_usuario = v.usuario_id
                 JOIN sucursales s ON s.id_sucursal = v.sucursal_id
                 LEFT JOIN clientes c ON c.id_cliente = v.cliente_id
                 WHERE ' . $where . ' ORDER BY v.fecha DESC, v.id_venta DESC LIMIT 100',
                $params
            ),
        ];
    }

    private function reportes(array $f): array
    {
        [$where, $params] = $this->sucursal('r', $f);
        $where .= ' AND r.fecha_recepcion >= ? AND r.fecha_recepcion < ?';
        $params = [...$params, $f['desde_utc'], $f['hasta_utc']];
        $estado = $this->filas(
            "SELECT er.codigo, er.nombre, COUNT(r.id_reparacion) AS cantidad FROM estados_reparacion er
             LEFT JOIN reparaciones r ON r.estado = er.codigo AND $where
             GROUP BY er.codigo, er.nombre, er.orden ORDER BY er.orden",
            $params
        );
        $branchWhere = $f['sucursal'] === null ? '1 = 1' : 's.id_sucursal = ?';
        $branchParams = $f['sucursal'] === null ? [] : [$f['sucursal']];
        $porSucursal = $this->filas(
            "SELECT s.nombre, COUNT(v.id_venta) AS cantidad, COALESCE(SUM(v.total), 0) AS total
             FROM sucursales s LEFT JOIN ventas v ON v.sucursal_id = s.id_sucursal
                AND v.fecha >= ? AND v.fecha < ? WHERE $branchWhere
             GROUP BY s.id_sucursal, s.nombre ORDER BY s.nombre",
            [$f['desde_utc'], $f['hasta_utc'], ...$branchParams]
        );
        [$stockWhere, $stockParams] = $this->sucursal('i', $f);
        return [
            'resumen' => $this->resumen($f),
            'estados' => $estado,
            'recibidas' => array_sum(array_column($estado, 'cantidad')),
            'sucursales' => $porSucursal,
            'stock' => $this->filas(
                "SELECT p.nombre, s.nombre AS sucursal, i.cantidad, p.stock_minimo FROM inventario i
                 JOIN productos p ON p.id_producto = i.producto_id
                 JOIN sucursales s ON s.id_sucursal = i.sucursal_id
                 WHERE $stockWhere AND p.activo = 1 AND i.cantidad < p.stock_minimo
                 ORDER BY s.nombre, p.nombre LIMIT 100",
                $stockParams
            ),
        ];
    }

    private function usuarios(array $f): array
    {
        [$where, $params] = $this->sucursal('u', $f);
        $total = (int) $this->valor("SELECT COUNT(*) FROM usuarios u WHERE $where", $params);
        $activos = (int) $this->valor("SELECT COUNT(*) FROM usuarios u WHERE $where AND u.activo = 1", $params);
        $search = '%' . $f['q'] . '%';
        $where .= ' AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.correo LIKE ?)';
        $params = [...$params, $search, $search, $search];
        if ($f['activo'] !== '') {
            $where .= ' AND u.activo = ?';
            $params[] = (int) $f['activo'];
        }
        return ['total' => $total, 'activos' => $activos, 'filas' => $this->filas(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.rol, u.activo, u.sucursal_id,
                s.nombre AS sucursal FROM usuarios u JOIN sucursales s ON s.id_sucursal = u.sucursal_id
             WHERE ' . $where . ' ORDER BY u.nombre, u.apellido, u.id_usuario LIMIT 100',
            $params
        )];
    }

    private function actividad(array $f): array
    {
        [$salesWhere, $salesParams] = $this->sucursal('v', $f);
        [$repairWhere, $repairParams] = $this->sucursal('h', $f);
        return $this->filas(
            "SELECT * FROM (
                SELECT 'venta' AS tipo, v.id_venta AS referencia, v.fecha, s.nombre AS sucursal,
                    'Venta registrada' AS descripcion FROM ventas v
                    JOIN sucursales s ON s.id_sucursal = v.sucursal_id WHERE $salesWhere
                UNION ALL
                SELECT 'reparacion' AS tipo, h.reparacion_id AS referencia, h.fecha, s.nombre AS sucursal,
                    er.nombre AS descripcion FROM historial_reparaciones h
                    JOIN sucursales s ON s.id_sucursal = h.sucursal_id
                    JOIN estados_reparacion er ON er.codigo = h.estado WHERE $repairWhere
             ) actividad ORDER BY fecha DESC, tipo, referencia DESC LIMIT 6",
            [...$salesParams, ...$repairParams]
        );
    }

    private function sucursal(string $alias, array $f): array
    {
        return $f['sucursal'] === null ? ['1 = 1', []] : ["$alias.sucursal_id = ?", [$f['sucursal']]];
    }

    private function filas(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private function valor(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }
}
