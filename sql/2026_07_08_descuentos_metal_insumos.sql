-- Descuentos por metal (POS/tienda), promocion cantidad en insumos y alcance metal en promociones.
-- Ejecutar una sola vez sobre la BD de la joyeria.

-- ---------------------------------------------------------------------------
-- 1) Metales: reglas de descuento en mostrador
-- ---------------------------------------------------------------------------
ALTER TABLE metales ADD COLUMN IF NOT EXISTS descuento_mostrador_pct DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE metales ADD COLUMN IF NOT EXISTS aplica_mayoreo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE metales ADD COLUMN IF NOT EXISTS umbral_piezas_descuento INT NULL DEFAULT NULL;
ALTER TABLE metales ADD COLUMN IF NOT EXISTS descuento_umbral_pct DECIMAL(5,2) NULL DEFAULT NULL;

-- Plata: 30% base + mayoreo
UPDATE metales
SET descuento_mostrador_pct = 30.00,
    aplica_mayoreo = 1,
    umbral_piezas_descuento = NULL,
    descuento_umbral_pct = NULL
WHERE activo = 1
  AND (LOWER(nom_metal) LIKE '%plata%' OR LOWER(nom_metal) LIKE '%.925%');

-- Oro: sin descuento base; regla 6 piezas (ajustar % en admin si aplica)
UPDATE metales
SET descuento_mostrador_pct = 0.00,
    aplica_mayoreo = 0,
    umbral_piezas_descuento = 6,
    descuento_umbral_pct = 10.00
WHERE activo = 1
  AND LOWER(nom_metal) LIKE '%oro%'
  AND LOWER(nom_metal) NOT LIKE '%plata%';

-- ---------------------------------------------------------------------------
-- 2) Insumos: promocion lleva N paga M (ej. lleva 6 paga 5)
-- ---------------------------------------------------------------------------
ALTER TABLE insumos ADD COLUMN IF NOT EXISTS promo_paga_unidades INT NULL DEFAULT NULL;
ALTER TABLE insumos ADD COLUMN IF NOT EXISTS promo_lleva_unidades INT NULL DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3) Promociones: alcance por metal (tienda en linea)
-- ---------------------------------------------------------------------------
ALTER TABLE promociones ADD COLUMN IF NOT EXISTS id_metal_FK INT NULL DEFAULT NULL;
ALTER TABLE promociones ADD KEY IF NOT EXISTS idx_promociones_metal (id_metal_FK);
