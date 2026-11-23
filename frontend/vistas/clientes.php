<section class="stats-grid three-cols">
    <?php foreach ([['clientes', 'Clientes registrados', 'clientes'], ['equipos', 'Equipos registrados', 'inventario'], ['con_equipo', 'Clientes con equipos', 'check']] as [$campo, $etiqueta, $icon]) : ?><article class="stat-card"><span class="stat-icon blue"><?= icono($icon) ?></span><div><p><?= escapar($etiqueta) ?></p><strong><?= (int) $datos['totales'][$campo] ?></strong><small>Catálogo compartido</small></div></article><?php endforeach; ?>
</section>
<section class="panel"><div class="panel-header"><div><h2>Listado de clientes</h2><p class="muted">Nombre, teléfono y correo registrados</p></div><button class="btn btn-primary" type="button" data-open="form-cliente">Nuevo cliente</button></div>
<div class="table-wrap"><table><thead><tr><th scope="col">Cliente</th><th scope="col">Teléfono</th><th scope="col">Correo</th><th scope="col">Equipos</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($datos['clientes'] as $c) : ?><tr><td><?= escapar($c['nombre']) ?></td><td><?= escapar($c['telefono'] ?: '—') ?></td><td><?= escapar($c['correo'] ?: '—') ?></td><td><?= (int) $c['equipos'] ?></td><td><?php botonEditar("form-cliente", $c, "id_cliente"); ?></td></tr><?php endforeach; ?>
<?php if ($datos['clientes'] === []) : ?><tr><td colspan="5" class="empty-cell">No se encontraron clientes.</td></tr><?php endif; ?>
</tbody></table></div><p class="panel-note">Los clientes y sus equipos forman parte del catálogo compartido de Telcom.</p></section>
<section class="panel"><div class="panel-header"><div><h2>Equipos registrados</h2><p class="muted">Dispositivos asociados a un cliente</p></div><button class="btn btn-primary" type="button" data-open="form-equipo">Nuevo equipo</button></div>
<div class="table-wrap"><table><thead><tr><th scope="col">Cliente</th><th scope="col">Marca / modelo</th><th scope="col">IMEI</th><th scope="col">Color</th><th scope="col">Estado físico</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($datos['equipos'] as $e) : ?><tr><td><?= escapar($e['cliente']) ?></td><td><?= escapar(trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? '')) ?: '—') ?></td><td><?= escapar($e['imei'] ?: '—') ?></td><td><?= escapar($e['color'] ?: '—') ?></td><td class="text-cell"><?= escapar($e['estado_fisico'] ?: '—') ?></td><td><?php botonEditar("form-equipo", $e, "id_equipo"); ?></td></tr><?php endforeach; ?>
<?php if ($datos['equipos'] === []) : ?><tr><td colspan="6" class="empty-cell">No se encontraron equipos.</td></tr><?php endif; ?>
</tbody></table></div></section>
