# Recorrido funcional

Revisión del 23 de noviembre de 2026 sobre el entorno local. El acceso y las siete secciones se recorrieron con la cuenta administradora configurada. Las operaciones de captura utilizaron exclusivamente telcom_pruebas y un servicio temporal en el puerto 8082.

## Hallazgos corregidos

| Hallazgo | Corrección y comprobación |
|---|---|
| La cantidad escrita en el carrito podía enviarse con el valor anterior | Actualización con eventos de escritura y cambio; se verificaron 2 unidades × 150.50 = 301.00, detalle de 2 y descuento exacto |
| Cantidades vacías o inválidas podían conservar la selección anterior | Cantidad obligatoria, validación de enteros/stock y conservación del error hasta corregirla |
| Los reenvíos de ventas podían registrar la misma solicitud otra vez | Clave de operación y respuesta confirmada en sesión; el reenvío devuelve el mismo id y no descuenta más stock |
| Nombres compuestos solo por espacios y teléfonos con letras pasaban la validación del navegador | Validación en navegador y servidor; teléfono opcional de 7 a 15 dígitos con formato internacional |
| Se podían registrar equipos sin información identificable | Se exige marca, modelo o IMEI, manteniendo el IMEI opcional y sus 15 dígitos |
| El seguimiento ofrecía estados que el servidor rechazaba | Solo estado actual y transiciones permitidas; técnico/diagnóstico obligatorios según estado y rol |
| Los selectores ofrecían asignaciones inactivas sin identificarlas | Opciones inactivas rotuladas y deshabilitadas para nuevas asignaciones; conservación de referencias históricas |
| Elegir todas las sucursales en ventas regresaba silenciosamente a la propia | Consulta global explícita, con captura deshabilitada fuera de la sucursal del operador |
| Se podía editar o cerrar el formulario mientras se guardaba | Bloqueo temporal de controles y cierre; recuperación ante error |
| El periodo invertido se detectaba solo después del envío | Validación inmediata de fechas en el navegador, conservando la del servidor |
| Una cuenta en sucursal inactiva no podía desactivarse | Se permite desactivar la cuenta; activarla continúa exigiendo sucursal activa |
| El menú móvil cerrado permanecía en el recorrido de teclado | Menú inerte al cerrarse, foco al abrir y cierre con Escape |
| La descarga CSV quedaba separada de los controles ocultos al imprimir | Acciones agrupadas y excluidas por los estilos de impresión |

## Recorrido comprobado

- Acceso con la contraseña simple configurada y cierre de sesión.
- Panel, clientes/equipos, reparaciones, inventario, ventas, reportes y administración.
- Alta de cliente con teléfono internacional, equipo y correspondencia del cliente al recibir una reparación.
- Recepción → diagnóstico → reparación → listo para entrega → entregado. Se observaron cinco eventos de historial y desaparición de la edición al cerrar.
- Alta de categoría, producto y ajuste de existencias; rechazo de cantidad negativa.
- Venta desde el carrito, detalle, importes y stock tras confirmar.
- CSV descargado y verificado; periodo invertido rechazado.
- Navegación a tamaño móvil y restablecimiento del tamaño habitual.
- Suite aislada: 341 comprobaciones de backend. JavaScript: importes, serialización, validaciones y regresión de cantidades por escritura.

## Conservación de datos

La base habitual conserva una sucursal, un usuario y ocho estados iniciales. No se insertaron clientes, equipos, técnicos, categorías, productos, reparaciones, existencias ni ventas allí. Los archivos SQL mantienen sus hashes previos. La cuenta y la contraseña configuradas no se modificaron.

## Límites observados

La aplicación registra la forma de pago; no ejecuta cobros. No dispone de anulaciones/devoluciones, recuperación de contraseña por correo ni libro de movimientos de inventario. El esquema existente no contiene esas operaciones y no se añadieron tablas ni campos para simularlas. El ajuste de existencias es absoluto, con comprobación del valor consultado.

La protección de reenvíos de ventas pertenece a la sesión del contenedor web; no ofrece confirmación durable ante caída del proceso o recreación del contenedor entre la confirmación SQL y la escritura de la sesión. Ante un resultado de red incierto se deben consultar las ventas antes de iniciar otra solicitud.

La impresión dispone de estilos y control de navegador; no se envió un trabajo a una impresora física. Las pruebas no equivalen a auditoría de producción, prueba de carga ni aceptación comercial de los requisitos.
