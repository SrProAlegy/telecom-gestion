<section class="pos-layout">
<article class="panel"><div class="panel-header"><div><h2>Productos del catálogo</h2><p class="muted">Existencias registradas en las sucursales consultadas</p></div><a class="text-link" href="/inventario">Ver inventario</a></div>
<?php if ($datos['productos'] === []) : ?><div class="empty-state"><?= icono('inventario') ?><h3>Sin productos registrados</h3><p>No hay productos activos en el catálogo.</p></div><?php else : ?><div class="product-grid">
<?php foreach ($datos['productos'] as $p) : ?><article class="product-card"><span class="quick-icon"><?= icono('inventario') ?></span><h3><?= escapar($p['nombre']) ?></h3><p><?= escapar(folio('PROD', $p['id_producto'])) ?> · Existencia: <?= (int) $p['cantidad'] ?></p><strong><?= moneda($p['precio']) ?></strong><button type="button" class="btn btn-light" data-add-product data-product-id="<?= (int) $p['id_producto'] ?>" data-product-name="<?= escapar($p['nombre']) ?>" data-product-price="<?= escapar($p['precio']) ?>" data-product-stock="<?= (int) $p['cantidad'] ?>">Agregar</button></article><?php endforeach; ?>
</div><?php endif; ?></article>
<aside class="panel sale-panel"><div class="panel-header"><div><h2>Detalle de venta</h2><p class="muted">Productos seleccionados</p></div></div>
<form action="/api/ventas" method="post" data-api-form id="form-venta">
<input type="hidden" name="sucursal_id" value="<?= escapar($filtro['sucursal'] ?? '') ?>"><input type="hidden" name="detalles" value="[]" data-cart-payload>
<div class="form-message" role="alert" hidden></div><div data-cart-items class="cart-items"><p class="empty-cart">Selecciona productos para iniciar la venta.</p></div>
<div class="sale-line"><span>Subtotal</span><strong data-cart-subtotal>$0.00</strong></div><div class="sale-line total-line"><span>Total</span><strong data-cart-total>$0.00</strong></div>
<label class="field-label" for="cliente-venta">Cliente</label><select id="cliente-venta" name="cliente_id"><option value="">Venta sin cliente asociado</option><?php foreach ($catalogos['clientes'] as $c) : ?><option value="<?= (int) $c['id_cliente'] ?>"><?= escapar($c['nombre']) ?></option><?php endforeach; ?></select>
<label class="field-label" for="metodo-venta">Método de pago <span aria-hidden="true">*</span></label><select id="metodo-venta" name="metodo_pago" required><option value="">Selecciona un método</option><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option></select>
<button class="btn btn-primary login-button" type="submit">Registrar venta</button><button class="btn btn-light login-button" type="button" data-clear-cart>Vaciar selección</button>
</form></aside>
</section>
<section class="panel"><div class="panel-header"><div><h2>Ventas registradas</h2><p class="muted">Importes y métodos de pago almacenados</p></div></div>
<div class="table-wrap"><table><thead><tr><th scope="col">Folio</th><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Cliente</th><th scope="col">Sucursal</th><th scope="col">Método</th><th scope="col">Partidas</th><th scope="col">Total</th></tr></thead><tbody>
<?php foreach ($datos['filas'] as $v) : ?><tr><td><?= escapar(folio('V', $v['id_venta'])) ?></td><td><?= escapar(fechaLocal($v['fecha'])) ?></td><td><?= escapar($v['nombre'] . ' ' . $v['apellido']) ?></td><td><?= escapar($v['cliente'] ?: 'Sin cliente asociado') ?></td><td><?= escapar($v['sucursal']) ?></td><td><?= escapar($v['metodo_pago']) ?></td><td><?= (int) $v['partidas'] ?></td><td class="amount"><?= moneda($v['total']) ?></td></tr><?php endforeach; ?>
<?php if ($datos['filas'] === []) : ?><tr><td colspan="8" class="empty-cell">No hay ventas registradas en el periodo seleccionado.</td></tr><?php endif; ?>
</tbody></table></div></section>
