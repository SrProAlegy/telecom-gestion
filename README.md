# Telcom Gestión — base ejecutable

Aplicación web para la gestión de sucursales, clientes, equipos, reparaciones, inventario y ventas de Telcom. HTML5, CSS3, JavaScript y Bootstrap; PHP 8.4 por capas con PDO; MySQL 8.4; Apache; Git. Docker Compose sustituye exclusivamente a XAMPP.

Funciona la página inicial, la comprobación HTTP/PDO/esquema y la inicialización de la base. **El alcance funcional actual se limita a la comprobación de servicios y base de datos.** La aplicación no incluye autenticación, formularios de captura, operaciones de reparación, movimientos de inventario, registro de ventas ni reportes. Las cuentas de MySQL son independientes de los usuarios de la aplicación.

## Requisitos

Docker Desktop con contenedores Linux y Docker Compose v2 o posterior. Git para control de versiones. No hace falta instalar PHP, Apache o MySQL en el equipo. Node/Composer solo se necesitan para ejecutar los linters fuera de CI.

## Ejecutar

Para una instalación nueva, copia el ejemplo de configuración (si ya existe `.env`, consérvalo):

```powershell
Copy-Item .env.example .env
```

En macOS/Linux: `cp .env.example .env`. Abre `.env` y reemplaza ambas contraseñas por claves largas, distintas y sin reutilizar. No publiques ese archivo. Para evitar expansión accidental de `$` en Compose, usa claves hexadecimales (por ejemplo 48 caracteres) o valores entre comillas simples. El ejemplo es una plantilla, no una clave segura.

```sh
docker compose config --quiet
docker compose up -d --build --wait --wait-timeout 240
```

Abre [Telcom Gestión](http://localhost:8080). Debe aparecer «Conexión y esquema disponibles». El endpoint [de comprobación](http://localhost:8080/health) devuelve HTTP 200 y `{"status":"ok","database":"ready"}`; devuelve 503 si falla la conexión o la inicialización. El primer arranque descarga imágenes y puede tardar varios minutos.

Si el puerto está ocupado, cambia `APP_PORT` en `.env` y ejecuta de nuevo `docker compose up -d`. MySQL publica `127.0.0.1:3306` para gestores locales; PHP lo alcanza mediante `db:3306`. Apache se publica en `127.0.0.1:8080` y phpMyAdmin en `127.0.0.1:8081`, para desarrollo local.

## Editar y entender el proyecto

El código está montado desde esta carpeta: edita y recarga el navegador. No hay compilación frontend ni framework adicional. Los recursos Bootstrap están incluidos localmente, por lo que la página no necesita un CDN.

```text
frontend/
  public/                 Único directorio expuesto por Apache
    index.php             Entrada y rutas GET/HEAD
    assets/css/           Estilos propios
    assets/js/            JavaScript propio
    assets/bootstrap/     Bootstrap y su licencia
  vistas/inicio.php       HTML y presentación de datos
backend/
  bootstrap.php           Carga de las clases utilizadas
  config/database.php     Configuración y conexión PDO
  controladores/          Solicitudes y respuestas
  modelos/                Consultas a MySQL
database/                 Esquema y catálogos iniciales
docker/                   Configuración Apache/PHP y comprobación del contenedor
tests/                    Pruebas de integridad, HTTP y recuperación
  herramientas/           Configuración y dependencias de los linters
docs/                     Alcance y evidencia de validación
backups/                  Respaldos locales excluidos de Git
.github/workflows/        Validación automática
```

El archivo `.env` lo interpreta Compose; PHP recibe las variables explícitas del servicio. El código no lee ni expone el archivo. PDO usa utf8mb4, excepciones, consultas preparadas nativas y una cuenta de aplicación distinta de root. Las credenciales root solo se entregan al contenedor MySQL. Los mensajes públicos no incluyen detalles SQL ni secretos. El enlace PDO va por la red local de Compose; no se ha configurado TLS hacia una base remota. La configuración corresponde a un entorno local HTTP; no incluye despliegue público con HTTPS ni control de acceso a los módulos.

## Datos e inicialización

`schema.sql` implementa las 11 entidades del ER y las extensiones documentadas en [docs/alcance-y-modelo.md](docs/alcance-y-modelo.md). `seed.sql` carga solo estados de reparación y la versión del esquema. Los scripts de inicialización no cargan sucursales, usuarios de la aplicación ni operaciones comerciales.

Los scripts de `docker-entrypoint-initdb.d` se ejecutan **solo con un volumen vacío**. Editar SQL no actualiza una base existente; los cambios posteriores necesitan migraciones versionadas. Cambiar la contraseña en `.env` tampoco modifica los usuarios de un volumen ya creado: hay que coordinar el cambio con `ALTER USER` en MySQL.

## Comprobar y detener

```sh
docker compose ps
docker compose exec -T web php docker/php/healthcheck.php
docker compose exec -T web php tests/integration.php
docker compose exec -T web php tests/http.php
docker compose logs --tail=100 web db
docker compose down
```

La prueba SQL crea datos temporales dentro de una transacción y hace rollback incluso ante error; puede avanzar contadores autoincrementales. Su alcance es la integridad del esquema; no evalúa procesos de negocio. Las pruebas de indisponibilidad y de restauración se describen en `docs/validacion.md`. `down` conserva los datos. **`docker compose down -v` elimina la base de este proyecto**: úsalo únicamente si deseas reinicializar una base descartable y ya tienes un respaldo. No es parte del arranque habitual.

Linters opcionales (CI los ejecuta):

```sh
composer --working-dir=tests/herramientas install
composer --working-dir=tests/herramientas lint
npm ci --prefix tests/herramientas
npm run lint --prefix tests/herramientas
```

PHP_CodeSniffer aplica PSR-12 al PHP de aplicación; ESLint valida el JavaScript propio. CI también levanta MySQL, comprueba sintaxis y ejecuta las pruebas. No se ha conectado un repositorio GitHub ni ejecutado el flujo remoto. Los resultados y límites de las comprobaciones están documentados en [docs/validacion.md](docs/validacion.md).

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

## Control de versiones y alcance

El proyecto cuenta con un repositorio Git local. `.gitignore` excluye `.env`, respaldos y dependencias. El flujo de validación está definido en `.github/workflows/validate.yml`.

Para una copia sin repositorio, `git init -b main` inicializa Git. La identidad de los commits se configura con el nombre y correo del autor.

La correspondencia con RF01–RF17 y RNF01–RNF09, las restricciones del modelo y las funciones no disponibles se describen en [docs/alcance-y-modelo.md](docs/alcance-y-modelo.md).

Referencias técnicas: [Compose y disponibilidad de dependencias](https://docs.docker.com/compose/how-tos/startup-order/), [PDO MySQL](https://www.php.net/manual/en/ref.pdo-mysql.php), [Bootstrap 5.3](https://getbootstrap.com/docs/5.3/getting-started/introduction/).

## Gestor visual de la base de datos

Abre [phpMyAdmin](http://localhost:8081) e inicia sesión con el usuario `admin_telecom` y el valor de `DB_PASSWORD` en `.env`. Selecciona `telcom_gestion` para consultar las tablas, su estructura y los registros. El gestor se conecta al servicio `db` dentro de Docker y no guarda las contraseñas en su configuración.

También puedes usar un gestor de escritorio: host `127.0.0.1`, puerto `3306`, base `telcom_gestion`, usuario `admin_telecom` y contraseña `DB_PASSWORD` de `.env`. Para administración, la cuenta `root` utiliza `DB_ROOT_PASSWORD`. Los puertos de MySQL y phpMyAdmin están limitados al equipo local.

El usuario inicial de Compose es `admin_telecom`. Cambiar `MYSQL_USER` o las contraseñas de `.env` no renombra ni actualiza cuentas en un volumen ya inicializado: se debe cambiar también la cuenta dentro de MySQL y después recrear los servicios con la configuración coincidente.

Referencia de configuración del gestor: [imagen oficial de phpMyAdmin](https://hub.docker.com/_/phpmyadmin).
La estructura separa la presentación en `frontend` y el acceso a datos/controladores en `backend`. La copia local de configuración previa está conservada en `backups/.env.before-connection-fix`.
