-- Snapshot de precio lista y promo aplicada en carrito online.
ALTER TABLE carrito_items
    ADD COLUMN IF NOT EXISTS precio_lista_snapshot DECIMAL(12,2) NULL AFTER precio_unitario_snapshot,
    ADD COLUMN IF NOT EXISTS id_promocion_FK INT NULL AFTER precio_lista_snapshot;
