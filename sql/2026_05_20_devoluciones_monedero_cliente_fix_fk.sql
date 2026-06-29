-- Reparacion si fallo errno 150 al crear FKs (columna id_devolucion_origen_FK como UNSIGNED).
-- Ejecutar solo si ya corriste el script principal y el ALTER de CONSTRAINT fallo.

-- 1) Alinear tipos con tablas referenciadas (int signed, como id_devolucion / id_venta)
ALTER TABLE cliente_creditos
    MODIFY COLUMN id_devolucion_origen_FK INT NULL,
    MODIFY COLUMN id_venta_origen_FK INT NULL;

-- 2) FKs en cliente_creditos (omitir si ya existen)
ALTER TABLE cliente_creditos
    ADD CONSTRAINT fk_cliente_credito_devolucion
        FOREIGN KEY (id_devolucion_origen_FK) REFERENCES devoluciones (id_devolucion)
            ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE cliente_creditos
    ADD CONSTRAINT fk_cliente_credito_venta_origen
        FOREIGN KEY (id_venta_origen_FK) REFERENCES ventas (id_venta)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- Si el indice ya existe, ignorar error 1061 (Duplicate key name)
CREATE INDEX idx_cliente_credito_cliente_estado_tipo
    ON cliente_creditos (id_cliente_FK, estado, tipo);

-- 3) Columnas en devoluciones (omitir si ya existen)
ALTER TABLE devoluciones
    ADD COLUMN id_cliente_FK INT NULL COMMENT 'Cliente acreditado en monedero' AFTER id_empleado_FK,
    ADD COLUMN id_credito_FK INT UNSIGNED NULL COMMENT 'Credito generado en cliente_creditos' AFTER id_cliente_FK;

ALTER TABLE devoluciones
    ADD CONSTRAINT fk_devolucion_cliente
        FOREIGN KEY (id_cliente_FK) REFERENCES clientes (id_cliente)
            ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE devoluciones
    ADD CONSTRAINT fk_devolucion_credito
        FOREIGN KEY (id_credito_FK) REFERENCES cliente_creditos (id_credito)
            ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO forma_pago (forma_pago, activo)
SELECT 'Credito monedero por devolucion (sin efectivo)', 1
FROM (SELECT 1 AS _x) t
WHERE NOT EXISTS (
    SELECT 1 FROM forma_pago
    WHERE forma_pago = 'Credito monedero por devolucion (sin efectivo)' LIMIT 1
);

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('DEVOLUCION_CREDITO_MONEDERO', 'Registrar devolucion que acredita el monedero del cliente', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'ADMINISTRADOR'
  AND p.nombre_permiso = 'DEVOLUCION_CREDITO_MONEDERO';
