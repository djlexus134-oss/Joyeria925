-- Modulo Ordenes de Taller: seguimiento de reparaciones/modificaciones, cobros y permisos RBAC.
-- Idempotente: se puede ejecutar varias veces.

-- ---------------------------------------------------------------------------
-- 1) Tabla ordenes_taller
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ordenes_taller (
    id_orden_taller INT NOT NULL AUTO_INCREMENT,
    folio VARCHAR(20) NOT NULL,
    origen ENUM('inventario', 'cliente') NOT NULL,
    id_pieza_stock_FK INT NULL DEFAULT NULL,
    pieza_descripcion VARCHAR(255) NULL DEFAULT NULL,
    id_cliente_FK INT NULL DEFAULT NULL,
    id_taller_FK INT NULL DEFAULT NULL,
    tipo ENUM('reparacion', 'modificacion') NOT NULL,
    descripcion_problema TEXT NOT NULL,
    estado ENUM('recibida', 'en_taller', 'lista', 'entregada', 'cancelada') NOT NULL DEFAULT 'recibida',
    costo_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    saldo_pendiente DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    costo_taller DECIMAL(12,2) NULL DEFAULT NULL,
    fecha_compromiso DATE NULL DEFAULT NULL,
    fecha_entrega DATETIME NULL DEFAULT NULL,
    observaciones VARCHAR(500) NULL DEFAULT NULL,
    id_usuario_FK INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_orden_taller),
    UNIQUE KEY uq_ordenes_taller_folio (folio),
    KEY idx_ordenes_taller_estado (estado),
    KEY idx_ordenes_taller_cliente (id_cliente_FK),
    KEY idx_ordenes_taller_taller (id_taller_FK),
    KEY idx_ordenes_taller_stock (id_pieza_stock_FK),
    CONSTRAINT fk_ordenes_taller_stock
        FOREIGN KEY (id_pieza_stock_FK) REFERENCES piezas_stock (id_pieza_stock)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ordenes_taller_cliente
        FOREIGN KEY (id_cliente_FK) REFERENCES clientes (id_cliente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ordenes_taller_taller
        FOREIGN KEY (id_taller_FK) REFERENCES talleres (id_taller)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ordenes_taller_usuario
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) Tabla ordenes_taller_pagos
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ordenes_taller_pagos (
    id_pago INT NOT NULL AUTO_INCREMENT,
    id_orden_taller_FK INT NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    id_forma_pago_FK INT NOT NULL,
    estado ENUM('registrado', 'anulado') NOT NULL DEFAULT 'registrado',
    tipo_origen ENUM('cobro_tienda', 'ajuste') NOT NULL DEFAULT 'cobro_tienda',
    referencia VARCHAR(120) NULL DEFAULT NULL,
    id_usuario_FK INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pago),
    KEY idx_ot_pagos_orden (id_orden_taller_FK),
    KEY idx_ot_pagos_fecha (fecha_registro),
    CONSTRAINT fk_ot_pagos_orden
        FOREIGN KEY (id_orden_taller_FK) REFERENCES ordenes_taller (id_orden_taller)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ot_pagos_forma
        FOREIGN KEY (id_forma_pago_FK) REFERENCES forma_pago (id_forma_pago)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ot_pagos_usuario
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3) Tabla ordenes_taller_historial
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ordenes_taller_historial (
    id_historial INT NOT NULL AUTO_INCREMENT,
    id_orden_taller_FK INT NOT NULL,
    estado ENUM('recibida', 'en_taller', 'lista', 'entregada', 'cancelada') NOT NULL,
    nota VARCHAR(500) NULL DEFAULT NULL,
    id_usuario_FK INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_historial),
    KEY idx_ot_historial_orden (id_orden_taller_FK),
    CONSTRAINT fk_ot_historial_orden
        FOREIGN KEY (id_orden_taller_FK) REFERENCES ordenes_taller (id_orden_taller)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ot_historial_usuario
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4) Permisos RBAC
-- ---------------------------------------------------------------------------
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('ORDEN_TALLER_LEER', 'Consultar ordenes de taller y seguimiento', 1),
    ('ORDEN_TALLER_CREAR', 'Crear ordenes de taller y registrar anticipos', 1),
    ('ORDEN_TALLER_ACTUALIZAR', 'Editar ordenes, cambiar estado y registrar abonos', 1),
    ('ORDEN_TALLER_BORRAR', 'Dar de baja ordenes de taller', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) IN ('ADMINISTRADOR', 'EMPLEADO')
  AND p.nombre_permiso IN (
      'ORDEN_TALLER_LEER',
      'ORDEN_TALLER_CREAR',
      'ORDEN_TALLER_ACTUALIZAR'
  );

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) = 'ADMINISTRADOR'
  AND p.nombre_permiso = 'ORDEN_TALLER_BORRAR';
