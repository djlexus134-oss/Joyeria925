-- Apartados: cambio de pieza con credito no reembolsable (Opcion A + B del plan).
-- Reglas de negocio (v1):
--   - Solo apartados con estado 'activo' y exactamente UNA linea en apartado_detalle.
--   - El credito aplicable = SUM(apartado_pagos.monto) donde estado='registrado' y tipo_origen='cobro_tienda'.
--   - No se permite si el abono acumulado supera el precio del nuevo apartado (no hay saldo a favor global en v1).
--   - impuesto_monto del apartado nuevo se copia del origen (ajustar manualmente en politica fiscal si aplica).
--   - Penalizaciones / comisiones por cambio: no implementadas en v1 (usar observaciones o apartado_pagos tipo 'ajuste' en evolucion).
-- Ejecutar en la base de datos de Joyeria.

-- 1) Estado terminal para apartado sustituido por cambio de pieza
ALTER TABLE apartados
    MODIFY COLUMN estado ENUM ('activo', 'liquidado', 'cancelado', 'vencido', 'reemplazado')
        DEFAULT 'activo' NULL;

-- 2) Abonos: distinguir cobro en tienda vs credito trasladado desde otro apartado
ALTER TABLE apartado_pagos
    ADD COLUMN tipo_origen ENUM ('cobro_tienda', 'credito_por_cambio', 'ajuste') NOT NULL DEFAULT 'cobro_tienda' AFTER referencia,
    ADD COLUMN id_apartado_credito_origen_FK INT NULL AFTER tipo_origen;

ALTER TABLE apartado_pagos
    ADD CONSTRAINT fk_apartado_pago_credito_origen
        FOREIGN KEY (id_apartado_credito_origen_FK) REFERENCES apartados (id_apartado)
            ON DELETE SET NULL ON UPDATE CASCADE;

CREATE INDEX idx_apartado_pagos_tipo_origen ON apartado_pagos (tipo_origen, id_apartado_FK);

-- 3) Forma de pago sintetica para filas de credito (no implica movimiento de caja del dia)
INSERT INTO forma_pago (forma_pago, activo)
SELECT v.forma_pago, v.activo
FROM (SELECT 'Credito interno (cambio apartado)' AS forma_pago, 1 AS activo) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM forma_pago fp WHERE fp.forma_pago = v.forma_pago LIMIT 1
);

-- 4) Tabla puente: trazabilidad origen -> destino
CREATE TABLE apartado_cambios_pieza (
    id_apartado_cambio           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_apartado_origen_FK        INT NOT NULL,
    id_apartado_destino_FK       INT NOT NULL,
    id_pieza_stock_origen_FK     INT NOT NULL,
    id_pieza_stock_destino_FK    INT NOT NULL,
    monto_credito_aplicado       DECIMAL(10, 2) NOT NULL,
    id_pago_credito_FK           INT NULL COMMENT 'apartado_pagos del destino, tipo_origen=credito_por_cambio',
    observaciones                TEXT NULL,
    fecha_registro               TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    id_empleado_FK               INT NOT NULL,
    id_usuario_FK                INT NOT NULL,
    CONSTRAINT fk_cambio_apartado_origen
        FOREIGN KEY (id_apartado_origen_FK) REFERENCES apartados (id_apartado)
            ON UPDATE CASCADE,
    CONSTRAINT fk_cambio_apartado_destino
        FOREIGN KEY (id_apartado_destino_FK) REFERENCES apartados (id_apartado)
            ON UPDATE CASCADE,
    CONSTRAINT fk_cambio_pieza_origen
        FOREIGN KEY (id_pieza_stock_origen_FK) REFERENCES piezas_stock (id_pieza_stock)
            ON UPDATE CASCADE,
    CONSTRAINT fk_cambio_pieza_destino
        FOREIGN KEY (id_pieza_stock_destino_FK) REFERENCES piezas_stock (id_pieza_stock)
            ON UPDATE CASCADE,
    CONSTRAINT fk_cambio_pago_credito
        FOREIGN KEY (id_pago_credito_FK) REFERENCES apartado_pagos (id_pago)
            ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cambio_empleado
        FOREIGN KEY (id_empleado_FK) REFERENCES empleados (id_empleado)
            ON UPDATE CASCADE,
    CONSTRAINT fk_cambio_usuario
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario)
            ON UPDATE CASCADE,
    CONSTRAINT chk_cambio_monto_positivo
        CHECK (monto_credito_aplicado > 0)
) COLLATE = utf8mb4_0900_ai_ci;

CREATE INDEX idx_cambio_origen ON apartado_cambios_pieza (id_apartado_origen_FK);
CREATE INDEX idx_cambio_destino ON apartado_cambios_pieza (id_apartado_destino_FK);
CREATE INDEX idx_cambio_fecha ON apartado_cambios_pieza (fecha_registro);

-- 5) Permisos (asignar a roles desde el panel)
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('APARTADO_CAMBIO_LEER', 'Consultar cambios de pieza en apartados y vista previa', 1),
    ('APARTADO_CAMBIO_CREAR', 'Registrar cambio de pieza con credito no reembolsable', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);
