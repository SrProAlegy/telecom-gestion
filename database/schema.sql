-- MySQL 8.4. Ejecutado una sola vez al inicializar un volumen vacío.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE sucursales (
    id_sucursal INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(25) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT chk_sucursal_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(150) NOT NULL,
    correo VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(30) NOT NULL,
    sucursal_id INT UNSIGNED NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_sucursal (id_usuario, sucursal_id),
    CONSTRAINT fk_usuario_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id_sucursal),
    CONSTRAINT chk_usuario_rol CHECK (rol IN ('administrador', 'recepcion', 'tecnico', 'ventas')),
    CONSTRAINT chk_usuario_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE clientes (
    id_cliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    telefono VARCHAR(25) NULL,
    correo VARCHAR(254) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE equipos (
    id_equipo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    marca VARCHAR(80) NULL,
    modelo VARCHAR(100) NULL,
    imei VARCHAR(15) NULL,
    color VARCHAR(60) NULL,
    estado_fisico TEXT NULL,
    CONSTRAINT fk_equipo_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente),
    CONSTRAINT chk_equipo_imei CHECK (imei IS NULL OR REGEXP_LIKE(imei, '^[0-9]{15}$')),
    INDEX idx_equipo_imei (imei)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE tecnicos (
    id_tecnico INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(150) NOT NULL,
    telefono VARCHAR(25) NULL,
    especialidad VARCHAR(150) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT chk_tecnico_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE estados_reparacion (
    codigo VARCHAR(30) PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    orden TINYINT UNSIGNED NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE reparaciones (
    id_reparacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    sucursal_id INT UNSIGNED NOT NULL,
    problema_reportado TEXT NOT NULL,
    diagnostico TEXT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'recibido',
    observaciones TEXT NULL,
    fecha_recepcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_entrega DATETIME NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reparacion_equipo FOREIGN KEY (equipo_id) REFERENCES equipos (id_equipo),
    CONSTRAINT fk_reparacion_tecnico FOREIGN KEY (tecnico_id) REFERENCES tecnicos (id_tecnico),
    CONSTRAINT fk_reparacion_usuario_sucursal FOREIGN KEY (usuario_id, sucursal_id) REFERENCES usuarios (id_usuario, sucursal_id),
    CONSTRAINT fk_reparacion_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id_sucursal),
    CONSTRAINT fk_reparacion_estado FOREIGN KEY (estado) REFERENCES estados_reparacion (codigo),
    CONSTRAINT chk_reparacion_fechas CHECK (fecha_entrega IS NULL OR fecha_entrega >= fecha_recepcion),
    INDEX idx_reparacion_filtros (sucursal_id, estado, fecha_recepcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Historial de estados, observaciones y responsables de cada reparación.
CREATE TABLE historial_reparaciones (
    id_historial BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reparacion_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    sucursal_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NULL,
    estado VARCHAR(30) NOT NULL,
    diagnostico TEXT NULL,
    observaciones TEXT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historial_reparacion FOREIGN KEY (reparacion_id) REFERENCES reparaciones (id_reparacion),
    CONSTRAINT fk_historial_usuario_sucursal FOREIGN KEY (usuario_id, sucursal_id) REFERENCES usuarios (id_usuario, sucursal_id),
    CONSTRAINT fk_historial_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id_sucursal),
    CONSTRAINT fk_historial_tecnico FOREIGN KEY (tecnico_id) REFERENCES tecnicos (id_tecnico),
    CONSTRAINT fk_historial_estado FOREIGN KEY (estado) REFERENCES estados_reparacion (codigo),
    INDEX idx_historial_seguimiento (reparacion_id, fecha, id_historial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE productos (
    id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    precio DECIMAL(12,2) NOT NULL,
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 0,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id_categoria),
    CONSTRAINT chk_producto_precio CHECK (precio >= 0),
    CONSTRAINT chk_producto_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE inventario (
    id_inventario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    sucursal_id INT UNSIGNED NOT NULL,
    cantidad INT NOT NULL DEFAULT 0,
    fecha_ultimo_mov DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventario_producto_sucursal (producto_id, sucursal_id),
    INDEX idx_inventario_sucursal (sucursal_id, producto_id),
    CONSTRAINT fk_inventario_producto FOREIGN KEY (producto_id) REFERENCES productos (id_producto),
    CONSTRAINT fk_inventario_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id_sucursal),
    CONSTRAINT chk_inventario_cantidad CHECK (cantidad >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE ventas (
    id_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NULL,
    sucursal_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_venta_usuario_sucursal FOREIGN KEY (usuario_id, sucursal_id) REFERENCES usuarios (id_usuario, sucursal_id),
    CONSTRAINT fk_venta_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente),
    CONSTRAINT fk_venta_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id_sucursal),
    CONSTRAINT chk_venta_total CHECK (total >= 0),
    CONSTRAINT chk_venta_pago CHECK (CHAR_LENGTH(TRIM(metodo_pago)) > 0),
    INDEX idx_venta_sucursal_fecha (sucursal_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE detalle_venta (
    id_detalle INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(14,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    CONSTRAINT fk_detalle_venta FOREIGN KEY (venta_id) REFERENCES ventas (id_venta),
    CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos (id_producto),
    CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_detalle_precio CHECK (precio_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE schema_version (
    version INT UNSIGNED PRIMARY KEY,
    aplicado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
