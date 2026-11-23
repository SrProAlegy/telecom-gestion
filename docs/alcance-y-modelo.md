# Alcance y modelo de datos

La especificación de referencia es «Telcom Gestion - Fase0 - Fase1.pdf»: diagrama entidad–relación en la página 3 y requisitos en las páginas 8–9. El entorno local utiliza Docker Compose con Apache/PHP y MySQL.

## Funciones disponibles

- Página de estado con comprobación de conexión y esquema.
- Endpoint `/health`: HTTP 200 con conexión disponible y HTTP 503 cuando falla la comprobación.
- Conexión PDO con consultas preparadas, codificación utf8mb4 y errores públicos sin detalles internos.
- Inicialización del esquema y del catálogo de estados de reparación.
- Consulta visual de la base mediante phpMyAdmin.
- Pruebas de integridad SQL, respuestas HTTP, disponibilidad y restauración de respaldo.

La aplicación no incluye inicio de sesión, permisos por rol, formularios de captura, operaciones de reparación, movimientos de inventario, registro de ventas, reportes ni exportación. La existencia de sus tablas no equivale a la disponibilidad de esos módulos.

## Entidades y relaciones

El esquema conserva las 11 entidades del ER: sucursales, usuarios, clientes, equipos, tecnicos, reparaciones, categorias, productos, inventario, ventas y detalle_venta. Incluye tres tablas complementarias: estados_reparacion, historial_reparaciones y schema_version.

La relación Cliente → Equipo → Reparación admite varias reparaciones del mismo dispositivo. El cliente de una venta es opcional; una reparación admite un técnico sin asignar. Técnicos y usuarios son entidades independientes.

## Campos y restricciones

- `usuarios.password_hash` reserva el almacenamiento del hash de contraseña. No hay cuentas de aplicación precargadas ni un mecanismo de autenticación activo.
- `usuarios.rol` admite administrador, recepcion, tecnico y ventas. La restricción SQL limita los valores; no aplica permisos de acceso.
- `reparaciones` conserva observaciones, fecha de recepción, fecha de entrega y fecha de actualización. La sesión PDO utiliza UTC; la zona horaria configurada en PHP es America/Mexico_City.
- `estados_reparacion` contiene recibido, enviado a técnico, diagnóstico, en reparación, listo para entrega, entregado, cancelado y no reparable. El catálogo restringe los valores de estado, sin controlar sus transiciones.
- `historial_reparaciones` almacena estado, diagnóstico, observaciones, técnico, usuario, sucursal y fecha de cada evento. No hay triggers ni operaciones de aplicación que creen eventos al modificar una reparación.
- `schema_version` identifica la versión del esquema inicializado.
- Sucursales y técnicos tienen un indicador de activo. Las restricciones impiden importes o existencias negativos, cantidades de venta menores que uno y duplicados de inventario por producto/sucursal.
- El IMEI es opcional y admite 15 dígitos. No tiene una restricción de unicidad.
- Las claves foráneas compuestas usuario–sucursal en ventas, reparaciones e historial exigen que la sucursal del registro coincida con la del usuario. Esta regla también impide cambiar la sucursal de un usuario con operaciones asociadas. La sucursal de recepción permanece en la reparación; los eventos del historial conservan la sucursal de su operador.
- `detalle_venta.subtotal` se calcula como cantidad × precio unitario. El esquema no verifica que `ventas.total` coincida con la suma de sus detalles y no descuenta inventario al insertar una venta.
- Las operaciones no tienen borrado en cascada; las referencias históricas están protegidas por claves foráneas.

## Correspondencia con los requisitos

| Requisito | Soporte existente | Funciones no disponibles |
|---|---|---|
| RF01–02 | Tabla de usuarios, hash y rol | Inicio/cierre de sesión, sesiones y permisos |
| RF03 | Sucursales y relaciones | Administración de sucursales |
| RF04–05 | Clientes y equipos | Formularios, validación y consultas |
| RF06–09 | Reparaciones, técnico opcional y estados | Recepción, asignación, diagnóstico y actualización |
| RF10 | Índice por sucursal, estado y fecha | Consulta y filtros de reparaciones |
| RF11–12 | Categorías, productos e inventario por sede | Administración del catálogo y movimientos |
| RF13–14 | Venta, detalle e importes | Registro transaccional de ventas y descuento de existencias |
| RF15 | Referencias a sucursal y consistencia del operador | Restricción de acceso por sucursal |
| RF16 | Tablas e índices consultables | Reportes |
| RF17 | Fechas y tabla de historial | Registro de eventos y consulta del seguimiento |

Los requisitos funcionales disponen de soporte en el esquema, pero no de procesos de aplicación completos.

| Requisito no funcional | Cobertura actual |
|---|---|
| RNF01 | Página de estado adaptable con Bootstrap |
| RNF02–03 | Sin autenticación ni autorización de aplicación |
| RNF04 | Base MySQL centralizada en el entorno local |
| RNF05 | Página de estado accesible mediante navegador |
| RNF06 | Mensajes genéricos ante fallos de conexión; sin formularios de captura |
| RNF07 | Comandos de respaldo y restauración documentados; prueba de recuperación en base temporal |
| RNF08 | Separación de vistas, controladores, modelos y conexión PDO |
| RNF09 | Repositorio Git, exclusiones y flujo de validación configurados; sin ejecución remota registrada |

## Límites del alcance

No se incluye exportación a Excel ni módulos específicos de impresiones o venta de dulces. No hay un servicio transaccional de ventas, registro de movimientos de inventario, control de concurrencia comercial ni historial automático de reparaciones.
