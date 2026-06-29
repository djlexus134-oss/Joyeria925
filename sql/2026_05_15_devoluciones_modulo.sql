-- Modulo devoluciones: lineas anulables, devoluciones con/sin venta, reembolsos en venta_pagos (montos negativos)
-- Ejecutar en la base de datos de Joyeria.

-- Lineas de venta que pueden anularse por devolucion (sin borrar historial)
ALTER TABLE venta_detalle
    ADD COLUMN anulada TINYINT(1) NOT NULL DEFAULT 0
    AFTER cantidad;

-- Devoluciones: permitir mostrador sin venta; montos y forma de pago del reembolso
ALTER TABLE devoluciones
    MODIFY COLUMN id_venta_FK int(11) NULL;

ALTER TABLE devoluciones
    ADD COLUMN tipo_origen ENUM('venta', 'mostrador') NOT NULL DEFAULT 'venta' AFTER id_venta_FK,
    ADD COLUMN id_venta_detalle_FK int(11) NULL AFTER id_pieza_stock_FK,
    ADD COLUMN monto_reembolso decimal(10,2) NOT NULL DEFAULT 0.00 AFTER motivo,
    ADD COLUMN id_forma_pago_FK int(11) NULL AFTER monto_reembolso,
    ADD COLUMN observaciones varchar(255) NULL AFTER id_empleado_FK;

ALTER TABLE devoluciones
    ADD CONSTRAINT fk_devolucion_venta_detalle
        FOREIGN KEY (id_venta_detalle_FK) REFERENCES venta_detalle (id_venta_detalle)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE devoluciones
    ADD CONSTRAINT fk_devolucion_forma_pago
        FOREIGN KEY (id_forma_pago_FK) REFERENCES forma_pago (id_forma_pago)
        ON DELETE SET NULL ON UPDATE CASCADE;

CREATE INDEX idx_devoluciones_venta_pieza ON devoluciones (id_venta_FK, id_pieza_stock_FK);
CREATE INDEX idx_devoluciones_tipo ON devoluciones (tipo_origen, fecha_devolucion);

-- Permisos (asignar a roles desde el panel si no eres administrador)
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('DEVOLUCION_LEER', 'Consultar devoluciones y listados', 1),
    ('DEVOLUCION_CREAR', 'Registrar devoluciones (venta y mostrador)', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- Opcional: asignar permisos al rol ADMINISTRADOR (omitir si ya los tienes por otro medio)
INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'ADMINISTRADOR'
  AND p.nombre_permiso IN ('DEVOLUCION_LEER', 'DEVOLUCION_CREAR');
