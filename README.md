# Telcom Gestión

Sistema web de gestión multisucursal con HTML, CSS, JavaScript y Bootstrap local; PHP 8.4 por capas, PDO y MySQL 8.4. Docker Compose proporciona Apache, PHP, MySQL y phpMyAdmin para el desarrollo local.

Incluye autenticación, permisos por rol y sucursal, administración de usuarios y catálogos, recepción y seguimiento de reparaciones, existencias, registro transaccional de ventas, consultas, exportaciones Excel, reportes CSV e impresión. Las cuentas de MySQL son independientes de las cuentas que inician sesión en el sistema.

## Arranque

Requiere Docker Desktop con contenedores Linux y Docker Compose v2. No requiere instalar XAMPP, PHP, MySQL, Node ni Composer en el equipo para utilizar la aplicación.

Desde la carpeta del proyecto, conserva `.env` si ya existe. En una instalación nueva:

```powershell
Copy-Item .env.example .env
```

Configura las contraseñas de MySQL en `.env` y ejecuta:

```sh
docker compose config --quiet
docker compose up -d --build --wait --wait-timeout 240
```

La aplicación está en http://localhost:8080 y el gestor de datos en http://localhost:8081. MySQL publica `127.0.0.1:3306`, base `telcom_gestion`, usuario `admin_telecom` y contraseña `DB_PASSWORD`. Root utiliza `DB_ROOT_PASSWORD`. `APP_PORT` permite cambiar el puerto web. Las credenciales y los archivos SQL existentes se conservan al actualizar el código.

## Primer administrador

La instalación no incluye cuentas ni contraseñas de aplicación predefinidas. Si todavía no hay usuarios, ejecuta este comando sustituyendo los datos por los tuyos:

```sh
docker compose exec web php backend/crear-admin.php --correo="tu.correo@dominio.com" --nombre="Tu nombre" --apellido="Tu apellido" --sucursal="Nombre de tu sucursal"
```

El comando pide la contraseña sin mostrarla en pantalla. Usa entre 4 y 72 bytes. Crea la sucursal si no existe una activa con ese nombre y registra al primer administrador dentro de una transacción. Se niega a ejecutarse cuando ya hay usuarios. No pases la contraseña como argumento del comando. `--password-stdin` está destinado a automatización que entregue la contraseña por entrada estándar.

Después inicia sesión por correo y contraseña en `/acceso`. Desde **Usuarios y sucursales** administra las sucursales, los técnicos y las cuentas adicionales. Técnicos y usuarios son entidades separadas del modelo; una cuenta con rol técnico no crea automáticamente un registro de técnico.

## Operación

1. Registra sucursales, usuarios y técnicos.
2. Registra clientes y sus equipos.
3. Recibe reparaciones en la sucursal de tu cuenta. Asigna técnico, registra diagnóstico y actualiza el estado. **Historial** muestra cada evento confirmado.
4. Crea categorías y productos; consulta y ajusta las existencias por sucursal.
5. Selecciona la sucursal de tu cuenta en **Ventas**, agrega productos y registra el pago. El servidor calcula el total, guarda el detalle y descuenta existencias en una sola transacción.
6. Consulta los reportes por fechas y sucursal, imprime o descarga Excel/CSV.

El administrador puede consultar todas las sucursales. Cada venta y recepción debe registrarse en la sucursal del operador, incluida una cuenta administradora: las claves foráneas del esquema exigen esa correspondencia. Para operar en otra sucursal utiliza una cuenta asignada a ella. Los usuarios con operaciones conservan su sucursal histórica.

Los teléfonos opcionales admiten entre 7 y 15 dígitos con formato internacional. Cada equipo requiere al menos marca, modelo o IMEI. Los formularios rechazan espacios como único contenido de un campo obligatorio.

Los listados principales se navegan en páginas de 100 registros. Los catálogos de selección y productos para venta no están limitados a la primera página. Las fechas de consulta se interpretan en America/Mexico_City y se convierten a UTC; la fecha final es inclusiva. El stock bajo corresponde al estado actual, no a existencias históricas del periodo.

## Exportaciones Excel

**Descargar Excel** está disponible en Clientes y equipos, Reparaciones, Inventario, Ventas y Reportes. Genera archivos `.xlsx` nativos, con hojas separadas para registros y detalle, encabezados inmovilizados, filtros de Excel, fechas locales y columnas numéricas. Una hoja Criterios identifica el alcance de la descarga. El archivo incluye todas las coincidencias, independientemente de la página del listado; respeta los mismos filtros y permisos del módulo. En reparaciones incluye el historial y las dos sucursales de cada evento.

Las celdas de texto conservan teléfonos e IMEI sin convertirlos en números o fórmulas. La exportación usa una transacción de lectura para mantener una misma vista de los datos y genera primero un archivo temporal, que elimina al finalizar. El inventario representa las existencias actuales. Si una hoja supera 1,048,576 filas o una celda supera 32,767 caracteres, se solicita reducir el resultado en lugar de truncarlo.

Docker instala la dependencia OpenSpout desde `backend/composer.lock` en `/opt/telcom/vendor`; no necesitas Composer en Windows. Los cambios de dependencias o extensiones requieren reconstruir la imagen. CSV e impresión continúan disponibles en reportes. La exportación es una consulta: no altera tablas ni sustituye un respaldo SQL.

## Permisos

| Operación | Administrador | Recepción | Técnico | Ventas |
|---|---|---|---|---|
| Panel y consulta de clientes/equipos | Sí | Sí | Sí | Sí |
| Alta y edición de clientes/equipos | Sí | Sí | No | Sí |
| Consulta de reparaciones | Sí | Sí | Sí | No |
| Recepción de reparaciones | Sí | Sí | No | No |
| Seguimiento técnico | Sí | Sí | Sí | No |
| Entrega y cancelación | Sí | Sí | No | No |
| Consulta de inventario y reportes | Sí | Sí | No | Sí |
| Catálogos, existencias y administración | Sí | No | No | No |
| Registro y consulta de ventas | Sí | No | No | Sí |

El área técnica consulta y actualiza reparaciones de todas las sucursales, con filtros por sede, estado y técnico. Su panel muestra únicamente la operación de reparaciones. Recepción entrega y cancela únicamente reparaciones de su sucursal. El administrador conserva el control global del seguimiento. El historial distingue la sucursal receptora de la sucursal del responsable de cada evento. Los demás datos operativos se limitan a la sucursal de la cuenta, salvo las consultas del administrador. Clientes, equipos, categorías, productos y técnicos son catálogos compartidos. La sucursal de una cuenta no se deduce de filtros del navegador.

## Estructura

```text
frontend/public/          Entrada HTTP y recursos públicos
frontend/vistas/          Plantilla, pantallas y formularios PHP
backend/controladores/    Rutas, autenticación HTTP y respuestas
backend/modelos/          Consultas, autenticación y operaciones transaccionales
backend/soporte/          Sesión, permisos, validación, Excel y errores de operación
backend/composer.lock    Versiones de las dependencias del backend
backend/config/           Conexión PDO por variables de entorno
backend/crear-admin.php   Alta inicial, solo desde consola
database/                Esquema y catálogo inicial
docker/                  Configuración de PHP y Apache
tests/                   Pruebas; compose.yaml usa una base aislada
docs/                    Modelo, contrato de integración y validación
backups/                 Respaldos locales excluidos de Git
```

Edita los archivos y recarga el navegador. No hay compilación frontend. Los cambios en PHP/Apache requieren reconstruir el servicio. Apache expone únicamente `frontend/public`. `.env`, respaldos y dependencias están excluidos de Git.

## Sesión y consistencia

Las contraseñas se guardan mediante `password_hash` y se comprueban con `password_verify`. Las sesiones regeneran su identificador al entrar y salir, caducan tras 30 minutos de inactividad u ocho horas y comprueban en cada solicitud que la cuenta y sucursal sigan activas. Cambiar una contraseña invalida las sesiones anteriores. Las escrituras, incluido el acceso y la salida, exigen un token CSRF. El acceso limita diez intentos fallidos por dirección IP en cinco minutos.

PDO utiliza consultas preparadas nativas, utf8mb4 y UTC. Ventas e historial usan transacciones y bloqueos. No se aceptan precios, totales ni responsables del navegador como autoridad. Un conflicto revierte la operación completa. Las ventas incluyen una clave de operación para reconocer reenvíos de la misma solicitud dentro de la sesión; esta protección no es un registro persistente independiente del contenedor web. El seguimiento y el ajuste de existencias detectan formularios desactualizados. La aplicación conserva operaciones históricas y no ofrece borrado físico de registros.

Las sesiones y el límite de intentos se almacenan en el contenedor web y se pierden al recrearlo. La configuración es local, con puertos limitados a este equipo; un despliegue público requiere HTTPS, configuración de infraestructura y una revisión de seguridad específica. No hay recuperación de contraseña por correo ni pasarela de pagos: el método de pago registra la forma de cobro de una venta.

## Comprobaciones

```sh
docker compose exec -T web php tests/http.php
docker compose exec -T web php docker/php/healthcheck.php
docker compose -f tests/compose.yaml up --build --abort-on-container-exit --exit-code-from pruebas
docker compose -f tests/compose.yaml down
```

La suite de backend utiliza **otro proyecto Compose**, sin puertos publicados, con MySQL temporal en memoria y sin el volumen comercial. Comprueba altas, ediciones, sesiones, CSRF, permisos, historial entre sucursales, Excel, reportes, paginación, rollback y ventas concurrentes con dos servidores. Las cuentas y operaciones que crea pertenecen exclusivamente a `telcom_pruebas`. Esa suite se niega a utilizar `telcom_gestion`.

La prueba `tests/integration.php` revisa las restricciones SQL dentro de una transacción que revierte sus registros; puede avanzar contadores. Se ejecuta en CI sobre una base efímera. Las comprobaciones públicas no necesitan cuentas de aplicación. `/health` devuelve 200 cuando MySQL y el catálogo inicial están disponibles, o 503 si no lo están.

Herramientas opcionales de revisión:

```sh
composer --working-dir=tests/herramientas install
composer --working-dir=tests/herramientas lint
npm ci --prefix tests/herramientas
npm run lint --prefix tests/herramientas
npm test --prefix tests/herramientas
```

## Datos y mantenimiento

`schema.sql` y `seed.sql` se ejecutan únicamente al inicializar un volumen vacío. Actualizar el código no recrea tablas ni modifica datos existentes. Cambiar las contraseñas de `.env` no actualiza las cuentas de un volumen ya creado: debe coordinarse también el cambio en MySQL.

`docker compose down` detiene el entorno y conserva la base. No uses `down -v` sobre datos que quieras conservar.

## Respaldo y restauración local

Exportación dentro del contenedor y copia binaria al equipo (evita que la redirección de PowerShell cambie la codificación):

```sh
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --no-tablespaces --routines --triggers --set-gtid-purged=OFF "$MYSQL_DATABASE" > /tmp/telcom-backup.sql'
docker compose cp db:/tmp/telcom-backup.sql ./backups/telcom-backup.sql
```

Verifica que la exportación terminó sin errores y conserva copias fuera del equipo, con acceso restringido. Para restaurar **sobre una base de pruebas**, con los servicios en marcha:

```sh
docker compose cp ./backups/telcom-backup.sql db:/tmp/telcom-restore.sql
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE" < /tmp/telcom-restore.sql'
docker compose exec -T web php docker/php/healthcheck.php
```

Restaurar reemplaza tablas/datos según el archivo; comprueba el destino y detén operaciones durante la restauración. Antes de usar respaldos en producción, prueba periódicamente la recuperación en un proyecto/volumen independiente. Las pruebas de integridad utilizan registros temporales y revierten sus cambios al terminar.


## Documentación

- [Alcance y modelo](docs/alcance-y-modelo.md)
- [Contrato de integración](docs/integracion-frontend.md)
- [Validación](docs/validacion.md)
- [Recorrido funcional y hallazgos](docs/revision-funcional.md)

El repositorio mantiene el flujo de comprobaciones en `.github/workflows/validate.yml`. Las imágenes de identidad visual son las proporcionadas en `frontend/public/assets`.

## Versión y distribución

La versión local `v0.2.0` identifica el backend operativo, el área técnica entre sucursales y las exportaciones Excel. La etiqueta de Git permite recuperar esa versión del código. El paquete de distribución contiene los archivos editables, SQL, configuración y documentación; excluye credenciales, datos locales, respaldos y dependencias instaladas. En otra ubicación o equipo, sigue las instrucciones de Arranque y Primer administrador. La base existente se mantiene en su volumen de Docker y no forma parte del paquete de código.
