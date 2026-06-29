-- Modulo cierre de caja: efectivo teorico por dia, tabla de cierres y marca es_efectivo en forma_pago.
-- Ejecutar una sola vez sobre la misma BD que usa la app.

-- ---------------------------------------------------------------------------
-- 1) forma_pago: marcar cuales cuentan como efectivo fisico en caja
-- ---------------------------------------------------------------------------
ALTER TABLE forma_pago
    ADD COLUMN es_efectivo TINYINT(1) NOT NULL DEFAULT 0
    AFTER activo;

UPDATE forma_pago
SET es_efectivo = 1
WHERE activo = 1
  AND LOWER(TRIM(forma_pago)) LIKE '%efectivo%';

-- ---------------------------------------------------------------------------
-- 2) Tabla cierre_caja (un registro auditable por fecha de operacion)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cierre_caja (
    id_cierre_caja INT NOT NULL AUTO_INCREMENT,
    fecha_operacion DATE NOT NULL,
    efectivo_esperado DECIMAL(12,2) NOT NULL,
    efectivo_contado DECIMAL(12,2) NULL DEFAULT NULL,
    diferencia DECIMAL(12,2) NULL DEFAULT NULL,
    observaciones VARCHAR(500) NULL DEFAULT NULL,
    resumen_json JSON NULL DEFAULT NULL,
    id_usuario_FK INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cierre_caja),
    UNIQUE KEY uq_cierre_caja_fecha (fecha_operacion),
    KEY idx_cierre_caja_usuario (id_usuario_FK),
    CONSTRAINT fk_cierre_caja_usuario
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3) Permisos RBAC
-- ---------------------------------------------------------------------------
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('CIERRE_CAJA_LEER', 'Consultar cierres de caja y previsualizar totales', 1),
    ('CIERRE_CAJA_CREAR', 'Registrar cierre de caja del dia', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);
