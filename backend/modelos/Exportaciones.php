<?php

declare(strict_types=1);

namespace Telcom\Modelos;

use Generator;
use PDO;

final class Exportaciones
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function hojas(string $pagina, array $f): array
    {
        return match ($pagina) {
            'clientes' => $this->clientes($f),
            'reparaciones' => $this->reparaciones($f),
            'inventario' => $this->inventario($f),
            'ventas' => $this->ventas($f),
            'reportes' => $this->reportes($f),
            default => throw new \InvalidArgumentException('Exportación no disponible.'),
        };
    }

    private function hoja(string $nombre, array $columnas, string $sql, array $params): array
    {
        return ['nombre' => $nombre, 'columnas' => $columnas, 'filas' => $this->cursor($sql, $params)];
    }

    private function cursor(string $sql, array $params): Generator
    {
        $buffer = $this->db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $query = null;
        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            while ($fila = $query->fetch()) {
                yield $fila;
            }
        } finally {
            $query?->closeCursor();
            $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffer);
        }
    }

    private function sucursal(string $alias, array $f): array
    {
        return $f['sucursal'] === null ? ['1=1', []] : ["$alias.sucursal_id = ?", [$f['sucursal']]];
    }

    private function clientes(array $f): array
    {
        $q = '%' . $f['q'] . '%';
        return [
            $this->hoja(
                'Clientes',
                ['id_cliente' => ['ID', 'int'], 'nombre' => ['Cliente'],
                'telefono' => ['Teléfono'], 'correo' => ['Correo electrónico'], 'equipos' => ['Equipos', 'int']],
                'SELECT c.id_cliente, c.nombre, c.telefono, c.correo,
                    (SELECT COUNT(*) FROM equipos e WHERE e.cliente_id=c.id_cliente) AS equipos
                 FROM clientes c WHERE c.nombre LIKE ? OR c.telefono LIKE ? OR c.correo LIKE ?
                 ORDER BY c.nombre, c.id_cliente',
                [$q, $q, $q]
            ),
            $this->hoja(
                'Equipos',
                ['id_equipo' => ['Equipo', 'EQ'], 'cliente' => ['Cliente'],
                'marca' => ['Marca'], 'modelo' => ['Modelo'], 'imei' => ['IMEI'], 'color' => ['Color'],
                'estado_fisico' => ['Estado físico']],
                'SELECT e.id_equipo, c.nombre AS cliente, e.marca, e.modelo, e.imei, e.color, e.estado_fisico
                 FROM equipos e JOIN clientes c ON c.id_cliente=e.cliente_id
                 WHERE c.nombre LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ? OR e.imei LIKE ?
                 ORDER BY e.id_equipo DESC',
                [$q, $q, $q, $q]
            ),
        ];
    }

    private function reparaciones(array $f): array
    {
        [$where, $params] = $this->sucursal('r', $f);
        if ($f['estado'] !== '') {
            $where .= ' AND r.estado = ?';
            $params[] = $f['estado'];
        }
        if (($f['tecnico'] ?? '') === 'sin_asignar') {
                $where .= ' AND r.tecnico_id IS NULL';
        } elseif (($f['tecnico'] ?? '') !== '') {
            $where .= ' AND r.tecnico_id = ?';
            $params[] = (int)$f['tecnico'];
        }
        $where .= ' AND (CAST(r.id_reparacion AS CHAR) LIKE ? OR c.nombre LIKE ? OR e.modelo LIKE ?)';
        $q = '%' . $f['q'] . '%';
        $params = [...$params, $q, $q, $q];
        $joins = 'FROM reparaciones r JOIN equipos e ON e.id_equipo=r.equipo_id
            JOIN clientes c ON c.id_cliente=e.cliente_id JOIN sucursales s ON s.id_sucursal=r.sucursal_id';
        return [
            $this->hoja(
                'Reparaciones',
                ['id_reparacion' => ['Folio', 'REP'], 'cliente' => ['Cliente'],
                'marca' => ['Marca'], 'modelo' => ['Modelo'], 'imei' => ['IMEI'], 'sucursal' => ['Sucursal receptora'],
                'receptor' => ['Usuario receptor'], 'tecnico' => ['Técnico'], 'estado_nombre' => ['Estado'],
                'problema_reportado' => ['Problema reportado'], 'diagnostico' => ['Diagnóstico'],
                'observaciones' => ['Observaciones'], 'fecha_recepcion' => ['Recepción', 'date'],
                'fecha_entrega' => ['Entrega', 'date']],
                "SELECT r.id_reparacion, c.nombre AS cliente, e.marca, e.modelo, e.imei, s.nombre AS sucursal,
                    CONCAT(u.nombre, ' ', u.apellido) AS receptor, CONCAT(t.nombre, ' ', t.apellido) AS tecnico,
                    er.nombre AS estado_nombre, r.problema_reportado, r.diagnostico, r.observaciones,
                    r.fecha_recepcion, r.fecha_entrega $joins
                 JOIN usuarios u ON u.id_usuario=r.usuario_id
                 LEFT JOIN tecnicos t ON t.id_tecnico=r.tecnico_id
                 JOIN estados_reparacion er ON er.codigo=r.estado
                 WHERE $where ORDER BY r.fecha_recepcion DESC, r.id_reparacion DESC",
                $params
            ),
            $this->hoja(
                'Historial',
                ['id_historial' => ['Evento', 'int'], 'reparacion_id' => ['Folio', 'REP'],
                'sucursal_receptora' => ['Sucursal receptora'], 'sucursal_operador' => ['Sucursal del operador'],
                'operador' => ['Responsable'], 'tecnico' => ['Técnico'], 'estado' => ['Estado'],
                'diagnostico' => ['Diagnóstico'], 'observaciones' => ['Observaciones'], 'fecha' => ['Fecha', 'date']],
                "SELECT h.id_historial, h.reparacion_id, s.nombre AS sucursal_receptora,
                    so.nombre AS sucursal_operador, CONCAT(u.nombre, ' ', u.apellido) AS operador,
                    CONCAT(t.nombre, ' ', t.apellido) AS tecnico, er.nombre AS estado,
                    h.diagnostico, h.observaciones, h.fecha $joins
                 JOIN historial_reparaciones h ON h.reparacion_id=r.id_reparacion
                 JOIN sucursales so ON so.id_sucursal=h.sucursal_id
                 JOIN usuarios u ON u.id_usuario=h.usuario_id
                 LEFT JOIN tecnicos t ON t.id_tecnico=h.tecnico_id
                 JOIN estados_reparacion er ON er.codigo=h.estado
                 WHERE $where ORDER BY h.reparacion_id, h.fecha, h.id_historial",
                $params
            ),
        ];
    }

    private function inventario(array $f): array
    {
        $q = '%' . $f['q'] . '%';
        $where = '(p.nombre LIKE ? OR CAST(p.id_producto AS CHAR) LIKE ? OR c.nombre LIKE ?)';
        $params = [$q, $q, $q];
        $columnas = ['id_producto' => ['Producto', 'PROD'], 'nombre' => ['Producto'], 'categoria' => ['Categoría'],
            'descripcion' => ['Descripción'], 'precio' => ['Precio', 'money'], 'stock_minimo' => ['Mínimo', 'int'],
            'activo' => ['Estado', 'active']];
        $productos = $this->hoja(
            'Productos',
            $columnas,
            "SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id_categoria=p.categoria_id
             WHERE $where ORDER BY p.nombre, p.id_producto",
            $params
        );
        if ($f['sucursal'] !== null) {
            $where .= ' AND s.id_sucursal=?';
            $params[] = $f['sucursal'];
        }
        $existencias = $this->hoja(
            'Existencias',
            ['id_producto' => ['Producto', 'PROD'], 'nombre' => ['Nombre'],
            'categoria' => ['Categoría'], 'sucursal' => ['Sucursal'], 'cantidad' => ['Cantidad', 'int'],
            'stock_minimo' => ['Mínimo', 'int'], 'activo' => ['Estado del producto', 'active']],
            "SELECT p.id_producto, p.nombre, c.nombre AS categoria, s.nombre AS sucursal, i.cantidad,
                p.stock_minimo, p.activo FROM productos p JOIN categorias c ON c.id_categoria=p.categoria_id
             CROSS JOIN sucursales s
             LEFT JOIN inventario i ON i.producto_id=p.id_producto AND i.sucursal_id=s.id_sucursal
             WHERE $where ORDER BY p.nombre, p.id_producto, s.nombre, s.id_sucursal",
            $params
        );
        return [$productos, $existencias];
    }

    private function ventas(array $f): array
    {
        [$where, $params] = $this->sucursal('v', $f);
        $where .= ' AND v.fecha>=? AND v.fecha<?';
        $params = [...$params, $f['desde_utc'], $f['hasta_utc']];
        return [
            $this->hoja(
                'Ventas',
                ['id_venta' => ['Folio', 'V'], 'fecha' => ['Fecha', 'date'],
                'operador' => ['Usuario'], 'cliente' => ['Cliente'], 'sucursal' => ['Sucursal'],
                'metodo_pago' => ['Método de pago'], 'partidas' => ['Partidas', 'int'], 'total' => ['Total', 'money']],
                "SELECT v.id_venta, v.fecha, CONCAT(u.nombre, ' ', u.apellido) AS operador, c.nombre AS cliente,
                    s.nombre AS sucursal, v.metodo_pago, v.total,
                    (SELECT COUNT(*) FROM detalle_venta d WHERE d.venta_id=v.id_venta) AS partidas
                 FROM ventas v JOIN usuarios u ON u.id_usuario=v.usuario_id
                 JOIN sucursales s ON s.id_sucursal=v.sucursal_id LEFT JOIN clientes c ON c.id_cliente=v.cliente_id
                 WHERE $where ORDER BY v.fecha DESC, v.id_venta DESC",
                $params
            ),
            $this->hoja(
                'Detalle de ventas',
                ['venta_id' => ['Folio', 'V'], 'fecha' => ['Fecha', 'date'],
                'sucursal' => ['Sucursal'], 'producto' => ['Producto'], 'cantidad' => ['Cantidad', 'int'],
                'precio_unitario' => ['Precio unitario', 'money'], 'subtotal' => ['Subtotal', 'money']],
                "SELECT d.venta_id, v.fecha, s.nombre AS sucursal, p.nombre AS producto,
                    d.cantidad, d.precio_unitario, d.subtotal FROM ventas v
                 JOIN detalle_venta d ON d.venta_id=v.id_venta JOIN productos p ON p.id_producto=d.producto_id
                 JOIN sucursales s ON s.id_sucursal=v.sucursal_id
                 WHERE $where ORDER BY v.fecha DESC, v.id_venta DESC, d.id_detalle",
                $params
            ),
        ];
    }

    private function reportes(array $f): array
    {
        $r = (new Sistema($this->db))->consultar('reportes', $f);
        return [
            ['nombre' => 'Resumen', 'columnas' => ['indicador' => ['Indicador'], 'importe' => ['Importe', 'money'],
                'cantidad' => ['Cantidad', 'int']], 'filas' => [
                    ['indicador' => 'Ventas del periodo', 'importe' => $r['resumen']['ventas']],
                    ['indicador' => 'Reparaciones recibidas', 'cantidad' => $r['recibidas']],
                    ['indicador' => 'Productos bajo mínimo actual', 'cantidad' => $r['resumen']['bajo']],
                ]],
            ['nombre' => 'Ventas por sucursal', 'columnas' => ['nombre' => ['Sucursal'],
                'cantidad' => ['Ventas', 'int'], 'total' => ['Total', 'money']], 'filas' => $r['sucursales']],
            ['nombre' => 'Reparaciones por estado', 'columnas' => ['nombre' => ['Estado'],
                'cantidad' => ['Reparaciones', 'int']], 'filas' => $r['estados']],
            ['nombre' => 'Stock bajo', 'columnas' => ['nombre' => ['Producto'], 'sucursal' => ['Sucursal'],
                'cantidad' => ['Existencia', 'int'], 'stock_minimo' => ['Mínimo', 'int']], 'filas' => $r['stock']],
        ];
    }
}
