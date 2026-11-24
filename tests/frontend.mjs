import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../frontend/public/assets/js/app.js', import.meta.url), 'utf8');
const context = vm.createContext({
    document: { querySelector: () => null, querySelectorAll: () => [], addEventListener: () => {} },
    Intl, URL, AbortSignal, TextEncoder, window: {},
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

const validations = [
    {type:'number',name:'precio',value:'1e3',invalid:true},
    {type:'number',name:'precio',value:'12.50',invalid:false},
    {type:'text',required:true,value:'   ',invalid:true},
    {type:'tel',value:'abc',invalid:true},
    {type:'tel',value:'123456',invalid:true},
    {type:'tel',value:'+52 (55) 1234-5678',invalid:false},
    {type:'tel',value:'',invalid:false},
    {type:'password',autocomplete:'new-password',value:'admin',invalid:false},
    {type:'password',autocomplete:'new-password',value:'    ',invalid:true},
    {type:'password',autocomplete:'new-password',value:'á'.repeat(37),invalid:true},
    {type:'text',value:'Control\u0001',invalid:true}
];
for (const sample of validations) {
    context.field = {...sample, tagName:'INPUT', setCustomValidity(value) { this.error=value; }};
    vm.runInContext('validarCampo(field)', context);
    assert.equal(Boolean(context.field.error),sample.invalid);
}
console.log('OK: espacios, teléfono, caracteres de control y contraseña simple.');

class NodeCart {
    children = [];
    listeners = {};
    value = '';
    dataset = {};
    setAttribute() {}
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren() { this.children = []; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    setCustomValidity(text) { this.error = text; }
}
const parts = Object.fromEntries(['items','subtotal','total','payload','message'].map(key=>[key,new NodeCart()]));
const sale = {querySelector(selector) {
    return parts[{'[data-cart-items]':'items','[data-cart-subtotal]':'subtotal',
        '[data-cart-total]':'total','[data-cart-payload]':'payload','.form-message':'message'}[selector]];
}};
const cartContext = vm.createContext({
    document: {querySelector:s=>s==='#form-venta'?sale:null,querySelectorAll:()=>[],addEventListener(){},
        createElement:()=>new NodeCart()}, Intl, URL, AbortSignal, TextEncoder, window:{}, FormData:class {}
});
vm.runInContext(source,cartContext);
vm.runInContext('cart.set("1", {name:"Conector",price:15050,quantity:1,stock:5}); renderCart()',cartContext);
const quantity = parts.items.children[0].children[1];
quantity.value='2';quantity.listeners.input();
assert.equal(JSON.parse(parts.payload.value)[0].cantidad,2);
assert.match(parts.total.textContent,/301[.,]00/);
quantity.value='';quantity.listeners.input();
assert.equal(quantity.required,true);
assert.ok(quantity.error);
quantity.value='6';quantity.listeners.input();
assert.ok(quantity.error);
quantity.value='3';quantity.listeners.input();
assert.equal(quantity.error,'');
assert.equal(JSON.parse(parts.payload.value)[0].cantidad,3);
assert.match(parts.total.textContent,/451[.,]50/);
console.log('OK: escribir cantidades actualiza el total y el envío; valores vacíos o sin stock quedan inválidos.');
