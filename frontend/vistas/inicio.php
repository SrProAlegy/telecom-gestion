<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telcom Gestión · Base del sistema</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<header class="border-bottom bg-white"><div class="container py-3 d-flex align-items-center gap-3">
    <span class="brand-mark" aria-hidden="true">T</span><div><strong>Telcom Gestión</strong><div class="small text-secondary">Sistema administrativo multisucursal</div></div>
    <span class="badge text-bg-light ms-auto">Base de desarrollo</span>
</div></header>
<main class="container py-5">
    <div class="row g-4 align-items-center mb-5">
        <div class="col-lg-8"><p class="eyebrow">ESTADO DEL SISTEMA</p><h1 class="display-5 fw-semibold">Telcom Gestión</h1>
            <p class="lead text-secondary">Entorno local de gestión multisucursal.</p>
            <p>Esta pantalla comprueba la conexión con MySQL y la disponibilidad del esquema. Los módulos administrativos no están habilitados.</p></div>
        <div class="col-lg-4"><section class="card status-card"><div class="card-body p-4"><h2 class="h6">Comprobación inicial</h2>
            <p id="system-status" class="fw-semibold" role="status" aria-live="polite"><?= $ready ? 'Conexión y esquema disponibles' : 'Base de datos no disponible' ?></p>
            <p class="small text-secondary">Apache · PHP · PDO · MySQL</p><button class="btn btn-primary" id="check-status" type="button">Comprobar de nuevo</button>
        </div></section></div>
    </div>
    <section aria-labelledby="modules-title"><div class="d-flex flex-wrap justify-content-between align-items-baseline mb-3"><h2 id="modules-title" class="h4">Módulos del sistema</h2><span class="text-secondary small">Sin funcionalidad habilitada</span></div>
        <div class="row g-3">
            <?php foreach ([
                ['Usuarios y sucursales', 'Acceso, roles y asociación de usuarios con cada sede.'],
                ['Clientes y equipos', 'Datos del cliente y dispositivos recibidos.'],
                ['Reparaciones', 'Recepción, asignación técnica e historial de seguimiento.'],
                ['Productos e inventario', 'Catálogo compartido y existencias por sucursal.'],
                ['Ventas', 'Detalle, pago y actualización transaccional del inventario.'],
                ['Consultas y reportes', 'Consulta de ventas, existencias y reparaciones.'],
            ] as [$title, $description]) : ?>
                <div class="col-md-6 col-xl-4"><article class="card h-100"><div class="card-body p-4"><span class="badge pending mb-3">No habilitado</span><h3 class="h5"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3><p class="text-secondary mb-0"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p></div></article></div>
            <?php endforeach; ?>
        </div>
    </section>
    <aside class="alert alert-light border mt-4">El inicio de sesión no está habilitado. Las cuentas de MySQL permiten acceder a la base de datos, no iniciar sesión en esta aplicación.</aside>
</main>
<footer class="container py-4 text-secondary small border-top">Telcom Gestión · HTML / CSS / JavaScript / Bootstrap · PHP por capas · MySQL</footer>
</body>
</html>
