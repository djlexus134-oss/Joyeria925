-- Extension cola_impresion para etiquetas de stock (Argox)
-- Idempotente: se puede ejecutar mas de una vez.

ALTER TABLE cola_impresion
    MODIFY id_venta_FK INT NULL;

ALTER TABLE cola_impresion
    MODIFY tipo ENUM('venta', 'reimpresion', 'etiqueta_stock', 'etiqueta_lote') NOT NULL DEFAULT 'venta';

ALTER TABLE cola_impresion
    ADD COLUMN IF NOT EXISTS id_usuario_FK INT NULL AFTER id_tienda_FK;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cola_impresion'
      AND INDEX_NAME = 'idx_cola_impresion_tipo_estado'
);
SET @sql_idx := IF(
    @idx_exists = 0,
    'ALTER TABLE cola_impresion ADD KEY idx_cola_impresion_tipo_estado (tipo, estado, fecha_creacion)',
    'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
VALUES
    ('etiqueta_impresion_habilitada', '1', 'BOOLEAN', 'Encolar etiquetas de piezas al solicitar impresion', NOW()),
    ('etiqueta_impresion_nombre_impresora', 'Argox OS-2140 PPLA', 'STRING', 'Nombre impresora Argox en Windows', NOW()),
    ('etiqueta_impresion_token', '', 'STRING', 'Token agente etiquetas (vacio = usar impresion_caja_token)', NOW()),
    ('etiqueta_ancho_mm', '90', 'INT', 'Ancho etiqueta en mm', NOW()),
    ('etiqueta_alto_mm', '12', 'INT', 'Alto etiqueta en mm', NOW()),
    ('etiqueta_dpi', '203', 'INT', 'DPI impresora termica etiquetas', NOW()),
    ('etiqueta_offset_x', '0', 'INT', 'Offset horizontal mm', NOW()),
    ('etiqueta_offset_y', '0', 'INT', 'Offset vertical mm', NOW()),
    ('etiqueta_lang', 'PPLA', 'STRING', 'Lenguaje de comandos PPLA o ZPL', NOW())
ON DUPLICATE KEY UPDATE fecha_actualizacion = NOW();
