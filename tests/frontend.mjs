import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../frontend/public/assets/js/app.js', import.meta.url), 'utf8');
const context = vm.createContext({
    document: { querySelector: () => null, querySelectorAll: () => [], addEventListener: () => {} },
    Intl, URL, AbortSignal, window: {},
    FormData: class { constructor(form) { this.entries = form.entries; } [Symbol.iterator]() { return this.entries[Symbol.iterator](); } }
});
vm.runInContext(source, context);
assert.equal(vm.runInContext('cents("0.29")', context), 29);
assert.equal(vm.runInContext('cents("180.5")', context), 18050);
assert.equal(vm.runInContext('cents("0")', context), 0);
assert.throws(() => vm.runInContext('cents("1.001")', context));
assert.throws(() => vm.runInContext('cents("-1")', context));
vm.runInContext('cart.set("1", {price:29, quantity:3}); cart.set("2", {price:1250, quantity:2})', context);
assert.equal(vm.runInContext('cartTotal()', context), 2587);
vm.runInContext('cart.set("3", {price:999999999999, quantity:2147483647})', context);
assert.throws(() => vm.runInContext('cartTotal()', context));
const value = vm.runInContext('payload({entries:[["id","8"],["cliente_id",""],["sucursal_id","3"],["precio","12.50"],["detalles","[{\\"producto_id\\":2,\\"cantidad\\":3}]"],["password",""], ["imei","012345678901234"]], dataset:{method:"PATCH"}})', context);
assert.equal(value.cliente_id, null);
assert.equal(value.sucursal_id, 3);
assert.equal(value.precio, '12.50');
assert.equal(value.detalles[0].producto_id, 2);
assert.equal(value.detalles[0].cantidad, 3);
assert.equal(value.imei, '012345678901234');
assert.equal('password' in value, false);
assert.equal('id' in value, false);
console.log('OK: centavos, precisión, límites numéricos, estructura JSON, valores opcionales y edición de contraseña.');
