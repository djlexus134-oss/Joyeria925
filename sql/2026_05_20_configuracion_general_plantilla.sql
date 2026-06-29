-- Limpieza de claves duplicadas y plantilla definitiva en configuracion_general.
-- Ejecutar una sola vez en el entorno donde existan filas repetidas.

-- 1) Conservar solo el registro mas reciente por clave (mayor id).
DELETE cg1
FROM configuracion_general cg1
INNER JOIN configuracion_general cg2
    ON cg1.clave = cg2.clave
    AND cg1.id_configuracion_global < cg2.id_configuracion_global;

-- 2) Indice unico por clave (omitir si ya existe uk_configuracion_general_clave).
ALTER TABLE configuracion_general
    ADD UNIQUE KEY uk_configuracion_general_clave (clave);

-- 3) Plantilla: insertar claves faltantes con tipo y descripcion canonicos.
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT v.clave, v.valor, v.tipo, v.descripcion, NOW()
FROM (
    SELECT 'tipo_codigo_barras_default' AS clave, 'QR' AS valor, 'STRING' AS tipo, 'Tipo de codigo de barras por defecto para piezas_stock' AS descripcion
    UNION ALL SELECT 'id_tienda_default', '1', 'INT', 'ID de la tienda por defecto para nuevas piezas'
    UNION ALL SELECT 'markup_pct_default', '220', 'DECIMAL', 'Porcentaje de margen al generar stock inicial de piezas'
    UNION ALL SELECT 'descuento_general_mostrador', '30', 'DECIMAL', 'Descuento general en punto de venta sin descuento de cliente'
    UNION ALL SELECT 'id_forma_pago_default', CAST(COALESCE((SELECT MIN(fp.id_forma_pago) FROM forma_pago fp WHERE fp.activo = 1), 0) AS CHAR), 'INT', 'Forma de pago preseleccionada en formularios'
    UNION ALL SELECT 'id_impuesto_default', CAST(COALESCE((SELECT MIN(i.id_impuesto) FROM impuestos i), 0) AS CHAR), 'INT', 'Impuesto preseleccionado en formularios'
    UNION ALL SELECT 'ticket_nombre_comercial', 'Plateria el Angel', 'STRING', 'Nombre comercial en encabezado del ticket'
    UNION ALL SELECT 'ticket_leyenda_folio', 'Folio', 'STRING', 'Leyenda mostrada arriba del numero de venta'
    UNION ALL SELECT 'ticket_horario', 'Horario: 1:30 PM - 7:00 PM', 'STRING', 'Horario de atencion en el ticket'
    UNION ALL SELECT 'ticket_mensaje_pie', 'Gracias por su preferencia', 'STRING', 'Mensaje al pie del ticket'
    UNION ALL SELECT 'ticket_ancho_columnas', '42', 'INT', 'Ancho en caracteres para impresora 80mm'
    UNION ALL SELECT 'ticket_margen_izquierdo', '60', 'INT', 'Margen izquierdo en puntos ESC/POS'
    UNION ALL SELECT 'ticket_mostrar_impuesto', '1', 'BOOLEAN', 'Mostrar desglose de impuesto en ticket'
    UNION ALL SELECT 'ticket_mostrar_empleado', '1', 'BOOLEAN', 'Mostrar nombre del empleado en ticket'
    UNION ALL SELECT 'impresion_habilitada', '1', 'BOOLEAN', 'Encolar tickets al confirmar venta'
    UNION ALL SELECT 'impresion_caja_token', 'cambiar_token_seguro', 'STRING', 'Token secreto agente impresion (header X-Caja-Token)'
    UNION ALL SELECT 'impresion_nombre_impresora', 'EPSON TM-T20 Receipt', 'STRING', 'Nombre de impresora de tickets en Windows'
    UNION ALL SELECT 'impresion_id_tienda_caja', '0', 'INT', 'ID tienda que atiende esta caja (0 = todas)'
    UNION ALL SELECT 'etiqueta_impresion_habilitada', '1', 'BOOLEAN', 'Encolar etiquetas de piezas al solicitar impresion'
    UNION ALL SELECT 'etiqueta_impresion_nombre_impresora', 'Argox OS-2140 PPLA', 'STRING', 'Nombre impresora Argox en Windows'
    UNION ALL SELECT 'etiqueta_impresion_token', '', 'STRING', 'Token agente etiquetas (vacio = usar impresion_caja_token)'
    UNION ALL SELECT 'etiqueta_ancho_mm', '60', 'INT', 'Largo total de etiqueta en mm'
    UNION ALL SELECT 'etiqueta_alto_mm', '10', 'INT', 'Alto de etiqueta en mm'
    UNION ALL SELECT 'etiqueta_gap_mm', '3', 'INT', 'Salto longitudinal entre etiquetas en mm'
    UNION ALL SELECT 'etiqueta_ala_mm', '17', 'INT', 'Ancho cabeza izquierda mm (precio)'
    UNION ALL SELECT 'etiqueta_media_mm', '17', 'INT', 'Zona media ancha mm (codigo de barras)'
    UNION ALL SELECT 'etiqueta_cola_mm', '26', 'INT', 'Largo cola estrecha mm (auxiliar)'
    UNION ALL SELECT 'etiqueta_alto_cola_mm', '5', 'INT', 'Alto cola estrecha mm'
    UNION ALL SELECT 'etiqueta_dpi', '203', 'INT', 'DPI impresora termica de etiquetas'
    UNION ALL SELECT 'etiqueta_offset_x', '0', 'INT', 'Offset horizontal mm'
    UNION ALL SELECT 'etiqueta_offset_y', '0', 'INT', 'Offset vertical mm'
    UNION ALL SELECT 'etiqueta_lang', 'IMAGEN', 'STRING', 'Modo: IMAGEN (PNG), PPLA (RAW) o ZPL'
    UNION ALL SELECT 'etiqueta_img_shift_barcode_mm', '4', 'STRING', 'PNG: desplazar barcode+aux mm'
    UNION ALL SELECT 'etiqueta_img_shift_precio_mm', '6', 'STRING', 'PNG: desplazar precio mm'
    UNION ALL SELECT 'etiqueta_img_margen_izq_barcode_mm', '2.5', 'STRING', 'PNG: margen izq barcode mm'
    UNION ALL SELECT 'etiqueta_img_margen_der_barcode_mm', '1', 'STRING', 'PNG: margen der barcode mm'
    UNION ALL SELECT 'etiqueta_img_gap_barcode_texto_mm', '0.3', 'STRING', 'PNG: gap barcode-texto mm'
    UNION ALL SELECT 'etiqueta_img_margen_inferior_aux_mm', '1.5', 'STRING', 'PNG: margen inferior aux mm'
    UNION ALL SELECT 'etiqueta_img_alto_barcode_ratio', '0.72', 'STRING', 'PNG: alto barcode / alto etiqueta'
    UNION ALL SELECT 'etiqueta_img_tam_aux_pt', '11', 'INT', 'PNG: tamano texto aux pt'
    UNION ALL SELECT 'etiqueta_img_tam_precio_pt', '24', 'INT', 'PNG: tamano precio pt'
    UNION ALL SELECT 'etiqueta_img_precio_baseline_factor', '0.30', 'STRING', 'PNG: factor vertical precio'
    UNION ALL SELECT 'contrato_ciudad', 'Celaya, Gto.', 'STRING', 'Ciudad donde se firma el contrato laboral'
    UNION ALL SELECT 'contrato_domicilio_fuente_trabajo', 'Andador Nicolas Bravo No. 104 Celaya, Gto.', 'STRING', 'Domicilio de la fuente de trabajo en el contrato'
    UNION ALL SELECT 'contrato_nombre_patron', 'BEATRIZ MARTHA HERNANDEZ ALVARADO', 'STRING', 'Nombre del patron que firma el contrato'
    UNION ALL SELECT 'contrato_tribunal_ciudad', 'Guanajuato, Guanajuato', 'STRING', 'Ciudad de los tribunales laborales (clausula 14)'
    UNION ALL SELECT 'contrato_jornada_horas_semanales', '48', 'INT', 'Horas de la jornada semanal en el contrato'
    UNION ALL SELECT 'contrato_nacionalidad_default', 'Mexicana', 'STRING', 'Nacionalidad por defecto si el empleado no tiene pais registrado'
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_general cg WHERE cg.clave = v.clave
);

-- 4) Normalizar tipo de etiqueta_gap_mm si quedo como STRING por migraciones antiguas.
UPDATE configuracion_general
SET tipo = 'INT',
    descripcion = 'Salto longitudinal entre etiquetas en mm',
    fecha_actualizacion = NOW()
WHERE clave = 'etiqueta_gap_mm' AND tipo <> 'INT';
