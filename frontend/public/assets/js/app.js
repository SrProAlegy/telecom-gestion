'use strict';

const button = document.querySelector('#check-status');
const status = document.querySelector('#system-status');

button?.addEventListener('click', async () => {
    button.disabled = true;
    status.textContent = 'Comprobando conexión…';
    try {
        const response = await fetch('/health', { cache: 'no-store', signal: AbortSignal.timeout(6000) });
        const data = await response.json();
        status.textContent = response.ok && data.status === 'ok'
            ? 'Conexión y esquema disponibles'
            : 'Base de datos no disponible. Revisa los servicios.';
    } catch {
        status.textContent = 'No se pudo conectar. Intenta de nuevo.';
    } finally {
        button.disabled = false;
    }
});
