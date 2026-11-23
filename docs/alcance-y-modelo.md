# Alcance y modelo de datos

La especificación de referencia es «Telcom Gestion - Fase0 - Fase1.pdf»: diagrama entidad–relación en la página 3 y requisitos en las páginas 8–9. El entorno local utiliza Docker Compose con Apache/PHP y MySQL.

## Funciones disponibles

- Panel con indicadores obtenidos de MySQL, consulta de reparaciones recientes y actividad registrada.
- Vistas de consulta de clientes/equipos, reparaciones, inventario, ventas, usuarios y sucursales.
- Filtros por sucursal, estado, texto y fechas según cada pantalla.
- Resúmenes de ventas y reparaciones por sucursal y estado, y consulta de stock bajo actual.
- Endpoint `/health`: HTTP 200 con conexión disponible y HTTP 503 cuando falla la comprobación.
- Conexión PDO con consultas preparadas, codificación utf8mb4 y errores públicos sin detalles internos.
- Inicialización del esquema y del catálogo de estados de reparación.
- Consulta visual de la base mediante phpMyAdmin.
- Pruebas de integridad SQL, respuestas HTTP, disponibilidad y restauración de respaldo.

La interfaz contiene formularios de acceso, alta y edición, carrito e impresión de reportes. El backend no procesa autenticación, permisos ni operaciones de escritura. Las consultas y resúmenes funcionan sobre los registros existentes; las rutas de envío se documentan en integracion-frontend.md. La existencia de sus tablas no equivale a la disponibilidad de esos módulos.

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
| RF03 | Consulta de sucursales y relaciones | Alta y edición de sucursales |
| RF04–05 | Consulta y búsqueda de clientes y equipos | Formularios y validación de captura |
| RF06–09 | Reparaciones, técnico opcional y estados | Recepción, asignación, diagnóstico y actualización |
| RF10 | Consulta de reparaciones con filtros por sucursal, estado y búsqueda | Permisos de consulta por usuario |
| RF11–12 | Consulta de productos, categorías y existencias por sede | Administración del catálogo y movimientos |
| RF13–14 | Venta, detalle e importes | Registro transaccional de ventas y descuento de existencias |
| RF15 | Referencias a sucursal y consistencia del operador | Restricción de acceso por sucursal |
| RF16 | Resúmenes consultables de ventas, reparaciones e inventario | Permisos y exportación |
| RF17 | Fechas y tabla de historial | Registro de eventos y consulta del seguimiento |

Las consultas tienen soporte funcional y los formularios están preparados en el frontend. Los controladores de escritura y acceso no están implementados.

| Requisito no funcional | Cobertura actual |
|---|---|
| RNF01 | Vistas adaptables con Bootstrap y menú desplegable en tablet |
| RNF02–03 | Sin autenticación ni autorización de aplicación |
| RNF04 | Base MySQL centralizada en el entorno local |
| RNF05 | Vistas de consulta accesibles mediante navegador |
| RNF06 | Validación de filtros y mensajes genéricos ante fallos de conexión; sin captura |
| RNF07 | Comandos de respaldo y restauración documentados; prueba de recuperación en base temporal |
| RNF08 | Separación de vistas, controladores, modelos y conexión PDO |
| RNF09 | Repositorio Git, exclusiones y flujo de validación configurados; sin ejecución remota registrada |

## Límites del alcance

No se incluye exportación a Excel ni módulos específicos de impresiones o venta de dulces. No hay un servicio transaccional de ventas, registro de movimientos de inventario, control de concurrencia comercial ni historial automático de reparaciones.

La identidad visual sigue la propuesta de Telcom: azul marino #17365D, azul #246B9E, fondos #F7F9FC y disposición de menú lateral, barra superior, indicadores y tablas. Los campos y valores proceden del esquema existente. La opción “Todas las sucursales” es un filtro de consulta; no una asignación de usuario.
