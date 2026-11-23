# Integración del frontend

## Estado y alcance

Las vistas consultan MySQL y presentan formularios de captura y edición con la identidad visual de Telcom. Las interacciones de navegador incluyen apertura y cierre de formularios, validación nativa, selección de equipos por cliente, visualización de contraseña, carrito, control de cantidades, cálculo de importes y preparación de solicitudes JSON.

El backend actual conserva sus rutas GET y HEAD. Las rutas de escritura de este documento son el contrato de integración del frontend; no tienen controladores de persistencia ni autenticación. Actualmente responden 404. La interfaz conserva los datos capturados y presenta un error operativo cuando el servidor no confirma la operación. No utiliza localStorage, cuentas de ejemplo ni respuestas de éxito simuladas.

## Solicitudes y respuestas

Los formularios envían JSON mediante fetch con credenciales same-origin y Accept: application/json. La creación utiliza POST; la edición utiliza PATCH y añade el identificador a la ruta. Los campos opcionales vacíos se envían como null; una contraseña vacía al editar se omite. El identificador oculto de edición se utiliza en la URL y no se incluye en el JSON.

| Recurso | Ruta | Campos |
|---|---|---|
| Clientes | `/api/clientes` | nombre, telefono, correo |
| Equipos | `/api/equipos` | cliente_id, marca, modelo, imei, color, estado_fisico |
| Reparación | `/api/reparaciones` | equipo_id, sucursal_id, tecnico_id, problema_reportado |
| Seguimiento | `/api/reparaciones/{id}` con PATCH | tecnico_id, estado, diagnostico, observaciones |
| Productos | `/api/productos` | nombre, categoria_id, descripcion, precio, stock_minimo, activo |
| Categorías | `/api/categorias` | nombre, descripcion |
| Existencias | `/api/inventario` con POST | producto_id, sucursal_id, cantidad |
| Usuarios | `/api/usuarios` | nombre, apellido, correo, password, rol, sucursal_id, activo |
| Sucursales | `/api/sucursales` | nombre, direccion, telefono, activo |
| Técnicos | `/api/tecnicos` | nombre, apellido, telefono, especialidad, activo |
| Ventas | `/api/ventas` con POST | sucursal_id, cliente_id, metodo_pago, detalles |
| Acceso | `/api/sesion` con POST | correo, password |
| Cierre de sesión | `/api/sesion` con DELETE | Objeto vacío |

La respuesta de éxito es JSON con `ok: true`. Puede incluir `redirect`, limitado al mismo origen. Para acceso, el destino predeterminado es `/`. En las demás operaciones, una confirmación real del servidor recarga los registros.

```json
{"ok": true, "mensaje": "Registro guardado"}
```

Los errores utilizan un código HTTP adecuado, `ok: false`, un mensaje comprensible y, cuando corresponde, un objeto `errores` cuyas claves son nombres de campo. Los mensajes se insertan como texto, sin interpretar HTML.

```json
{"ok": false, "mensaje": "Revisa los datos", "errores": {"correo": "Indica un correo válido"}}
```

## Correspondencia con MySQL

Los campos conservan los nombres del esquema. `password` es una entrada de operación: su representación persistida es `usuarios.password_hash`. No es una columna adicional. La selección de cliente en recepción es un filtro visual sin atributo name; solo se envía equipo_id. La existencia se guarda en inventario, separada del producto.

Los roles de usuario son administrador, recepcion, tecnico y ventas. Cada cuenta selecciona una sucursal concreta. Los técnicos proceden de tecnicos y no se confunden con las cuentas de usuarios. Las opciones de estados, clientes, equipos, categorías, sucursales, técnicos y productos proceden de consultas reales.

Los controles reflejan las longitudes VARCHAR y los tipos numéricos del esquema. Los textos extensos tienen un límite de interfaz de 10 000 caracteres, inferior a la capacidad de TEXT. El IMEI opcional admite exactamente 15 dígitos y se conserva como texto. Los importes se presentan con dos decimales; precio se transmite como cadena decimal. Los identificadores, cantidades e indicadores se envían como números enteros.

## Carrito

La selección usa id_producto, precio y existencias del catálogo consultado para una sucursal concreta. La interfaz impide superar la existencia mostrada y calcula en centavos enteros, con control del rango numérico seguro. Los totales del carrito son una vista previa de la selección, independientes de las ventas ya registradas.

`detalles` contiene únicamente pares producto_id/cantidad. Los precios e importes de la venta no se aceptan como autoridad desde el cliente. La fecha de la operación, el usuario responsable, la autorización de sucursal y el cálculo y confirmación transaccional corresponden al servidor. El método de pago ofrece efectivo, tarjeta y transferencia como opciones de interfaz sobre ventas.metodo_pago.

## Contexto de sesión y seguridad

La plantilla acepta `$usuarioActual` con nombre y sucursal_nombre para el encabezado. En ausencia de ese contexto muestra Iniciar sesión; no presenta una persona conectada ficticia. La variable `$csrfToken` se publica en una etiqueta meta y, cuando contiene un valor, el adaptador envía X-CSRF-Token.

La integración del servidor requiere validación de sesión, permisos, sucursal y CSRF antes de aceptar escrituras; validación de los campos; hash y comprobación de contraseñas; consultas preparadas y transacciones cuando intervienen varios registros. La validación del navegador no sustituye esas comprobaciones. El esquema existente no se modifica para acomodar los formularios.

## Navegación

Los accesos principales mantienen las rutas PHP existentes. Los formularios se abren mediante diálogos nativos con foco controlado por el navegador y cierre con Cancelar o Escape. Los filtros usan GET. El menú lateral es desplegable en pantallas estrechas. El reporte utiliza la impresión del navegador con estilos que ocultan navegación y controles.
