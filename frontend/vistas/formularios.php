<?php

declare(strict_types=1);

function botonEditar(string $formulario, array $registro, string $clave): void
{
    echo '<button type="button" class="text-link plain-button" data-open="' . escapar($formulario)
        . '" data-record="' . escapar(json_encode($registro, JSON_UNESCAPED_UNICODE))
        . '" data-id-key="' . escapar($clave) . '">Editar</button>';
}

function mostrarFormularios(string $pagina, array $catalogos): void
{
    $opciones = static function (array $filas, string $clave, callable $nombre): array {
        return array_map(static fn (array $fila): array => [$fila[$clave], $nombre($fila)], $filas);
    };
    $sucursales = $opciones($catalogos['sucursales'], 'id_sucursal', static fn ($s) => $s['nombre']);
    $clientes = $opciones($catalogos['clientes'], 'id_cliente', static fn ($c) => $c['nombre']);
    $equipos = array_map(static fn ($e) => [$e['id_equipo'], trim(($e['marca'] ?? '') . ' '
        . ($e['modelo'] ?? '')) . ' · ' . folio('EQ', $e['id_equipo']), $e['cliente_id']], $catalogos['equipos']);
    $tecnicos = $opciones($catalogos['tecnicos'], 'id_tecnico', static fn ($t) => $t['nombre'].' '.$t['apellido']);
    $categorias = $opciones($catalogos['categorias'], 'id_categoria', static fn ($c) => $c['nombre']);
    $productos = $opciones($catalogos['productos'], 'id_producto', static fn ($p) => $p['nombre']);
    $estados = $opciones($catalogos['estados'], 'codigo', static fn ($e) => $e['nombre']);
    $activo = ['activo', 'Estado', 'select', true, [[1, 'Activo'], [0, 'Inactivo']]];
    $nombre = ['nombre', 'Nombre', 'text', true, 100];
    $apellido = ['apellido', 'Apellido', 'text', true, 150];
    $telefono = ['telefono', 'Teléfono', 'tel', false, 25];
    $forms = [
        'clientes' => [
            ['cliente', 'cliente', '/api/clientes', [
                ['nombre', 'Nombre completo', 'text', true, 200], $telefono,
                ['correo', 'Correo electrónico', 'email', false, 254],
            ]],
            ['equipo', 'equipo', '/api/equipos', [
                ['cliente_id', 'Cliente', 'select', true, $clientes],
                ['marca', 'Marca', 'text', false, 80], ['modelo', 'Modelo', 'text', false, 100],
                ['imei', 'IMEI', 'imei', false, 15], ['color', 'Color', 'text', false, 60],
                ['estado_fisico', 'Estado físico', 'textarea', false, 10000],
            ]],
        ],
        'reparaciones' => [
            ['reparacion', 'reparación', '/api/reparaciones', [
                ['', 'Cliente', 'cliente-filtro', false, $clientes],
                ['equipo_id', 'Equipo', 'select', true, $equipos],
                ['sucursal_id', 'Sucursal receptora', 'select', true, $sucursales],
                ['tecnico_id', 'Técnico', 'select', false, $tecnicos],
                ['problema_reportado', 'Problema reportado', 'textarea', true, 10000],
            ]],
            ['seguimiento', 'seguimiento de reparación', '/api/reparaciones', [
                ['tecnico_id', 'Técnico', 'select', false, $tecnicos],
                ['estado', 'Estado', 'select', true, $estados],
                ['diagnostico', 'Diagnóstico', 'textarea', false, 10000],
                ['observaciones', 'Observaciones', 'textarea', false, 10000],
            ]],
        ],
        'inventario' => [
            ['producto', 'producto', '/api/productos', [
                ['nombre', 'Nombre', 'text', true, 150],
                ['categoria_id', 'Categoría', 'select', true, $categorias],
                ['descripcion', 'Descripción', 'textarea', false, 10000],
                ['precio', 'Precio de venta', 'money', true, '9999999999.99'],
                ['stock_minimo', 'Stock mínimo', 'number', true, 4294967295], $activo,
            ]],
            ['categoria', 'categoría', '/api/categorias', [
                ['nombre', 'Nombre', 'text', true, 100], ['descripcion', 'Descripción', 'textarea', false, 10000],
            ]],
            ['stock', 'existencias', '/api/inventario', [
                ['producto_id', 'Producto', 'select', true, $productos],
                ['sucursal_id', 'Sucursal', 'select', true, $sucursales],
                ['cantidad', 'Cantidad disponible', 'number', true, 2147483647],
            ]],
        ],
        'usuarios' => [
            ['usuario', 'usuario', '/api/usuarios', [$nombre, $apellido,
                ['correo', 'Correo electrónico', 'email', true, 254],
                ['password', 'Contraseña', 'password', true, 128],
                ['rol', 'Rol', 'select', true, [['administrador', 'Administrador'], ['recepcion', 'Recepción'],
                    ['tecnico', 'Técnico'], ['ventas', 'Ventas']]],
                ['sucursal_id', 'Sucursal', 'select', true, $sucursales], $activo,
            ]],
            ['sucursal', 'sucursal', '/api/sucursales', [
                ['nombre', 'Nombre', 'text', true, 120], ['direccion', 'Dirección', 'text', false, 255],
                $telefono, $activo,
            ]],
            ['tecnico', 'técnico', '/api/tecnicos', [$nombre, $apellido, $telefono,
                ['especialidad', 'Especialidad', 'text', false, 150], $activo,
            ]],
        ],
    ];
    foreach ($forms[$pagina] ?? [] as [$id, $titulo, $endpoint, $campos]) {
        ?>
        <dialog class="form-dialog" id="form-<?= escapar($id) ?>" aria-labelledby="titulo-<?= escapar($id) ?>">
            <form action="<?= escapar($endpoint) ?>" method="post" data-api-form data-entity="<?= escapar($titulo) ?>">
                <header class="dialog-header"><div><p class="eyebrow">Telcom Gestión</p><h2 id="titulo-<?= escapar($id) ?>" data-dialog-title>Registrar <?= escapar($titulo) ?></h2></div><button type="button" class="dialog-close" data-close aria-label="Cerrar formulario">×</button></header>
                <div class="dialog-body"><p class="required-note">Los campos con * son obligatorios.</p><div class="form-message" role="alert" hidden></div><input type="hidden" name="id" value="">
                <div class="form-grid">
                <?php foreach ($campos as [$name, $label, $type, $required, $extra]) : $fieldId = $id . '-' . ($name ?: 'cliente'); ?>
                    <div class="form-field <?= $type === 'textarea' ? 'span-2' : '' ?>"><label for="<?= escapar($fieldId) ?>"><?= escapar($label) ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                    <?php if (in_array($type, ['select', 'cliente-filtro'], true)) : ?>
                        <select id="<?= escapar($fieldId) ?>" <?= $name !== '' ? 'name="'.escapar($name).'"' : 'data-filter-client' ?> <?= $required ? 'required' : '' ?>>
                            <?php if ($name !== 'activo') : ?><option value=""><?= $name === 'tecnico_id' ? 'Por asignar' : 'Selecciona una opción' ?></option><?php endif; ?>
                            <?php foreach ($extra as $opcion) : ?><option value="<?= escapar($opcion[0]) ?>" <?= isset($opcion[2]) ? 'data-cliente-id="'.(int)$opcion[2].'"' : '' ?>><?= escapar($opcion[1]) ?></option><?php endforeach; ?>
                        </select>
                    <?php elseif ($type === 'textarea') : ?><textarea id="<?= escapar($fieldId) ?>" name="<?= escapar($name) ?>" rows="3" maxlength="<?= (int) $extra ?>" <?= $required ? 'required' : '' ?>></textarea>
                    <?php else : ?><input id="<?= escapar($fieldId) ?>" name="<?= escapar($name) ?>" type="<?= in_array($type, ['money','number'], true) ? 'number' : ($type === 'imei' ? 'text' : escapar($type)) ?>" <?= $required ? 'required' : '' ?>
                        <?php if (in_array($type, ['money','number'], true)) : ?>min="0" max="<?= escapar($extra) ?>" step="<?= $type === 'money' ? '0.01' : '1' ?>" value="<?= $name === 'stock_minimo' ? '0' : '' ?>"
                        <?php else : ?>maxlength="<?= (int) $extra ?>" <?= $type === 'password' ? 'autocomplete="new-password"' : '' ?> <?= $type === 'imei' ? 'pattern="[0-9]{15}" inputmode="numeric" title="El IMEI contiene 15 dígitos."' : '' ?><?php endif; ?>>
                    <?php endif; ?>
                    <?php if ($type === 'password') : ?><small class="field-help" data-password-help hidden>Deja el campo vacío para conservar la contraseña.</small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div></div><footer class="dialog-footer"><button class="btn btn-light" type="button" data-close>Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></footer>
            </form>
        </dialog>
        <?php
    }
}
