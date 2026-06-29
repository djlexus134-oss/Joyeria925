-- Devoluciones con credito al monedero del cliente (tipo devolucion en cliente_creditos).
-- Ejecutar en la base de datos de Joyeria.

-- Tipos INT (signed), igual que devoluciones.id_devolucion y ventas.id_venta (no UNSIGNED).
ALTER TABLE cliente_creditos
    ADD COLUMN id_devolucion_origen_FK INT NULL COMMENT 'Devolucion que genero el credito' AFTER id_apartado_origen_FK,
    ADD COLUMN id_venta_origen_FK INT NULL COMMENT 'Venta origen cuando el credito proviene de ticket' AFTER id_devolucion_origen_FK;

ALTER TABLE cliente_creditos
    ADD CONSTRAINT fk_cliente_credito_devolucion
        FOREIGN KEY (id_devolucion_origen_FK) REFERENCES devoluciones (id_devolucion)
            ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_cliente_credito_venta_origen
        FOREIGN KEY (id_venta_origen_FK) REFERENCES ventas (id_venta)
            ON DELETE SET NULL ON UPDATE CASCADE;

CREATE INDEX idx_cliente_credito_cliente_estado_tipo
    ON cliente_creditos (id_cliente_FK, estado, tipo);

ALTER TABLE devoluciones
    ADD COLUMN id_cliente_FK INT NULL COMMENT 'Cliente acreditado en monedero' AFTER id_empleado_FK,
    ADD COLUMN id_credito_FK INT UNSIGNED NULL COMMENT 'Credito generado en cliente_creditos' AFTER id_cliente_FK;

ALTER TABLE devoluciones
    ADD CONSTRAINT fk_devolucion_cliente
        FOREIGN KEY (id_cliente_FK) REFERENCES clientes (id_cliente)
            ON DELETE SET NULL ON UPDATE CASCADE,
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
