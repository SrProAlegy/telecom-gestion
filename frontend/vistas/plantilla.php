<?php
$paginas = [
    'inicio' => ['Panel principal', '/', 'Resumen general'],
    'clientes' => ['Clientes y equipos', '/clientes-equipos', 'Registros'],
    'reparaciones' => ['Reparaciones', '/reparaciones', 'Servicio técnico'],
    'inventario' => ['Productos e inventario', '/inventario', 'Almacén'],
    'ventas' => ['Ventas', '/ventas', 'Operaciones'],
    'reportes' => ['Consultas y reportes', '/reportes', 'Información'],
    'usuarios' => ['Usuarios y sucursales', '/usuarios-sucursales', 'Administración'],
    'acceso' => ['Iniciar sesión', '/acceso', 'Acceso'],
];
[$titulo, $ruta, $categoria] = $paginas[$pagina];
$sucursales = $catalogos['sucursales'];
$sucursalesVisibles = array_values(array_filter($sucursales, static fn (array $s): bool =>
    ($filtro['sucursal'] ?? null) === null || (int) $s['id_sucursal'] === $filtro['sucursal']));
$contenidoDisponible = $ready && $error === '';
$usuarioActual = $usuarioActual ?? null;
$csrfToken = $csrfToken ?? '';
$assetVersion = substr(hash('sha256', hash_file('sha256', __DIR__ . '/../public/assets/js/app.js')
    . hash_file('sha256', __DIR__ . '/../public/assets/css/app.css')), 0, 12);
require_once __DIR__ . '/formularios.php';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= escapar($csrfToken) ?>">
    <link rel="icon" type="image/png" href="/assets/Emblema%20Y%20Isom%C3%A9trico%20Azul%20con%20%C3%93rbita.png">
    <title><?= escapar($titulo) ?> | Telcom Gestión</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= escapar($assetVersion) ?>">
    <script src="/assets/js/app.js?v=<?= escapar($assetVersion) ?>" defer></script>
</head>
<?php if ($pagina === 'acceso') : ?>
<body class="access-page"><main class="access-content">
    <?php if ($error !== '') : ?><div class="alert alert-warning" role="alert"><?= escapar($error) ?></div><?php endif; ?>
    <?php require __DIR__ . '/acceso.php'; ?>
</main></body></html>
<?php return; endif; ?>
<body>
<a class="skip-link" href="#contenido">Ir al contenido</a>
<button class="nav-backdrop" type="button" aria-label="Cerrar menú" hidden></button>
<aside class="sidebar" id="menu-principal">
    <a class="brand" href="/" aria-label="Telcom Gestión, inicio"><img src="/assets/Logo%20TELCOM%20GESTI%C3%93N%20con%20emblema%20orbital.png" alt="Telcom Gestión" class="brand-logo"></a>
    <p class="nav-label">MENÚ PRINCIPAL</p>
    <nav class="nav-list" aria-label="Navegación principal">
        <?php foreach ($paginas as $clave => [$nombre, $enlace]) : ?>
            <?php if ($clave === 'acceso' || !verPagina($clave)) { continue; } ?>
            <a href="<?= escapar($enlace) ?>" class="<?= $clave === $pagina ? 'active' : '' ?>"
                <?= $clave === $pagina ? 'aria-current="page"' : '' ?>>
                <?= icono($clave) ?><span><?= escapar($nombre) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">Sistema de gestión multisucursal<small>Telcom Gestión</small></div>
</aside>
<div class="main">
    <header class="topbar">
        <div class="topbar-section">
            <button class="menu-toggle" type="button" aria-controls="menu-principal" aria-expanded="false" aria-label="Abrir menú"><?= icono('menu') ?></button>
            <span class="topbar-title">Telcom Gestión <span>/ <?= escapar($titulo) ?></span></span>
        </div>
        <div class="top-user"><span class="avatar"><?= icono('usuarios') ?></span>
            <?php if ($usuarioActual !== null) : ?><span class="session-label"><?= escapar($usuarioActual['nombre'] ?? '') ?><small><?= escapar($usuarioActual['sucursal_nombre'] ?? '') ?></small></span><button class="text-link plain-button" type="button" data-logout>Salir</button>
            <?php else : ?><a class="text-link" href="/acceso">Iniciar sesión</a><?php endif; ?>
        </div>
    </header>
    <main class="content" id="contenido" tabindex="-1">
        <?php if ($pagina !== 'acceso') : ?>
        <section class="page-heading"><div><p class="eyebrow"><?= escapar($categoria) ?></p><h1><?= escapar($titulo) ?></h1><p class="muted">Información registrada en Telcom.</p></div>

        <?php if ($contenidoDisponible && in_array($pagina, ['clientes', 'reparaciones', 'inventario', 'ventas', 'reportes'], true)) :
            $exportFilters = array_intersect_key($filtro, array_flip(['sucursal', 'q', 'estado', 'tecnico', 'desde', 'hasta']));
            $exportFilters['sucursal'] = $filtro['sucursal'] ?? ''; ?>
            <a class="btn btn-light report-actions" href="<?= escapar($ruta . '/exportar.xlsx?' . http_build_query($exportFilters)) ?>">Descargar Excel</a>
        <?php endif; ?>
        </section>

        <?php endif; ?>
        <?php if ($aviso !== '') : ?><div class="alert alert-success" role="status"><?= escapar($aviso) ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-warning" role="alert"><?= escapar($error) ?> <a href="<?= escapar($ruta) ?>">Restablecer consulta</a></div><?php endif; ?>
        <?php if ($pagina !== 'acceso' && $contenidoDisponible) : ?>
            <form class="filters-panel" method="get" action="<?= escapar($ruta) ?>" aria-label="Filtros de consulta">
                <?php if ($pagina !== 'clientes') : ?>
                    <label>Sucursal<select name="sucursal" aria-label="Sucursal"><option value=""><?= \Telcom\Soporte\Sesion::sucursalesCompartidas($pagina, $usuarioActual) ? 'Todas las sucursales registradas' : 'Mi sucursal' ?></option>
                    <?php foreach ($sucursales as $sucursal) : ?><option value="<?= (int) $sucursal['id_sucursal'] ?>" <?= (int) $sucursal['id_sucursal'] === ($filtro['sucursal'] ?? null) ? 'selected' : '' ?>><?= escapar($sucursal['nombre']) ?><?= $sucursal['activo'] ? '' : ' (inactiva)' ?></option><?php endforeach; ?>
                    </select></label>
                <?php endif; ?>
                <?php if (in_array($pagina, ['clientes', 'reparaciones', 'inventario', 'usuarios'], true)) : ?>
                    <label class="filter-search">Buscar<input type="search" name="q" value="<?= escapar($filtro['q']) ?>" maxlength="200" placeholder="Nombre<?= $pagina === 'usuarios' ? ' o correo' : ' o referencia' ?>"></label>
                <?php endif; ?>
                <?php if ($pagina === 'reparaciones') : ?><label>Estado<select name="estado" aria-label="Estado"><option value="">Todos los estados</option><?php foreach ($catalogos['estados'] as $estado) : ?><option value="<?= escapar($estado['codigo']) ?>" <?= $filtro['estado'] === $estado['codigo'] ? 'selected' : '' ?>><?= escapar($estado['nombre']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <?php if ($pagina === 'reparaciones') : ?><label>Técnico<select name="tecnico" aria-label="Técnico"><option value="">Todos los técnicos</option><option value="sin_asignar" <?= $filtro['tecnico'] === 'sin_asignar' ? 'selected' : '' ?>>Sin asignar</option><?php foreach ($catalogos['tecnicos'] as $tecnico) : ?><option value="<?= (int)$tecnico['id_tecnico'] ?>" <?= (string)$tecnico['id_tecnico'] === $filtro['tecnico'] ? 'selected' : '' ?>><?= escapar($tecnico['nombre'] . ' ' . $tecnico['apellido']) ?><?= $tecnico['activo'] ? '' : ' (inactivo)' ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <?php if ($pagina === 'usuarios') : ?><label>Cuenta<select name="activo" aria-label="Cuenta"><option value="">Todas</option><option value="1" <?= $filtro['activo'] === '1' ? 'selected' : '' ?>>Activa</option><option value="0" <?= $filtro['activo'] === '0' ? 'selected' : '' ?>>Inactiva</option></select></label><?php endif; ?>
                <?php if (in_array($pagina, ['ventas', 'reportes'], true)) : ?><label>Fecha inicial<input type="date" name="desde" value="<?= escapar($filtro['desde']) ?>" required></label><label>Fecha final<input type="date" name="hasta" value="<?= escapar($filtro['hasta']) ?>" required></label><?php endif; ?>
                <div class="filter-actions"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a class="btn btn-light" href="<?= escapar($ruta) ?>">Limpiar</a></div>
            </form>
        <?php endif; ?>
        <?php if ($contenidoDisponible || $pagina === 'acceso') : ?>
            <?php require __DIR__ . '/' . $pagina . '.php'; ?>
        <?php else : ?>
            <section class="panel"><div class="empty-state"><?= icono('alerta') ?><h2>Información no disponible</h2><p>Los indicadores y registros se muestran cuando la consulta se completa correctamente.</p></div></section>
        <?php endif; ?>
        <?php if ($contenidoDisponible && (($filtro['pagina'] ?? 1) > 1 || $hayMas)) : $actual = $filtro['pagina']; ?>
        <nav class="pagination-bar" aria-label="Páginas de resultados">
            <?php if ($actual > 1) : ?><a class="btn btn-light" href="<?= escapar($ruta . '?' . http_build_query(array_merge($_GET, ['pagina' => $actual - 1]))) ?>">Anterior</a><?php endif; ?>
            <span>Página <?= (int)$actual ?></span>
            <?php if ($hayMas) : ?><a class="btn btn-light" href="<?= escapar($ruta . '?' . http_build_query(array_merge($_GET, ['pagina' => $actual + 1]))) ?>">Siguiente</a><?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php if ($contenidoDisponible) { mostrarFormularios($pagina, $catalogos); } ?>
        <dialog class="form-dialog" id="detalle-registro" aria-labelledby="detalle-titulo"><header class="dialog-header"><h2 id="detalle-titulo">Detalle del registro</h2><button type="button" class="dialog-close" data-close aria-label="Cerrar detalle">×</button></header><div class="dialog-body" data-detail-content></div><footer class="dialog-footer"><button class="btn btn-light" type="button" data-close>Cerrar</button></footer></dialog>
        <footer class="content-foot">Telcom Gestión · Administración multisucursal</footer>
    </main>
</div>
</body>
</html>
