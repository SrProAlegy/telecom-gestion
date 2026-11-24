<div class="report-actions"><a class="btn btn-light" href="/reportes/exportar.csv?<?= escapar(http_build_query(array_filter(['sucursal' => $filtro['sucursal'], 'desde' => $filtro['desde'], 'hasta' => $filtro['hasta']], static fn ($v) => $v !== null))) ?>">Descargar CSV</a>
<button type="button" class="btn btn-light" data-print>Imprimir reporte</button></div>
<section class="stats-grid three-cols">
<article class="stat-card"><span class="stat-icon blue"><?= icono('ventas') ?></span><div><p>Ventas registradas</p><strong><?= moneda($datos['resumen']['ventas']) ?></strong><small>Periodo seleccionado</small></div></article>
<article class="stat-card"><span class="stat-icon cyan"><?= icono('reparaciones') ?></span><div><p>Reparaciones recibidas</p><strong><?= (int) $datos['recibidas'] ?></strong><small>Periodo seleccionado</small></div></article>
<article class="stat-card"><span class="stat-icon amber"><?= icono('inventario') ?></span><div><p>Productos bajo mínimo</p><strong><?= (int) $datos['resumen']['bajo'] ?></strong><small>Existencias actuales, sin filtro de fecha</small></div></article>
</section>
<section class="content-grid equal-cols">
<article class="panel"><div class="panel-header"><div><h2>Ventas por sucursal</h2><p class="muted">Importes del periodo consultado</p></div></div><div class="table-wrap"><table><thead><tr><th scope="col">Sucursal</th><th scope="col">Ventas</th><th scope="col">Total</th></tr></thead><tbody>
<?php foreach ($datos['sucursales'] as $s) : ?><tr><td><?= escapar($s['nombre']) ?></td><td><?= (int) $s['cantidad'] ?></td><td class="amount"><?= moneda($s['total']) ?></td></tr><?php endforeach; ?>
<?php if ($datos['sucursales'] === []) : ?><tr><td colspan="3" class="empty-cell">No hay sucursales registradas.</td></tr><?php endif; ?>
</tbody></table></div></article>
<article class="panel"><div class="panel-header"><div><h2>Reparaciones por estado</h2><p class="muted">Estado actual de las reparaciones recibidas en el periodo</p></div></div><dl class="state-summary">
<?php foreach ($datos['estados'] as $estado) : ?><div><dt><span class="status <?= etiquetaEstado($estado['codigo']) ?>"><?= escapar($estado['nombre']) ?></span></dt><dd><?= (int) $estado['cantidad'] ?></dd></div><?php endforeach; ?>
</dl></article>
</section>
<section class="panel"><div class="panel-header"><div><h2>Inventario bajo el mínimo</h2><p class="muted">Existencias actuales por sucursal</p></div><a class="text-link" href="/inventario">Ver inventario <?= icono('flecha') ?></a></div><div class="table-wrap"><table><thead><tr><th scope="col">Producto</th><th scope="col">Sucursal</th><th scope="col">Existencia</th><th scope="col">Mínimo</th></tr></thead><tbody>
<?php foreach ($datos['stock'] as $p) : ?><tr><td><?= escapar($p['nombre']) ?></td><td><?= escapar($p['sucursal']) ?></td><td><span class="stock-warning"><?= (int) $p['cantidad'] ?></span></td><td><?= (int) $p['stock_minimo'] ?></td></tr><?php endforeach; ?>
<?php if ($datos['stock'] === []) : ?><tr><td colspan="4" class="empty-cell">No se encontraron existencias por debajo del mínimo.</td></tr><?php endif; ?>
</tbody></table></div><p class="panel-note">Existencias actuales de los productos bajo el mínimo establecido.</p></section>
