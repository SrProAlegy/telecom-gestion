<?php

declare(strict_types=1);

$db = require dirname(__DIR__) . '/backend/config/database.php';
$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException($label);
    }
    echo "OK: $label\n";
};
$reject = static function (string $sql, string $label) use ($db, $assert): void {
    try {
        $db->exec($sql);
    } catch (PDOException $error) {
        $assert(in_array($error->errorInfo[1] ?? 0, [3819, 1062, 1452], true), $label);
        return;
    }
    throw new RuntimeException('Se aceptó un dato inválido: ' . $label);
};

$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(8));
    $db->exec("INSERT INTO sucursales (nombre) VALUES ('Prueba temporal')");
    $branch = (int) $db->lastInsertId();
    $statement = $db->prepare('INSERT INTO usuarios
        (nombre, apellido, correo, password_hash, rol, sucursal_id) VALUES (?, ?, ?, ?, ?, ?)');
    $statement->execute([
        'Prueba', 'Temporal', $suffix . '@example.invalid',
        password_hash($suffix, PASSWORD_DEFAULT), 'recepcion', $branch,
    ]);
    $user = (int) $db->lastInsertId();
    $db->exec("INSERT INTO clientes (nombre) VALUES ('Prueba temporal')");
    $client = (int) $db->lastInsertId();
    $db->exec("INSERT INTO equipos (cliente_id) VALUES ($client)");
    $device = (int) $db->lastInsertId();
    $db->exec("INSERT INTO reparaciones (equipo_id, usuario_id, sucursal_id, problema_reportado)
        VALUES ($device, $user, $branch, 'Prueba'), ($device, $user, $branch, 'Segunda visita')");
    $repair = (int) $db->lastInsertId();
    $db->exec("INSERT INTO historial_reparaciones (reparacion_id, usuario_id, sucursal_id, estado)
        VALUES ($repair, $user, $branch, 'recibido'), ($repair, $user, $branch, 'diagnostico')");
    $assert(
        (int) $db->query("SELECT COUNT(*) FROM reparaciones WHERE equipo_id = $device")->fetchColumn() === 2,
        'Un equipo admite varias reparaciones'
    );
    $assert(
        (int) $db->query("SELECT COUNT(*) FROM historial_reparaciones
            WHERE reparacion_id = $repair")->fetchColumn() === 2,
        'Historial con varios eventos'
    );
    $db->exec("INSERT INTO categorias (nombre) VALUES ('$suffix')");
    $category = (int) $db->lastInsertId();
    $db->exec("INSERT INTO productos (categoria_id, nombre, precio) VALUES ($category, 'Prueba', 12.50)");
    $product = (int) $db->lastInsertId();
    $db->exec("INSERT INTO inventario (producto_id, sucursal_id, cantidad) VALUES ($product, $branch, 3)");
    $reject(
        "INSERT INTO inventario (producto_id, sucursal_id) VALUES ($product, $branch)",
        'Inventario único por producto y sucursal'
    );
    $reject("UPDATE inventario SET cantidad = -1 WHERE producto_id = $product", 'Rechazo de existencias negativas');
    $db->exec("INSERT INTO sucursales (nombre) VALUES ('Otra sucursal temporal')");
    $other = (int) $db->lastInsertId();
    $db->exec("INSERT INTO inventario (producto_id, sucursal_id, cantidad) VALUES ($product, $other, 7)");
    $assert(
        (int) $db->query("SELECT cantidad FROM inventario
            WHERE producto_id = $product AND sucursal_id = $branch")->fetchColumn() === 3,
        'Existencias independientes por sucursal'
    );
    $reject(
        "INSERT INTO ventas (total, metodo_pago, usuario_id, sucursal_id) VALUES (25, 'efectivo', $user, $other)",
        'Usuario y sucursal deben corresponder'
    );
    $db->exec("INSERT INTO ventas (total, metodo_pago, usuario_id, sucursal_id)
        VALUES (25, 'efectivo', $user, $branch)");
    $sale = (int) $db->lastInsertId();
    $db->exec("INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario)
        VALUES ($sale, $product, 2, 12.50)");
    $assert(
        (float) $db->query("SELECT subtotal FROM detalle_venta WHERE venta_id = $sale")->fetchColumn() === 25.0,
        'Subtotal decimal calculado'
    );
    $reject(
        "INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario)
            VALUES ($sale, $product, 0, 12.50)",
        'Rechazo de cantidad cero'
    );
    $reject(
        "UPDATE reparaciones SET estado = 'inexistente' WHERE id_reparacion = $repair",
        'Estado limitado al catálogo'
    );
    $reject("INSERT INTO equipos (cliente_id) VALUES (4294967295)", 'Rechazo de referencia huérfana');
    $assert(
        (int) $db->query('SELECT COUNT(*) FROM estados_reparacion')->fetchColumn() === 8,
        'Catálogo de estados inicializado'
    );
} finally {
    $db->rollBack();
}
echo "Pruebas terminadas; datos temporales revertidos.\n";
