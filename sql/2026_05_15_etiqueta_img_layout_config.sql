-- Acomodo etiqueta PNG (modo IMAGEN): desplazamientos, margenes y tamanos de texto.
-- ON DUPLICATE KEY: actualiza si la clave ya existe (clave unica en configuracion_general).

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
VALUES
    ('etiqueta_img_shift_barcode_mm', '4', 'STRING', 'PNG: desplazar barcode+aux mm', NOW()),
    ('etiqueta_img_shift_precio_mm', '6', 'STRING', 'PNG: desplazar precio mm', NOW()),
    ('etiqueta_img_margen_izq_barcode_mm', '2.5', 'STRING', 'PNG: margen izq barcode mm', NOW()),
    ('etiqueta_img_margen_der_barcode_mm', '1', 'STRING', 'PNG: margen der barcode mm', NOW()),
    ('etiqueta_img_gap_barcode_texto_mm', '0.3', 'STRING', 'PNG: gap barcode-texto mm', NOW()),
    ('etiqueta_img_margen_inferior_aux_mm', '1.5', 'STRING', 'PNG: margen inferior aux mm', NOW()),
    ('etiqueta_img_alto_barcode_ratio', '0.72', 'STRING', 'PNG: alto barcode / alto etiqueta', NOW()),
    ('etiqueta_img_tam_aux_pt', '11', 'INT', 'PNG: tamano texto aux pt', NOW()),
    ('etiqueta_img_tam_precio_pt', '24', 'INT', 'PNG: tamano precio pt', NOW()),
    ('etiqueta_img_precio_baseline_factor', '0.30', 'STRING', 'PNG: factor vertical precio', NOW())
ON DUPLICATE KEY UPDATE
    valor = VALUES(valor),
    tipo = VALUES(tipo),
    descripcion = VALUES(descripcion),
    fecha_actualizacion = NOW();
