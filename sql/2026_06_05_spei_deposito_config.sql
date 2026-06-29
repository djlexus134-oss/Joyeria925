-- Configuracion de deposito SPEI / transferencia para QR informativo en POS.
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT v.clave, v.valor, v.tipo, v.descripcion, NOW()
FROM (
    SELECT 'spei_deposito_habilitado' AS clave, '0' AS valor, 'BOOLEAN' AS tipo, 'Muestra QR de datos bancarios en punto de venta (1=si, 0=no)' AS descripcion
    UNION ALL SELECT 'spei_beneficiario', '', 'STRING', 'Nombre del titular de la cuenta para depositos SPEI'
    UNION ALL SELECT 'spei_banco', '', 'STRING', 'Nombre del banco receptor (ej. BBVA Mexico)'
    UNION ALL SELECT 'spei_clabe', '', 'STRING', 'CLABE interbancaria de 18 digitos para depositos'
    UNION ALL SELECT 'spei_instrucciones', '', 'STRING', 'Instrucciones opcionales para el cliente al transferir'
    UNION ALL SELECT 'spei_referencia_prefijo', 'VENTA', 'STRING', 'Prefijo del concepto/referencia en el QR de transferencia'
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_general cg WHERE cg.clave = v.clave
);
