'use strict';

const toggle = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('.sidebar');
const backdrop = document.querySelector('.nav-backdrop');
const mobileNavigation = window.matchMedia?.('(max-width: 991px)');

function closeMenu() {
    sidebar?.classList.remove('is-open');
    if (sidebar) { sidebar.inert = Boolean(mobileNavigation?.matches); }
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'Abrir menú');
    if (backdrop) { backdrop.hidden = true; }
}
toggle?.addEventListener('click', () => {
    const open = sidebar.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    backdrop.hidden = !open;
    sidebar.inert = Boolean(mobileNavigation?.matches && !open);
    if (open) { sidebar.querySelector('a')?.focus(); }
});
backdrop?.addEventListener('click', closeMenu);
mobileNavigation?.addEventListener('change', closeMenu);
closeMenu();

function message(form, text) {
    const box = form.querySelector('.form-message');
    if (!box) { return; }
    box.textContent = text;
    box.hidden = !text;
}

function validarCampo(field) {
    field.setCustomValidity('');
    if (field.type === 'hidden' || field.tagName === 'SELECT' || field.disabled) { return; }
    const value = field.value;
    if (field.required && !value.trim()) {
        field.setCustomValidity('Completa este campo con un valor distinto de espacios.');
    } else if (value && /[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/.test(value)) {
        field.setCustomValidity('El campo contiene caracteres no válidos.');
    } else if (field.name === 'precio' && value && !/^\d{1,10}(\.\d{1,2})?$/.test(value)) {
        field.setCustomValidity('Usa un importe con hasta dos decimales, sin notación exponencial.');
    } else if (field.type === 'tel' && value.trim()) {
        const digits = value.replace(/[^0-9]/g, '');
        if (!/^\+?[0-9(][0-9 ().-]*$/.test(value.trim()) || digits.length < 7 || digits.length > 15) {
            field.setCustomValidity('Indica de 7 a 15 dígitos; puedes usar +, espacios, paréntesis, puntos o guiones.');
        }
    } else if (field.type === 'password' && field.autocomplete === 'new-password' && value) {
        const size = new TextEncoder().encode(value).length;
        if (!value.trim() || size < 4 || size > 72) { field.setCustomValidity('Usa entre 4 y 72 bytes para la contraseña.'); }
    }
}

function requisitosSeguimiento(form) {
    if (!form.dataset.transiciones) { return; }
    const state = form.elements.namedItem('estado').value;
    const technician = form.elements.namedItem('tecnico_id');
    const diagnosis = form.elements.namedItem('diagnostico');
    technician.required = ['enviado_tecnico', 'diagnostico', 'en_reparacion', 'listo_entrega', 'entregado', 'no_reparable'].includes(state);
    diagnosis.required = ['en_reparacion', 'listo_entrega', 'entregado', 'no_reparable'].includes(state);
    for (const field of [technician, diagnosis]) {
        field.setCustomValidity('');
        field.closest('.form-field').querySelector('label').classList.toggle('required-field', field.required);
    }
}

document.querySelectorAll('[data-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.open);
        const form = dialog?.querySelector('form');
        if (!dialog || !form) { return; }
        if (form.getAttribute('aria-busy') === 'true') { return; }
        form.reset();
        message(form, '');
        form.querySelectorAll('[aria-invalid]').forEach((field) => field.removeAttribute('aria-invalid'));
        Array.from(form.elements).forEach((field) => field.setCustomValidity?.(''));
        form.dataset.baseAction ||= form.getAttribute('action');
        const record = button.dataset.record ? JSON.parse(button.dataset.record) : null;
        const id = record?.[button.dataset.idKey];
        form.action = id ? `${form.dataset.baseAction}/${encodeURIComponent(id)}` : form.dataset.baseAction;
        form.dataset.method = id ? 'PATCH' : 'POST';
        const title = dialog.querySelector('[data-dialog-title]');
        if (title) { title.textContent = `${id ? 'Editar' : 'Registrar'} ${form.dataset.entity}`; }
        if (record) {
            Array.from(form.elements).forEach((field) => {
                if (field.name === 'password' || !field.name || !(field.name in record)) { return; }
                field.value = record[field.name] ?? '';
            });
        }
        const idField = form.elements.namedItem('id');
        if (idField) { idField.value = id || ''; }
        const password = form.elements.namedItem('password');
        if (password) { password.required = !id; }
        const passwordHelp = form.querySelector('[data-password-help]');
        if (passwordHelp) { passwordHelp.hidden = !id; }
        form.querySelectorAll('option[data-cliente-id]').forEach((option) => {
            option.hidden = false;
            option.disabled = false;
        });
        form.querySelectorAll('option[data-inactivo]').forEach((option) => {
            option.disabled = !record || String(record[option.parentElement.name]) !== option.value;
        });
        if (form.dataset.transiciones && record) {
            const transitions = JSON.parse(form.dataset.transiciones);
            const allowed = [record.estado, ...(transitions[record.estado] || [])];
            for (const option of form.elements.namedItem('estado').options) {
                option.disabled = !allowed.includes(option.value) || (form.dataset.rol === 'tecnico' && ['entregado', 'cancelado'].includes(option.value));
                option.hidden = option.disabled;
            }
            requisitosSeguimiento(form);
        }
        if (!record) {
            form.querySelectorAll('select[required]').forEach((select) => {
                const options = Array.from(select.options).filter((option) => option.value && !option.disabled);
                if (options.length === 1) { select.value = options[0].value; }
            });
        }
        dialog.showModal();
        if (form === stockForm) { stockForm.dispatchEvent(new Event('stock-open')); }
    });
});

document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = button.closest('dialog');
        if (dialog.querySelector('[aria-busy="true"]')) { return; }
        dialog.close();
    });
});
document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('cancel', (event) => {
        if (dialog.querySelector('[aria-busy="true"]')) { event.preventDefault(); }
    });
});
document.querySelector('[data-transiciones] select[name="estado"]')?.addEventListener('change', (event) => requisitosSeguimiento(event.target.form));

document.querySelectorAll('[data-filter-client]').forEach((select) => {
    select.addEventListener('change', () => {
        const equipment = select.form.elements.namedItem('equipo_id');
        if (!equipment) { return; }
        equipment.value = '';
        Array.from(equipment.options).forEach((option) => {
            const hide = !!select.value && !!option.value && option.dataset.clienteId !== select.value;
            option.hidden = hide;
            option.disabled = hide;
        });
    });
});
document.querySelectorAll('[data-password]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.password);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-pressed', String(show));
    });
});
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') {
        closeMenu();
        toggle.focus();
    }
});

const cart = new Map();
const saleForm = document.querySelector('#form-venta');
const currency = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

function cents(value) {
    if (!/^\d{1,10}(\.\d{1,2})?$/.test(value)) { throw new Error('El precio del producto no es válido.'); }
    const [integer, fraction = ''] = value.split('.');
    return Number(integer) * 100 + Number(fraction.padEnd(2, '0'));
}
function cartTotal() {
    let total = 0;
    for (const item of cart.values()) {
        total += item.price * item.quantity;
        if (!Number.isSafeInteger(total) || total > 999999999999) { throw new Error('El importe de la venta supera el límite permitido.'); }
    }
    return total;
}
function renderCart() {
    if (!saleForm) { return; }
    const box = saleForm.querySelector('[data-cart-items]');
    box.replaceChildren();
    if (!cart.size) {
        const empty = document.createElement('p');
        empty.className = 'empty-cart';
        empty.textContent = 'Selecciona productos para iniciar la venta.';
        box.append(empty);
    }
    for (const [id, item] of cart) {
        const row = document.createElement('div');
        row.className = 'cart-item';
        const description = document.createElement('div');
        const name = document.createElement('b');
        name.textContent = item.name;
        const price = document.createElement('small');
        price.textContent = currency.format(item.price / 100);
        description.append(name, price);
        const quantity = document.createElement('input');
        quantity.type = 'number';
        quantity.min = '1';
        quantity.max = String(Math.min(item.stock, 2147483647));
        quantity.step = '1';
        quantity.value = String(item.quantity);
        quantity.setAttribute('aria-label', `Cantidad de ${item.name}`);
        quantity.required = true;
        quantity.dataset.cartQuantity = '';
        const updateQuantity = () => {
            quantity.setCustomValidity('');
            const value = Number(quantity.value);
            if (!quantity.value || !Number.isInteger(value) || value < 1 || value > Math.min(item.stock, 2147483647)) {
                quantity.setCustomValidity('Indica una cantidad dentro de las existencias disponibles.');
                return;
            }
            const previous = item.quantity;
            item.quantity = value;
            try {
                cartTotal();
                subtotal.textContent = currency.format(item.quantity * item.price / 100);
                updateCartTotals();
                message(saleForm, '');
            } catch (error) {
                item.quantity = previous;
                quantity.setCustomValidity(error.message);
                message(saleForm, error.message);
            }
        };
        quantity.addEventListener('input', updateQuantity);
        quantity.addEventListener('change', updateQuantity);
        const subtotal = document.createElement('strong');
        subtotal.textContent = currency.format(item.quantity * item.price / 100);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.setAttribute('aria-label', `Quitar ${item.name}`);
        remove.addEventListener('click', () => { cart.delete(id); renderCart(); });
        row.append(description, quantity, subtotal, remove);
        box.append(row);
    }
    updateCartTotals();
}
function updateCartTotals() {
    const total = currency.format(cartTotal() / 100);
    saleForm.querySelector('[data-cart-subtotal]').textContent = total;
    saleForm.querySelector('[data-cart-total]').textContent = total;
    saleForm.querySelector('[data-cart-payload]').value = JSON.stringify(
        Array.from(cart, ([producto_id, item]) => ({ producto_id: Number(producto_id), cantidad: item.quantity }))
    );
}
document.querySelectorAll('[data-add-product]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!saleForm.elements.namedItem('sucursal_id').value) {
            message(saleForm, 'Selecciona una sucursal y aplica el filtro para iniciar la venta.');
            document.querySelector('select[name="sucursal"]')?.focus();
            return;
        }
        const id = button.dataset.productId;
        const stock = Number(button.dataset.productStock);
        const previous = cart.get(id);
        const quantity = (previous?.quantity || 0) + 1;
        if (!Number.isInteger(stock) || quantity > stock) {
            message(saleForm, 'No hay existencias suficientes de este producto.');
            return;
        }
        try {
            cart.set(id, { name: button.dataset.productName, price: cents(button.dataset.productPrice), stock, quantity });
            cartTotal();
            message(saleForm, '');
            renderCart();
        } catch (error) {
            if (previous) { cart.set(id, previous); } else { cart.delete(id); }
            message(saleForm, error.message);
        }
    });
});
document.querySelector('[data-clear-cart]')?.addEventListener('click', () => {
    cart.clear();
    message(saleForm, '');
    renderCart();
});

function payload(form) {
    const result = {};
    for (const [key, value] of new FormData(form)) {
        if (key === 'id' || (key === 'password' && value === '' && form.dataset.method === 'PATCH')) { continue; }
        if (key === 'detalles') { result[key] = JSON.parse(value); }
        else if (key.endsWith('_id') || ['cantidad', 'cantidad_anterior', 'revision', 'stock_minimo', 'activo'].includes(key)) {
            result[key] = value === '' ? null : Number(value);
            if (result[key] !== null && !Number.isSafeInteger(result[key])) {
                throw new Error('Revisa los valores numéricos del formulario.');
            }
        } else { result[key] = value === '' ? null : value; }
    }
    return result;
}
async function request(url, method, data) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (token) { headers['X-CSRF-Token'] = token; }
    const response = await fetch(url, {
        method, headers, credentials: 'same-origin', redirect: 'error',
        body: JSON.stringify(data), signal: AbortSignal.timeout(15000)
    });
    let body = {};
    if (response.headers.get('content-type')?.includes('application/json')) {
        try { body = await response.json(); } catch {
            throw new Error('La respuesta recibida no es válida. Inténtalo de nuevo.');
        }
        if (!body || typeof body !== 'object' || Array.isArray(body)) {
            throw new Error('La respuesta recibida no es válida. Inténtalo de nuevo.');
        }
    }
    return { response, body };
}
document.querySelectorAll('[data-api-form]').forEach((form) => {
    form.addEventListener('input', (event) => {
        if (event.target.setCustomValidity && !event.target.hasAttribute('data-cart-quantity')) { validarCampo(event.target); }
        event.target.removeAttribute('aria-invalid');
        if (form.closest('dialog')?.id === 'form-equipo' && ['marca', 'modelo', 'imei'].includes(event.target.name)) {
            form.elements.namedItem('marca').setCustomValidity('');
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        for (const field of form.elements) {
            if (field.setCustomValidity && !field.hasAttribute('data-cart-quantity')) { validarCampo(field); }
        }
        if (form.closest('dialog')?.id === 'form-equipo' && !['marca', 'modelo', 'imei'].some((name) => form.elements.namedItem(name).value.trim())) {
            form.elements.namedItem('marca').setCustomValidity('Registra al menos la marca, el modelo o el IMEI para identificar el equipo.');
        }
        if (!form.reportValidity()) { return; }
        if (form === saleForm && (!cart.size || !form.elements.namedItem('sucursal_id').value)) {
            message(form, 'Selecciona una sucursal y agrega al menos un producto.');
            return;
        }
        const submit = form.querySelector('[type="submit"]');
        if (submit.disabled) { return; }
        let data;
        try { data = payload(form); } catch { message(form, 'Revisa los valores del formulario.'); return; }
        const locked = Array.from(form.elements).filter((field) => !field.disabled);
        locked.forEach((field) => { field.disabled = true; });
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        message(form, '');
        try {
            const { response, body } = await request(form.action, form.dataset.method || 'POST', data);
            if (response.status === 401 && !form.hasAttribute('data-login')) { window.location.assign('/acceso'); return; }
            if (!response.ok || body.ok !== true) {
                locked.forEach((field) => { field.disabled = false; });
                for (const [name, text] of Object.entries(body.errores || {})) {
                    const field = form.elements.namedItem(name);
                    if (field?.setCustomValidity && typeof text === 'string') {
                        field.setCustomValidity(text);
                        field.setAttribute('aria-invalid', 'true');
                    }
                }
                form.reportValidity();
                throw new Error(typeof body.mensaje === 'string' ? body.mensaje : 'No fue posible completar la solicitud. Inténtalo de nuevo.');
            }
            const redirect = new URL(body.redirect || '/', window.location.origin);
            if (redirect.origin !== window.location.origin) { throw new Error('La respuesta recibida no es válida.'); }
            if (form.hasAttribute('data-login') || body.redirect) { window.location.assign(redirect.href); }
            else { window.location.reload(); }
        } catch (error) {
            message(form, error instanceof TypeError || error.name === 'TimeoutError'
                ? 'No se pudo conectar. Inténtalo de nuevo.' : error.message);
        } finally {
            locked.forEach((field) => { field.disabled = false; });
            submit.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
});
document.querySelector('[data-logout]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
        const { response, body } = await request('/api/sesion', 'DELETE', {});
        if (!response.ok || body.ok !== true) { throw new Error('No se pudo cerrar la sesión.'); }
        window.location.assign('/acceso');
    } catch (error) {
        button.disabled = false;
        const notice = document.createElement('p');
        notice.className = 'form-message';
        notice.setAttribute('role', 'alert');
        notice.textContent = error.message;
        document.querySelector('#contenido').prepend(notice);
    }
});

document.querySelectorAll('[data-detail]').forEach((button) => {
    button.addEventListener('click', async () => {
        const dialog = document.querySelector('#detalle-registro');
        const content = dialog.querySelector('[data-detail-content]');
        dialog.querySelector('h2').textContent = button.dataset.detailTitle;
        content.textContent = 'Consultando información…';
        dialog.showModal();
        const labels = { producto: 'Producto', cantidad: 'Cantidad', precio_unitario: 'Precio unitario',
            subtotal: 'Subtotal', estado: 'Estado', diagnostico: 'Diagnóstico', observaciones: 'Observaciones',
            fecha: 'Fecha', usuario: 'Responsable', apellido: 'Apellido', sucursal: 'Sucursal del operador', sucursal_receptora: 'Sucursal receptora',
            tecnico: 'Técnico', tecnico_apellido: 'Apellido del técnico' };
        try {
            const response = await fetch(button.dataset.detail, { credentials: 'same-origin',
                headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(15000) });
            if (response.status === 401) { window.location.assign('/acceso'); return; }
            if (!response.ok) { throw new Error('No fue posible consultar el detalle.'); }
            const body = await response.json();
            if (body.ok !== true || !Array.isArray(body.registros)) { throw new Error('La respuesta no es válida.'); }
            content.replaceChildren();
            for (const row of body.registros) {
                const dl = document.createElement('dl');
                dl.className = 'detail-entry';
                for (const [key, value] of Object.entries(row)) {
                    if (!labels[key]) { continue; }
                    const dt = document.createElement('dt');
                    const dd = document.createElement('dd');
                    dt.textContent = labels[key];
                    dd.textContent = key === 'fecha' && value
                        ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Mexico_City' }).format(new Date(value.replace(' ', 'T') + 'Z'))
                        : value ?? 'Sin registrar';
                    dl.append(dt, dd);
                }
                content.append(dl);
            }
            if (!body.registros.length) { content.textContent = 'No hay registros asociados.'; }
        } catch { content.textContent = 'No fue posible consultar el detalle. Inténtalo de nuevo.'; }
    });
});

const stockForm = document.querySelector('#form-stock form');
if (stockForm) {
    let generation = 0;
    const loadStock = async () => {
        const current = ++generation;
        const product = stockForm.elements.namedItem('producto_id').value;
        const branch = stockForm.elements.namedItem('sucursal_id').value;
        stockForm.elements.namedItem('cantidad_anterior').value = '';
        stockForm.querySelector('[type="submit"]').disabled = true;
        stockForm.elements.namedItem('cantidad').value = '';
        if (!product || !branch) { return; }
        try {
            const response = await fetch(`/api/inventario?producto_id=${encodeURIComponent(product)}&sucursal_id=${encodeURIComponent(branch)}`,
                { credentials: 'same-origin', signal: AbortSignal.timeout(15000) });
            const body = await response.json();
            if (current !== generation) { return; }
            if (!response.ok || body.ok !== true || !Number.isInteger(body.cantidad)) { throw new Error(); }
            stockForm.elements.namedItem('cantidad_anterior').value = String(body.cantidad);
            stockForm.elements.namedItem('cantidad').value = String(body.cantidad);
            stockForm.querySelector('[type="submit"]').disabled = false;
            message(stockForm, '');
        } catch {
            if (current === generation) { message(stockForm, 'No se pudo consultar la existencia. Selecciona de nuevo el producto.'); }
        }
    };
    stockForm.addEventListener('stock-open', loadStock);
    stockForm.elements.namedItem('producto_id').addEventListener('change', loadStock);
    stockForm.elements.namedItem('sucursal_id').addEventListener('change', loadStock);
    stockForm.addEventListener('reset', () => { generation++; stockForm.querySelector('[type="submit"]').disabled = true; });
}

document.querySelectorAll('.filters-panel').forEach((form) => {
    const from = form.elements.namedItem('desde');
    const to = form.elements.namedItem('hasta');
    if (!from || !to) { return; }
    const dates = () => {
        to.min = from.value;
        to.setCustomValidity(from.value && to.value && from.value > to.value
            ? 'La fecha final debe ser igual o posterior a la inicial.' : '');
    };
    from.addEventListener('input', dates);
    to.addEventListener('input', dates);
    dates();
});
