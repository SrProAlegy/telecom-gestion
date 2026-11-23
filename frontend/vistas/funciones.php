<?php

declare(strict_types=1);

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function moneda(mixed $valor): string
{
    return '$' . number_format((float) $valor, 2, '.', ',');
}

function folio(string $prefijo, mixed $id): string
{
    return $prefijo . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function fechaLocal(?string $valor): string
{
    if ($valor === null || $valor === '') {
        return '—';
    }
    return (new DateTimeImmutable($valor, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('America/Mexico_City'))->format('d/m/Y H:i');
}

function etiquetaEstado(string $codigo): string
{
    return match ($codigo) {
        'listo_entrega', 'entregado' => 'status-ready',
        'cancelado', 'no_reparable' => 'status-inactive',
        'recibido', 'enviado_tecnico' => 'status-pending',
        default => 'status-progress',
    };
}

function icono(string $nombre): string
{
    $paths = [
        'inicio' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"/><path d="M9 21v-8h6v8"/>',
        'clientes' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6m1 4a5 5 0 0 1 3 5"/>',
        'reparaciones' => '<path d="m14 6 4 4m-7-7a6 6 0 0 0-6 8L2 18a2.8 2.8 0 0 0 4 4l7-7a6 6 0 0 0 8-6l-4 3-5-5 3-4Z"/>',
        'inventario' => '<path d="m3 7 9-4 9 4v11l-9 4-9-4Zm0 0 9 4 9-4M12 11v11M7 5l10 4"/>',
        'ventas' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h3M8 18h8"/>',
        'reportes' => '<path d="M4 3v18h17M8 16v-5m5 5V7m5 9V4"/>',
        'usuarios' => '<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'alerta' => '<path d="m12 3 10 18H2Zm0 6v5m0 3v1"/>',
        'conexion' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 12c0 4 16 4 16 0"/>',
        'flecha' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$nombre] ?? $paths['inicio']) . '</svg>';
}
