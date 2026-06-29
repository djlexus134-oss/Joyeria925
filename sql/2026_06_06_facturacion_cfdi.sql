-- =====================================================================
-- Migracion: Facturacion CFDI 4.0 (Facturama) + datos fiscales
-- Fecha: 2026-06-06
-- Sistema: Joyeria Plateria El Angel
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) clientes: datos fiscales del receptor
-- ---------------------------------------------------------------------
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS rfc VARCHAR(13) NULL AFTER descuento_porcentaje,
    ADD COLUMN IF NOT EXISTS razon_social VARCHAR(254) NULL AFTER rfc,
    ADD COLUMN IF NOT EXISTS regimen_fiscal VARCHAR(3) NULL AFTER razon_social,
    ADD COLUMN IF NOT EXISTS uso_cfdi VARCHAR(5) NULL DEFAULT 'G03' AFTER regimen_fiscal,
    ADD COLUMN IF NOT EXISTS codigo_postal_fiscal VARCHAR(5) NULL AFTER uso_cfdi;

-- ---------------------------------------------------------------------
-- 2) forma_pago: clave SAT c_FormaPago
-- ---------------------------------------------------------------------
ALTER TABLE forma_pago
    ADD COLUMN IF NOT EXISTS clave_sat VARCHAR(2) NULL AFTER es_efectivo;

UPDATE forma_pago SET clave_sat = '01'
WHERE activo = 1 AND clave_sat IS NULL
  AND (LOWER(forma_pago) LIKE '%efectivo%' OR es_efectivo = 1);

UPDATE forma_pago SET clave_sat = '04'
WHERE activo = 1 AND clave_sat IS NULL
  AND (LOWER(forma_pago) LIKE '%tarjeta%' OR LOWER(forma_pago) LIKE '%debito%' OR LOWER(forma_pago) LIKE '%credito%');

UPDATE forma_pago SET clave_sat = '03'
WHERE activo = 1 AND clave_sat IS NULL
  AND LOWER(forma_pago) LIKE '%transfer%';

UPDATE forma_pago SET clave_sat = '28'
WHERE activo = 1 AND clave_sat IS NULL
  AND (LOWER(forma_pago) LIKE '%monedero%' OR LOWER(forma_pago) LIKE '%credito a favor%' OR LOWER(forma_pago) LIKE '%credito cliente%');

UPDATE forma_pago SET clave_sat = '99'
WHERE activo = 1 AND clave_sat IS NULL;

-- ---------------------------------------------------------------------
-- 3) familias e insumos: clave producto/servicio SAT
-- ---------------------------------------------------------------------
ALTER TABLE familias
    ADD COLUMN IF NOT EXISTS clave_prod_serv VARCHAR(8) NULL DEFAULT '42181500' AFTER nom_familia;

ALTER TABLE insumos
    ADD COLUMN IF NOT EXISTS clave_prod_serv VARCHAR(8) NULL DEFAULT '53131600' AFTER nombre;

-- ---------------------------------------------------------------------
-- 4) factura_detalle: campos CFDI 4.0
-- ---------------------------------------------------------------------
ALTER TABLE factura_detalle
    ADD COLUMN IF NOT EXISTS clave_prod_serv VARCHAR(8) NOT NULL DEFAULT '42181500' AFTER id_factura_FK,
    ADD COLUMN IF NOT EXISTS clave_unidad VARCHAR(4) NOT NULL DEFAULT 'H87' AFTER clave_prod_serv,
    ADD COLUMN IF NOT EXISTS objeto_imp CHAR(2) NOT NULL DEFAULT '02' AFTER clave_unidad,
    ADD COLUMN IF NOT EXISTS tasa_iva DECIMAL(5,2) NULL AFTER importe,
    ADD COLUMN IF NOT EXISTS base_iva DECIMAL(10,2) NULL AFTER tasa_iva,
    ADD COLUMN IF NOT EXISTS importe_iva DECIMAL(10,2) NULL AFTER base_iva;

-- ---------------------------------------------------------------------
-- 5) facturas: envio, errores, apartado, idempotencia
-- ---------------------------------------------------------------------
ALTER TABLE facturas
    MODIFY COLUMN estado ENUM('pendiente','emitida','error','cancelada') NOT NULL DEFAULT 'pendiente';

ALTER TABLE facturas
    MODIFY COLUMN xml LONGTEXT NULL;

ALTER TABLE facturas
    ADD COLUMN IF NOT EXISTS envio_correo_estado ENUM('pendiente','enviado','omitido','error') NOT NULL DEFAULT 'pendiente' AFTER pdf,
    ADD COLUMN IF NOT EXISTS envio_correo_fecha DATETIME NULL AFTER envio_correo_estado,
    ADD COLUMN IF NOT EXISTS envio_whatsapp_estado ENUM('pendiente','enviado','omitido','error') NOT NULL DEFAULT 'pendiente' AFTER envio_correo_fecha,
    ADD COLUMN IF NOT EXISTS envio_whatsapp_fecha DATETIME NULL AFTER envio_whatsapp_estado,
    ADD COLUMN IF NOT EXISTS error_timbrado TEXT NULL AFTER envio_whatsapp_fecha,
    ADD COLUMN IF NOT EXISTS respuesta_pac JSON NULL AFTER error_timbrado,
    ADD COLUMN IF NOT EXISTS id_apartado_FK INT NULL AFTER id_venta_FK,
    ADD COLUMN IF NOT EXISTS id_facturama VARCHAR(80) NULL AFTER respuesta_pac;

SET @fk_fact_ap := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facturas'
      AND CONSTRAINT_NAME = 'fk_facturas_apartado'
);
SET @sql_fk_fact_ap := IF(@fk_fact_ap = 0,
    'ALTER TABLE facturas ADD CONSTRAINT fk_facturas_apartado FOREIGN KEY (id_apartado_FK) REFERENCES apartados(id_apartado)',
    'SELECT 1');
PREPARE stmt_fk_fact_ap FROM @sql_fk_fact_ap;
EXECUTE stmt_fk_fact_ap;
DEALLOCATE PREPARE stmt_fk_fact_ap;

SET @uq_fact_venta := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facturas'
      AND CONSTRAINT_NAME = 'uq_facturas_venta'
);
SET @sql_uq_fact_venta := IF(@uq_fact_venta = 0,
    'ALTER TABLE facturas ADD UNIQUE KEY uq_facturas_venta (id_venta_FK)',
    'SELECT 1');
PREPARE stmt_uq_fact_venta FROM @sql_uq_fact_venta;
EXECUTE stmt_uq_fact_venta;
DEALLOCATE PREPARE stmt_uq_fact_venta;

-- ---------------------------------------------------------------------
-- 6) factura_pagos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS factura_pagos (
    id_factura_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_factura_FK INT NOT NULL,
    id_forma_pago_FK INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    clave_sat VARCHAR(2) NOT NULL,
    CONSTRAINT fk_factura_pago_factura FOREIGN KEY (id_factura_FK) REFERENCES facturas(id_factura) ON DELETE CASCADE,
    CONSTRAINT fk_factura_pago_forma FOREIGN KEY (id_forma_pago_FK) REFERENCES forma_pago(id_forma_pago),
    INDEX idx_factura_pagos_factura (id_factura_FK)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) configuracion_general
-- ---------------------------------------------------------------------
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT v.clave, v.valor, v.tipo, v.descripcion, NOW()
FROM (
    SELECT 'facturacion_habilitada' AS clave, '0' AS valor, 'BOOLEAN' AS tipo, 'Emite CFDI automaticamente al completar ventas' AS descripcion
    UNION ALL SELECT 'facturama_api_url', 'https://apisandbox.facturama.mx', 'STRING', 'URL base API Facturama'
    UNION ALL SELECT 'facturama_modo', 'sandbox', 'STRING', 'sandbox o produccion'
    UNION ALL SELECT 'cfdi_rfc_emisor', '', 'STRING', 'RFC del emisor en Facturama'
    UNION ALL SELECT 'cfdi_nombre_emisor', '', 'STRING', 'Razon social del emisor'
    UNION ALL SELECT 'cfdi_regimen_fiscal', '601', 'STRING', 'Regimen fiscal emisor'
    UNION ALL SELECT 'cfdi_lugar_expedicion', '', 'STRING', 'CP expedicion (5 digitos)'
    UNION ALL SELECT 'cfdi_serie', 'A', 'STRING', 'Serie CFDI'
    UNION ALL SELECT 'cfdi_siguiente_folio', '1', 'INT', 'Proximo folio numerico'
    UNION ALL SELECT 'cfdi_clave_unidad_default', 'H87', 'STRING', 'Clave unidad SAT default'
    UNION ALL SELECT 'cfdi_clave_prod_serv_insumo_default', '53131600', 'STRING', 'Clave prod/serv insumos'
    UNION ALL SELECT 'cfdi_forma_pago_online_default', '03', 'STRING', 'Forma pago ventas online'
    UNION ALL SELECT 'whatsapp_template_factura', '', 'STRING', 'Plantilla WhatsApp factura'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = v.clave);
