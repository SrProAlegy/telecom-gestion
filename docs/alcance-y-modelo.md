# Alcance y modelo de datos

El esquema conserva las 11 entidades del ER: sucursales, usuarios, clientes, equipos, tecnicos, reparaciones, categorias, productos, inventario, ventas y detalle_venta. Las tablas complementarias son estados_reparacion, historial_reparaciones y schema_version. La implementación del backend no modifica el esquema, sus campos ni los estados iniciales.

## Funciones

| Requisito | Implementación |
|---|---|
| RF01–02 | Acceso y salida, hash de contraseña, sesión, cuentas activas y permisos por rol |
| RF03 | Alta, edición y desactivación de sucursales |
| RF04–05 | Alta, edición y consulta de clientes y equipos; IMEI opcional de 15 dígitos |
| RF06–09 | Recepción, asignación técnica, diagnóstico, seguimiento y entrega |
| RF10 | Consultas por sucursal, estado, búsqueda y páginas |
| RF11–12 | Categorías, productos y ajuste de existencias independientes por sucursal |
| RF13–14 | Venta y detalles con precios del servidor, transacción y descuento de existencias |
| RF15 | Autorización de sucursal en lecturas y escrituras |
| RF16 | Reportes de ventas/reparaciones, stock bajo actual, impresión y CSV |
| RF17 | Registro transaccional y consulta de historial de reparaciones |

La correspondencia describe funciones implementadas; no sustituye una aceptación funcional con el negocio. La matriz de permisos está en el README.

## Relaciones y reglas

- Cliente → Equipo → Reparación admite varias reparaciones del mismo dispositivo. Un equipo con reparaciones conserva el cliente para no alterar su historial.
- Clientes, equipos, técnicos, categorías y productos son catálogos compartidos. El inventario y las operaciones tienen sucursal.
- Técnicos y cuentas con rol técnico son entidades independientes. El rol técnico puede trabajar con reparaciones de todas las sucursales; no se infiere una relación usuario–técnico que el esquema no tiene.
- Las claves usuario–sucursal exigen que ventas, recepción e historial se registren con la sucursal del operador. Un administrador puede consultar todas las sucursales y registrar seguimiento de otras sedes; el historial conserva la sede del operador.
- Los usuarios con ventas, reparaciones o historial conservan su sucursal. Las claves foráneas protegen esas referencias.
- El administrador no puede desactivar su propia cuenta ni quitarse el rol. Debe conservarse al menos un administrador activo en una sucursal activa.
- Desactivar una cuenta o sucursal impide el acceso. Cambiar la contraseña invalida sus sesiones anteriores.
- No hay borrado físico desde la aplicación. Usuarios, sucursales, técnicos y productos pueden desactivarse; los demás registros conservan sus relaciones.

## Reparaciones

| Estado actual | Cambios admitidos |
|---|---|
| recibido | enviado_tecnico, diagnostico, cancelado |
| enviado_tecnico | diagnostico, cancelado |
| diagnostico | en_reparacion, no_reparable, cancelado |
| en_reparacion | diagnostico, listo_entrega, no_reparable, cancelado |
| listo_entrega | entregado, en_reparacion, cancelado |
| entregado, cancelado, no_reparable | Estados cerrados |

Mientras una reparación esté abierta, admite actualización de diagnóstico y observaciones manteniendo el estado. El avance técnico exige técnico asignado. Reparación, disponibilidad para entrega, entrega y cierre no reparable exigen diagnóstico. Entrega y cancelación corresponden a recepción o administración. La entrega registra la fecha UTC del servidor. Cada recepción o seguimiento crea un evento de historial dentro de la misma transacción. La revisión enviada por el formulario debe coincidir con el último evento para evitar sobrescrituras.

## Ventas e inventario

Una venta admite cliente opcional, método efectivo/tarjeta/transferencia y entre una y cien partidas, sin productos repetidos. Los productos deben estar activos y tener existencias suficientes en la sucursal del operador. El total se calcula en centavos enteros usando los precios bloqueados del catálogo y debe caber en DECIMAL(12,2). `detalle_venta.subtotal` mantiene su cálculo generado en MySQL. Venta, detalles y decremento de inventario se confirman juntos; ante conflicto se revierten todos.

El ajuste de existencias establece la cantidad absoluta de producto y sucursal, con bloqueo y comparación de la cantidad consultada. No representa entradas/salidas contables: el esquema no incluye un libro de movimientos ni auditoría de ajustes. No se añade uno de forma implícita.

## Alcance operativo

El entorno es local con Apache/PHP, MySQL y phpMyAdmin. Incluye validación del servidor, mensajes públicos genéricos, protección CSRF, cookies HttpOnly/SameSite y bloqueo ante intentos de acceso repetidos. Las sesiones se almacenan en el contenedor web. No incluye recuperación de contraseña por correo, pagos en línea, contabilidad, facturación fiscal, XLSX nativo ni módulos específicos de impresiones/dulces. Los reportes CSV pueden abrirse en un gestor de hojas de cálculo.

Las consultas utilizan UTC y presentan fechas en America/Mexico_City. Los resúmenes financieros corresponden al periodo seleccionado; el inventario es actual. Los catálogos iniciales no crean cuentas ni operaciones. La configuración de instalación pública, TLS remoto y auditoría de producción quedan fuera del entorno de desarrollo.

## Atención técnica entre sucursales

El área técnica atiende reparaciones recibidas en cualquier sucursal sin cambiar su origen. La recepción y entrega pertenecen a la sede receptora; cada evento registra también la sede de su operador. Este comportamiento utiliza las relaciones ya existentes de reparaciones e historial, sin tablas ni campos adicionales. Las consultas y exportaciones del técnico comparten ese ámbito para reparaciones; sus permisos no incluyen ventas, existencias ni reportes financieros.
