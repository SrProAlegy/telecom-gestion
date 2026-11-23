'use strict';

const toggle = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('.sidebar');
const backdrop = document.querySelector('.nav-backdrop');

function closeMenu() {
    sidebar?.classList.remove('is-open');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'Abrir menú');
    if (backdrop) { backdrop.hidden = true; }
}
toggle?.addEventListener('click', () => {
    const open = sidebar.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    backdrop.hidden = !open;
});
backdrop?.addEventListener('click', closeMenu);

function message(form, text) {
    const box = form.querySelector('.form-message');
    if (!box) { return; }
    box.textContent = text;
    box.hidden = !text;
}

document.querySelectorAll('[data-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.open);
        const form = dialog?.querySelector('form');
        if (!dialog || !form) { return; }
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
        dialog.showModal();
    });
});

document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog').close());
});
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
        if (!Number.isSafeInteger(total)) { throw new Error('El importe de la venta supera el límite permitido.'); }
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
        quantity.max = String(item.stock);
        quantity.step = '1';
        quantity.value = String(item.quantity);
        quantity.setAttribute('aria-label', `Cantidad de ${item.name}`);
        quantity.addEventListener('change', () => {
            const value = Number(quantity.value);
            if (!Number.isInteger(value) || value < 1 || value > item.stock) {
                quantity.setCustomValidity('Indica una cantidad dentro de las existencias disponibles.');
                quantity.reportValidity();
                return;
            }
            const previous = item.quantity;
            item.quantity = value;
            try { cartTotal(); } catch (error) {
                item.quantity = previous;
                message(saleForm, error.message);
            }
            renderCart();
        });
        quantity.addEventListener('input', () => quantity.setCustomValidity(''));
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
        else if (key.endsWith('_id') || ['cantidad', 'stock_minimo', 'activo'].includes(key)) {
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
        event.target.setCustomValidity?.('');
        event.target.removeAttribute('aria-invalid');
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) { return; }
        if (form === saleForm && (!cart.size || !form.elements.namedItem('sucursal_id').value)) {
            message(form, 'Selecciona una sucursal y agrega al menos un producto.');
            return;
        }
        const submit = form.querySelector('[type="submit"]');
        if (submit.disabled) { return; }
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        message(form, '');
        try {
            const { response, body } = await request(form.action, form.dataset.method || 'POST', payload(form));
            if (!response.ok || body.ok !== true) {
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
