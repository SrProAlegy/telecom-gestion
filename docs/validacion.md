# Validación de la base ejecutable

Pruebas realizadas localmente con Docker Desktop, contenedores Linux, PHP 8.4 y MySQL 8.4. Cubren el entorno, el esquema y las respuestas de la página de estado. No evalúan procesos de negocio.

## Arranque real

`docker compose config --quiet` y `docker compose up -d --build --wait --wait-timeout 240` finalizaron correctamente. Los servicios web y db quedaron healthy; phpMyAdmin quedó en ejecución. Los scripts SQL se ejecutaron sobre un volumen nuevo. La comprobación atraviesa Apache → entrada PHP → controlador → modelo → PDO → MySQL, y exige versión de esquema 1 y los ocho estados iniciales.

## Integridad SQL

`docker compose exec -T web php tests/integration.php`: 11 comprobaciones correctas. Verifica varias reparaciones por equipo, varios eventos de historial, inventario único por producto/sucursal, rechazo de stock negativo, existencias independientes por sede, correspondencia entre operador y sucursal, cálculo decimal de subtotal, rechazo de cantidad cero, estados válidos, claves foráneas y catálogo inicial. Los datos temporales fueron revertidos. No evalúa el registro de ventas ni el descuento de inventario desde la aplicación.

## Respuestas HTTP y archivos privados

`docker compose exec -T web php tests/http.php`: diez casos correctos. Inicio y health 200, ruta desconocida 404, POST a health 405, acceso a `.env` 403, configuración y SQL fuera del directorio público 404, Bootstrap local 200.

## Indisponibilidad y recuperación

Se detuvo únicamente el servicio db de este proyecto, se ejecutó `tests/unavailable.php` y se recuperó con `docker compose up -d --wait --wait-timeout 120`. Se recibió HTTP 503 con JSON genérico, sin mensajes SQL ni credenciales. Después, web y db recuperaron su estado healthy sin recrear el volumen.

Para repetir exclusivamente en desarrollo:

```powershell
try {
    docker compose stop db
    docker compose exec -T web php tests/unavailable.php
    if ($LASTEXITCODE -ne 0) { throw 'Falló prueba de indisponibilidad' }
} finally {
    docker compose up -d --wait --wait-timeout 120
}
```

El DNS interno de Docker puede demorar cuando el servicio está detenido. La prueba negativa permite 30 segundos; el botón de la interfaz limita la espera a seis segundos y muestra un mensaje de conexión si se alcanza ese límite.

## Respaldo y restauración

`tests/backup-roundtrip.sh` exportó mediante mysqldump y restauró en una base temporal independiente dentro del contenedor. Verificó 14 tablas, ocho estados y versión 1; eliminó solo esa base temporal al salir. El esquema original permaneció disponible. Para repetir:

```sh
docker compose cp tests/backup-roundtrip.sh db:/tmp/telcom-backup-check.sh
docker compose exec -T db sh /tmp/telcom-backup-check.sh
```

Esta prueba cubre la estructura y los catálogos iniciales; no acredita recuperación de operaciones comerciales. El README contiene los comandos para guardar un respaldo fuera del contenedor.

## Revisión del código

- Los 10 archivos PHP pasaron `php -l`.
- PHP_CodeSniffer 3.13.6 pasó sin errores ni advertencias con el estándar del proyecto.
- ESLint 10.15.0 pasó para el JavaScript propio con Node 22.
- Composer y npm tienen archivos lock incluidos. Las herramientas de revisión son dependencias de desarrollo, no requisitos para arrancar la aplicación.
- Git confirmó que `.env`, dependencias y respaldos están excluidos. Las dependencias se instalan mediante los archivos lock de Composer y npm.

## Interfaz

Se revisó la página en navegador en vista estrecha y de escritorio. El botón «Comprobar de nuevo» actualizó el estado correctamente. La página identifica los seis módulos como no habilitados e informa que su función actual es comprobar la conexión. La captura `pantalla-inicial.jpg` registra esa pantalla.

## Límites de la validación

No se ejecutó GitHub Actions en un remoto, ni una auditoría de producción, prueba de carga o validador formal HTML/CSS. No hay usuarios, sucursales comerciales ni operaciones precargadas. Las pruebas no acreditan el cumplimiento completo de los 17 RF. El entorno validado es local; no incluye validación de despliegue público ni de operación comercial.

## Reorganización frontend/backend

Se reorganizó el código activo en `frontend/public`, `frontend/vistas`, `backend/config`, `backend/controladores` y `backend/modelos`. Se concentró la comprobación de disponibilidad en el controlador y el modelo, sin una capa de servicios para esta operación sencilla. `backend/bootstrap.php` carga las clases utilizadas; las dos rutas están declaradas en `frontend/public/index.php`.

Se movieron Composer, npm, ESLint y PHP_CodeSniffer a `tests/herramientas`. El archivo local de configuración previa se conservó dentro de `backups`. Apache expone únicamente `frontend/public` y la comprobación del contenedor reside en `docker/php/healthcheck.php`.

Verificación posterior: reconstrucción Docker correcta; diez archivos PHP sin errores de sintaxis; 11 comprobaciones de integridad SQL y diez casos HTTP correctos; ESLint y PHP_CodeSniffer correctos desde sus nuevas ubicaciones; respuesta 503 ante una caída de MySQL y recuperación de los servicios. Las rutas directas a backend, vistas, herramientas y SQL devuelven 404. Git sigue excluyendo `.env`, la copia de configuración y las dependencias.

La base y sus credenciales permanecieron disponibles. La reorganización conservó el alcance funcional. El flujo de GitHub Actions utiliza las rutas actuales; no hay una ejecución remota registrada.