# Validación

## Backend y procesos de negocio

La suite `tests/backend.php` se ejecutó con `tests/compose.yaml`, PHP 8.4, MySQL 8.4 y dos servidores HTTP para comprobar concurrencia. Finalizó con **341 comprobaciones correctas**. El proyecto de pruebas utiliza `telcom_pruebas`, almacenamiento temporal de MySQL y ninguna publicación de puertos. No utiliza el volumen de `telcom_gestion`.

Se comprobaron alta inicial única del administrador; acceso válido e inválido; regeneración de sesión; CSRF en acceso y escrituras; cierre; revocación al desactivar cuenta; límite de intentos; permisos por rol y sucursal; altas y ediciones de catálogos; validación de formatos, correo, texto e IMEI; duplicados; historial y fecha de entrega; transiciones y cierre de reparaciones; conservación de sucursal histórica; protección del administrador; consulta de partidas; cálculo decimal; rollback al fallar una partida; conflicto de existencias; protección de seguimiento desactualizado; exportación CSV; escape HTML y paginación.

Dos sesiones en servidores distintos solicitaron simultáneamente las dos últimas unidades de un producto: una venta confirmó con HTTP 201 y la otra se rechazó con 409. Se verificó una sola venta, total 0.58 calculado con precio 0.29 y stock final cero.

Para repetir:

```sh
docker compose -f tests/compose.yaml up --build --abort-on-container-exit --exit-code-from pruebas
docker compose -f tests/compose.yaml down
```

## Comprobaciones públicas y código

`tests/http.php` comprueba que las páginas privadas redirijan al acceso, que los archivos internos no sean accesibles y que health y recursos públicos respondan. Las consultas autenticadas y los errores de filtros se comprueban en la suite aislada. `tests/frontend.mjs` revisa importes en centavos, límites de precisión y la conversión de formularios a JSON.

Las revisiones de código utilizan sintaxis PHP, PHP_CodeSniffer y ESLint. `.github/workflows/validate.yml` incluye las pruebas públicas, integridad SQL, pruebas de backend aisladas y linters. La configuración del flujo no acredita una ejecución en GitHub.

## Conservación del esquema

Los archivos SQL mantienen sus hashes previos a la implementación:

- schema.sql: E51CB4D4B353AFEB7F54ABB5A09CE768BF502D618808D31F6A8296E947A7FA4A
- seed.sql: 3701CFE25DC30ECF95DABBB599ECC32154D2724256B731493A6EEFBD95B2C51E

Las operaciones de negocio de esta revisión se probaron en la base aislada. No se cargaron cuentas ni operaciones de prueba en la base comercial.

## Comprobaciones de infraestructura existentes

`tests/integration.php` comprueba restricciones y relaciones SQL dentro de una transacción con rollback. `tests/unavailable.php` verifica la respuesta de health ante MySQL detenido. `tests/backup-roundtrip.sh` exporta y restaura sobre una base temporal separada dentro del contenedor. Sus ejecuciones previas constan en el historial del repositorio; no son pruebas de recuperación comercial ni auditorías de producción.

## Límites

La suite automatizada no sustituye pruebas de aceptación con datos de negocio. La concurrencia comprobada cubre dos ventas competidoras; no acredita capacidad de carga. No se ejecutó auditoría de seguridad de producción, despliegue público ni validación de una pasarela de pagos. Las imágenes de identidad visual son las proporcionadas por el usuario.

## Revisión en navegador

En un servicio temporal conectado exclusivamente a telcom_pruebas se verificaron acceso y salida, alta y edición de cliente, ajuste de existencias, seguimiento con historial y venta desde el carrito. Se observó confirmación de persistencia y actualización de los listados. El servicio temporal y su base se cerraron al terminar. La captura acceso-backend.jpg corresponde al acceso habitual con las imágenes proporcionadas.

La consulta final de la base habitual confirmó cero registros comerciales y ocho estados iniciales. Los datos creados por las pruebas no se cargaron allí.

## Revisión funcional y regresiones

El recorrido posterior con la cuenta administradora configurada comprobó las siete secciones y el inicio/cierre de sesión en el entorno habitual. Las capturas completas se realizaron en una base aislada, utilizando una copia temporal de la cuenta. Se mantuvieron sin operaciones los clientes, equipos, reparaciones, inventario y ventas de la base habitual; permanecieron su sucursal y usuario iniciales.

Se corrigió el envío de cantidades escritas en el carrito: dos unidades a 150.50 registraron 301.00, dos unidades en detalle y el descuento exacto. Se comprobó el rechazo de cantidad vacía y stock negativo; recepción, diagnóstico, reparación, disponibilidad y entrega con cinco eventos de historial; cierre sin edición posterior; categoría y producto; consulta global de ventas sin captura fuera de la sucursal; descarga CSV; fechas invertidas; menú móvil y cierre con Escape.

La prueba de frontend incluye una regresión con eventos de escritura que verifica el total y el JSON de venta, además de espacios, teléfonos, importes y contraseña simple. Las 341 comprobaciones del backend incluyen reenvío de ventas sin nuevos registros, rechazo de reutilización de clave con contenido diferente y desactivación de cuentas en sucursales inactivas. El detalle y los límites están en revision-funcional.md.

## Operación central y Excel

La revisión de cierre comprobó recepción en C, seguimiento por una cuenta técnica en B, consulta desde A/B/C y entrega por recepción C. Se verificaron la conservación de la sucursal y receptor originales, seis eventos con sucursal del operador y receptora, restricción de entrega/cancelación para técnicos, conflictos de edición y exclusión de ventas/inventario del panel técnico.

Los libros XLSX se descargaron por HTTP autenticado y se reabrieron con un lector de Excel; se comprobaron integridad ZIP, XML, nombres de hojas, filtros, encabezados inmovilizados, ausencia de fórmulas, texto literal, IMEI con ceros iniciales, fechas reales con hora local, importes numéricos y coincidencia entre ventas y partidas. Una búsqueda de 101 clientes se exportó completa desde la página 2. Se comprobaron resultados vacíos, filtros inválidos y rechazo de accesos fuera del rol/sucursal.

En navegador se registró otra reparación desde recepción C, se atendió desde técnico B hasta lista para entrega y se entregó en C. La reparación cerrada conservó el historial y dejó de ofrecer edición. Se descargó el libro de reparaciones mediante el botón de la pantalla. La captura `entrega-multisucursal.jpg` corresponde exclusivamente a la base aislada.

## Instalación desde una copia limpia

Se generó una copia del proyecto sin `.env`, `.git`, respaldos ni dependencias locales. Se creó un `.env` independiente y se construyó la imagen desde esa copia con el proyecto Compose `telcom-gestion-instalacion`, su propio volumen de MySQL y el puerto web 8083. Los puertos de MySQL y phpMyAdmin de esa copia no se publicaron. La base y los servicios habituales permanecieron separados.

La instalación completó los healthchecks, 37 casos HTTP públicos, 11 comprobaciones de relaciones y restricciones SQL con rollback y el alta inicial por consola. Se inició sesión y se comprobaron las siete secciones y los cinco tipos de libro XLSX, incluyendo libros sin registros comerciales. El respaldo se restauró en una base temporal con 14 tablas, ocho estados y versión de esquema 1. Al detener MySQL se obtuvo health 503 genérico; después de arrancarlo nuevamente se recuperó health 200.

También finalizaron sin errores la revisión de sintaxis PHP, PHP_CodeSniffer, ESLint y las regresiones JavaScript. Composer validó las dependencias de ejecución y no reportó avisos de vulnerabilidades en la revisión realizada. La suite final aislada terminó con 341 comprobaciones. Estos resultados corresponden a ejecuciones locales; no se publicó ni ejecutó esta versión en GitHub Actions.

Al cierre, la base habitual conserva una sucursal y una cuenta, ocho estados iniciales y cero registros en clientes, equipos, técnicos, categorías, productos, inventario, reparaciones, historial, ventas y partidas. Los hashes de esquema y catálogo inicial coinciden con los indicados arriba. No se cambiaron las credenciales configuradas.
