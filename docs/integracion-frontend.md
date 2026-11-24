# Integración del frontend

## Estado y alcance

Las vistas consultan MySQL y presentan formularios de captura y edición con la identidad visual de Telcom. Las interacciones de navegador incluyen apertura y cierre de formularios, validación nativa, selección de equipos por cliente, visualización de contraseña, carrito, control de cantidades, cálculo de importes y preparación de solicitudes JSON.

Las vistas requieren sesión y aplican permisos por rol y sucursal. Los endpoints de escritura de este documento están implementados en ApiController y Operaciones, con validación del servidor, CSRF y persistencia mediante PDO. La interfaz conserva los datos capturados y presenta un error operativo cuando el servidor no confirma la operación. No utiliza localStorage, cuentas de ejemplo ni respuestas de éxito simuladas.

## Solicitudes y respuestas

Los formularios envían JSON mediante fetch con credenciales same-origin y Accept: application/json. La creación utiliza POST; la edición utiliza PATCH y añade el identificador a la ruta. Los campos opcionales vacíos se envían como null; una contraseña vacía al editar se omite. El identificador oculto de edición se utiliza en la URL y no se incluye en el JSON.

| Recurso | Ruta | Campos |
|---|---|---|
| Clientes | `/api/clientes` | nombre, telefono, correo |
| Equipos | `/api/equipos` | cliente_id, marca, modelo, imei, color, estado_fisico |
| Reparación | `/api/reparaciones` | equipo_id, sucursal_id, tecnico_id, problema_reportado |
| Seguimiento | `/api/reparaciones/{id}` con PATCH | tecnico_id, estado, diagnostico, observaciones, revision |
| Productos | `/api/productos` | nombre, categoria_id, descripcion, precio, stock_minimo, activo |
| Categorías | `/api/categorias` | nombre, descripcion |
| Existencias | `/api/inventario` con POST | producto_id, sucursal_id, cantidad, cantidad_anterior |
| Usuarios | `/api/usuarios` | nombre, apellido, correo, password, rol, sucursal_id, activo |
| Sucursales | `/api/sucursales` | nombre, direccion, telefono, activo |
| Técnicos | `/api/tecnicos` | nombre, apellido, telefono, especialidad, activo |
| Ventas | `/api/ventas` con POST | sucursal_id, cliente_id, metodo_pago, detalles, operacion |
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

El servidor valida sesión, permisos, sucursal y CSRF antes de aceptar escrituras; realiza validación de los campos; hash y comprobación de contraseñas; consultas preparadas y transacciones cuando intervienen varios registros. La validación del navegador no sustituye esas comprobaciones. El esquema existente no se modifica para acomodar los formularios.

## Navegación

Los accesos principales mantienen las rutas PHP existentes. Los formularios se abren mediante diálogos nativos con foco controlado por el navegador y cierre con Cancelar o Escape. Los filtros usan GET. El menú lateral es desplegable en pantallas estrechas. El reporte utiliza la impresión del navegador con estilos que ocultan navegación y controles.

## Consultas adicionales y concurrencia

`GET /api/reparaciones/{id}/historial` devuelve los eventos ordenados por fecha e identificador. `GET /api/ventas/{id}` devuelve sus partidas con precios y subtotales. Ambas consultas exigen permisos de módulo y sucursal. `GET /api/inventario?producto_id=...&sucursal_id=...`, reservado al administrador, devuelve la cantidad actual para el formulario de existencias. `GET /reportes/exportar.csv` aplica los mismos permisos y filtros que la vista de reportes.

El seguimiento envía `revision`, el último id_historial mostrado. El ajuste de existencias envía `cantidad_anterior`, consultada al elegir el producto y la sucursal. Son metadatos de la solicitud, sin columnas nuevas. Si cambió el registro, la API devuelve 409 y no sobrescribe el trabajo confirmado por otra operación.

Una creación confirmada devuelve HTTP 201 y una edición 200. Otros resultados: 400 JSON inválido, 401 sesión o credenciales inválidas, 403 sin permiso, 404 recurso inexistente, 405 método no admitido, 409 conflicto, 413 solicitud excesiva, 415 formato distinto de JSON, 419 CSRF inválido, 422 datos inválidos y 429 límite de acceso. El cliente conserva el formulario ante error. Un fallo de conexión devuelve un mensaje genérico sin SQL ni credenciales.

Las ventas no aceptan edición ni borrado. El usuario se obtiene de la sesión, el precio del catálogo bloqueado y la fecha del servidor. El carrito representa una selección todavía sin guardar; solo se vacía por recarga tras la confirmación del servidor o por acción explícita del usuario. Un error de red posterior al envío no acredita el resultado: consulta las ventas antes de repetir una solicitud cuyo resultado no se recibió.

## Validación de captura y reenvíos

Los nombres obligatorios rechazan entradas compuestas solo por espacios y caracteres de control. Los teléfonos opcionales aceptan entre siete y quince dígitos y formato internacional con + inicial, espacios, paréntesis, puntos y guiones. Para registrar un equipo debe existir al menos marca, modelo o IMEI. Las contraseñas de alta y edición admiten de 4 a 72 bytes y rechazan valores compuestos solo por espacios; el acceso conserva la contraseña simple de desarrollo configurada por el usuario.

El seguimiento muestra únicamente el estado actual y sus transiciones permitidas. Técnico y diagnóstico se vuelven obligatorios según el estado. Las asignaciones nuevas excluyen productos, técnicos y sucursales inactivos; una referencia histórica inactiva puede conservarse al editar. La desactivación de una cuenta puede realizarse aunque su sucursal esté inactiva.

La venta envía `operacion`, una cadena aleatoria de 32 caracteres hexadecimales generada por el servidor al abrir la página. Una solicitud confirmada conserva su identificador y la huella de su contenido en la sesión. Reenviar la misma clave y contenido devuelve HTTP 200 con el mismo id; reutilizarla con contenido diferente devuelve 409. Los errores de validación no consumen la clave. Esta protección corresponde a la misma sesión; no constituye un registro de idempotencia persistente ante recreación del contenedor o caída del proceso entre el commit SQL y la escritura de la sesión.

La captura del carrito actualiza cantidades, subtotales, total y JSON al escribir, antes de perder el foco. Una cantidad vacía, fraccionaria, fuera de stock o cuyo importe exceda DECIMAL(12,2) impide el envío. Los controles se bloquean mientras se procesa la solicitud y el diálogo no admite cierre durante ese periodo. El token `operacion` no añade columnas al esquema.

En ventas, la ausencia del filtro de sucursal selecciona la de la cuenta. Elegir explícitamente todas las sucursales permite la consulta agregada; la captura queda deshabilitada hasta seleccionar la sucursal del operador. Los filtros de fechas también comprueban su orden en el navegador.

## Área técnica central

Una cuenta técnica consulta y actualiza reparaciones de todas las sucursales. Los filtros `sucursal`, `estado`, `tecnico` y `q` se combinan; `tecnico=sin_asignar` selecciona la cola sin responsable. El técnico no registra recepciones ni entrega/cancela reparaciones. Recepción conserva su ámbito de sucursal. Los eventos de historial incluyen `sucursal_receptora` y `sucursal` (sede del operador); una actualización técnica conserva el receptor, equipo y sucursal originales. El panel técnico excluye indicadores y actividad financiera.

## Descargas XLSX

Las rutas GET `/clientes-equipos/exportar.xlsx`, `/reparaciones/exportar.xlsx`, `/inventario/exportar.xlsx`, `/ventas/exportar.xlsx` y `/reportes/exportar.xlsx` usan los permisos y validación de filtros de sus pantallas. `pagina` no limita las filas exportadas. Ventas sin `sucursal` selecciona la propia; `sucursal=` permite al administrador una consulta global explícita. Un filtro no autorizado o inválido devuelve un error antes de enviar cabeceras de descarga. Las respuestas correctas usan el tipo MIME de XLSX y un nombre fijo por módulo. HEAD no envía el cuerpo del archivo.

El libro se completa antes de enviarlo; los registros se recorren con un cursor sin límite de paginación dentro de una transacción de solo lectura. Los textos se escriben como texto, incluso si empiezan con signos de fórmula. No se exportan contraseñas, sesiones ni hashes. El inventario es actual, sin corte histórico.
