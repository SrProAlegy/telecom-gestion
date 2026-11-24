# Telcom Gestión

**Sistema web de administración de reparaciones, inventario y ventas para múltiples sucursales.**

Telcom Gestión reúne en una sola plataforma la información de clientes, equipos, reparaciones, personal técnico, productos y ventas. Cada operación mantiene la referencia de su sucursal y los usuarios acceden a las funciones autorizadas según su rol.

El objetivo es facilitar la comunicación entre establecimientos y el área técnica, dar seguimiento a los servicios desde su recepción hasta la entrega y consultar las existencias y ventas de cada sucursal.

> **Identidad visual:** incorpora aquí el logotipo oficial y, si lo deseas, una captura del panel principal cuando sus rutas estén disponibles en el repositorio.

## Funcionalidades

| Módulo | Función principal |
|---|---|
| **Panel principal** | Muestra indicadores de reparaciones, ventas del día y productos con stock bajo. |
| **Clientes y equipos** | Registra clientes y los dispositivos asociados a cada uno. |
| **Reparaciones** | Administra recepción, asignación técnica, diagnóstico, estados e historial. |
| **Productos e inventario** | Mantiene el catálogo general y las existencias de cada sucursal. |
| **Ventas** | Registra productos y pagos; actualiza las existencias de la sucursal. |
| **Consultas y reportes** | Permite filtrar información por sucursal y periodo, e imprimir o exportar resultados. |
| **Usuarios y sucursales** | Administra cuentas, roles, sucursales y personal técnico. |

### Seguimiento de reparaciones

El servicio comienza con el registro del cliente y su equipo. La sucursal receptora crea una orden con el problema reportado y, durante la atención, se asigna un técnico y se agregan el diagnóstico y las observaciones.

**Flujo general:**

`Recibido → Enviado a técnico → Diagnóstico → En reparación → Listo para entrega → Entregado`

También existen los estados **Cancelado** y **No reparable**. El historial conserva las actualizaciones, sus responsables y las sucursales involucradas. El área técnica puede atender órdenes de distintas sedes sin perder la referencia de la sucursal que recibió el equipo.

### Ventas e inventario

Los productos forman un catálogo compartido, pero el inventario se controla por sucursal. Al registrar una venta, el sistema verifica las existencias, obtiene los precios del catálogo y actualiza las unidades disponibles. Los reportes permiten identificar productos por debajo de su stock mínimo.

### Roles y permisos

| Función | Administrador | Recepción | Técnico | Ventas |
|---|---|---|---|---|
| Consultar clientes y equipos | Sí | Sí | Sí | Sí |
| Registrar o editar clientes y equipos | Sí | Sí | No | Sí |
| Consultar reparaciones | Todas las sedes | Su sede | Todas las sedes | No |
| Recibir equipos | Su sede | Su sede | No | No |
| Actualizar seguimiento | Todas las sedes | Su sede | Todas las sedes | No |
| Entregar o cancelar reparaciones | Sí | Su sede | No | No |
| Consultar inventario y reportes | Todas las sedes | Su sede | No | Su sede |
| Registrar ventas | Su sede | No | No | Su sede |
| Administrar cuentas, catálogos y existencias | Sí | No | No | No |

El catálogo de personal técnico es independiente de las cuentas de usuario con rol **Técnico**.

## Reportes y exportación

El sistema permite consultar ventas, inventario y reparaciones con los filtros disponibles en cada módulo. Se pueden descargar archivos **Excel (`.xlsx`)** de clientes y equipos, reparaciones, inventario, ventas y reportes. En reportes también se dispone de **CSV** e impresión.

Las exportaciones respetan los permisos de la cuenta y los filtros de la consulta. **Los archivos exportados no sustituyen un respaldo de MySQL.**

## Tecnologías

| Componente | Tecnología |
|---|---|
| Interfaz | HTML5, CSS3, JavaScript y Bootstrap local |
| Servidor y lógica | PHP 8.4 con Apache |
| Acceso a datos | PDO |
| Base de datos | MySQL 8.4 |
| Exportación Excel | OpenSpout |
| Entorno de desarrollo local | Docker Compose |
| Control de versiones | Git y GitHub |

Docker Compose se utiliza **solo como entorno local**, en lugar de instalar XAMPP, PHP y MySQL directamente en la computadora. La aplicación mantiene su arquitectura PHP por capas.

### Organización del proyecto

```text
telcom-gestion/
├── frontend/
│   ├── public/           # Entrada web y recursos estáticos
│   └── vistas/           # Pantallas y formularios
├── backend/
│   ├── config/           # Conexión PDO
│   ├── controladores/    # Rutas, páginas y operaciones
│   ├── modelos/          # Consultas y reglas del sistema
│   ├── soporte/          # Sesiones, permisos y exportación
│   └── crear-admin.php   # Alta del administrador inicial
├── database/             # Esquema SQL y datos iniciales
├── docker/               # Configuración de Apache y PHP
├── docs/                 # Documentación complementaria
├── tests/                # Pruebas del proyecto
├── backups/              # Respaldos locales
├── compose.yaml
├── Dockerfile
├── .env.example
└── README.md
```

## Ejecución local con Docker

### Requisitos

- Docker Desktop con contenedores Linux y Docker Compose v2.
- Un navegador web moderno.

No es necesario instalar XAMPP, PHP, MySQL ni Composer en Windows para ejecutar la aplicación.

### 1. Configurar el entorno

Desde la carpeta raíz, crear el archivo de configuración local **solo si todavía no existe**:

```powershell
Copy-Item .env.example .env
```

En `.env`, establecer `DB_PASSWORD` y `DB_ROOT_PASSWORD`. No subir este archivo a Git.

### 2. Iniciar los servicios

```powershell
docker compose up -d --build --wait --wait-timeout 240
```

| Servicio | Dirección local |
|---|---|
| Aplicación web | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| MySQL | `127.0.0.1:3306` |

La base de datos utilizada por la aplicación se llama `telcom_gestion`. Los puertos pueden variar si se modifican en la configuración del entorno.

### 3. Crear el administrador inicial

**Solo cuando la base de datos aún no tenga usuarios**, ejecutar:

```powershell
docker compose exec web php backend/crear-admin.php --correo="tu.correo@dominio.com" --nombre="Nombre" --apellido="Apellido" --sucursal="Sucursal"
```

El comando solicita la contraseña de forma interactiva. Después, las cuentas y sucursales se administran desde el propio sistema.

### 4. Detener el entorno

```powershell
docker compose down
```

Este comando detiene los servicios sin eliminar el volumen habitual de MySQL. Evita usar `docker compose down -v` si deseas conservar los datos.

## Documentación complementaria

- [Alcance y modelo de datos](docs/alcance-y-modelo.md)
- [Integración entre interfaz y backend](docs/integracion-frontend.md)
- [Revisión funcional](docs/revision-funcional.md)
- [Validación técnica](docs/validacion.md)

La documentación ampliada reúne el modelo de datos, las reglas de operación y las verificaciones técnicas. El manual de usuario se mantiene como documento independiente.
