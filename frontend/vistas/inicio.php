<section class="stats-grid" aria-label="Resumen de operaciones">
    <article class="stat-card"><span class="stat-icon blue"><?= icono('reparaciones') ?></span><div><p>Reparaciones activas</p><strong><?= (int) $datos['resumen']['activas'] ?></strong><small>Sin entrega ni cierre</small></div></article>
    <article class="stat-card"><span class="stat-icon cyan"><?= icono('ventas') ?></span><div><p>Ventas del día</p><strong><?= moneda($datos['resumen']['ventas']) ?></strong><small>Importe registrado hoy</small></div></article>
    <article class="stat-card"><span class="stat-icon amber"><?= icono('inventario') ?></span><div><p>Productos con stock bajo</p><strong><?= (int) $datos['resumen']['bajo'] ?></strong><small>En el ámbito seleccionado</small></div></article>
    <article class="stat-card"><span class="stat-icon green"><?= icono('check') ?></span><div><p>Listos para entrega</p><strong><?= (int) $datos['resumen']['listas'] ?></strong><small>Según estado registrado</small></div></article>
</section>
<section class="content-grid">
    <article class="panel"><div class="panel-header"><div><h2>Reparaciones recientes</h2><p class="muted">Últimos equipos recibidos</p></div><a class="text-link" href="/reparaciones">Ver reparaciones <?= icono('flecha') ?></a></div>
        <div class="table-wrap"><table><thead><tr><th scope="col">Folio</th><th scope="col">Cliente</th><th scope="col">Equipo</th><th scope="col">Sucursal</th><th scope="col">Estado</th></tr></thead><tbody>
            <?php foreach ($datos['reparaciones'] as $r) : ?><tr><td><?= escapar(folio('REP', $r['id_reparacion'])) ?></td><td><?= escapar($r['cliente']) ?></td><td><?= escapar(trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')) ?: 'Sin datos de marca o modelo') ?></td><td><?= escapar($r['sucursal']) ?></td><td><span class="status <?= etiquetaEstado($r['estado']) ?>"><?= escapar($r['estado_nombre']) ?></span></td></tr><?php endforeach; ?>
            <?php if ($datos['reparaciones'] === []) : ?><tr><td colspan="5" class="empty-cell">No hay reparaciones registradas en esta consulta.</td></tr><?php endif; ?>
        </tbody></table></div>
    </article>
    <article class="panel"><div class="panel-header"><div><h2>Accesos rápidos</h2><p class="muted">Consulta por módulo</p></div></div><div class="quick-links">
        <?php foreach (['clientes' => ['Clientes y equipos', 'Consultar registros compartidos'], 'reparaciones' => ['Reparaciones', 'Consultar recepción y estado'], 'inventario' => ['Inventario', 'Ver existencias por sucursal'], 'ventas' => ['Ventas', 'Consultar operaciones registradas']] as $clave => [$nombre, $descripcion]) : ?>
            <a href="<?= escapar($paginas[$clave][1]) ?>"><span class="quick-icon"><?= icono($clave) ?></span><span><b><?= escapar($nombre) ?></b><small><?= escapar($descripcion) ?></small></span><?= icono('flecha') ?></a>
        <?php endforeach; ?>
    </div></article>
</section>
<section class="panel"><div class="panel-header"><div><h2>Actividad registrada</h2><p class="muted">Ventas y eventos del historial de reparaciones</p></div></div>
    <?php if ($datos['actividad'] === []) : ?><div class="empty-state compact"><?= icono('reportes') ?><h3>Sin actividad registrada</h3><p>La base no contiene ventas ni eventos de reparación para esta consulta.</p></div><?php else : ?><ul class="activity-list">
        <?php foreach ($datos['actividad'] as $evento) : ?><li><span class="activity-dot <?= $evento['tipo'] === 'venta' ? 'green-dot' : 'blue-dot' ?>"></span><div><b><?= escapar($evento['descripcion']) ?></b><p><?= escapar(folio($evento['tipo'] === 'venta' ? 'V' : 'REP', $evento['referencia'])) ?> · <?= escapar($evento['sucursal']) ?></p></div><time><?= escapar(fechaLocal($evento['fecha'])) ?></time></li><?php endforeach; ?>
    </ul><?php endif; ?>
</section>
