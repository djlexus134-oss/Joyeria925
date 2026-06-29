-- Cambio de pieza: soportar reemplazo en el mismo apartado (multilinea).
-- Ejecutar despues de 2026_05_15_apartado_cambio_pieza_credito.sql

ALTER TABLE apartado_cambios_pieza
    ADD COLUMN tipo_operacion ENUM ('nuevo_apartado', 'reemplazo_mismo') NOT NULL DEFAULT 'nuevo_apartado' AFTER monto_credito_aplicado,
    ADD COLUMN id_apartado_detalle_FK INT NULL AFTER tipo_operacion;

ALTER TABLE apartado_cambios_pieza
    ADD CONSTRAINT fk_cambio_apartado_detalle
        FOREIGN KEY (id_apartado_detalle_FK) REFERENCES apartado_detalle (id_apartado_detalle)
            ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE apartado_cambios_pieza
    DROP CHECK chk_cambio_monto_positivo;

ALTER TABLE apartado_cambios_pieza
    ADD CONSTRAINT chk_cambio_monto_nonneg CHECK (monto_credito_aplicado >= 0);
