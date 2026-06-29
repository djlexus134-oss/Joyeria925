-- Claves de WhatsApp Cloud API (Meta) para WhatsAppService (configuracion_general).
-- El TOKEN de acceso NO se guarda aqui: definelo como constante JOYERIA_WHATSAPP_TOKEN
-- en config.php (es secreto y puede superar el limite de 255 chars de la columna valor).
--
-- Antes de funcionar, en Meta Business Manager se deben crear y APROBAR las plantillas:
--   * Bienvenida cliente  (1 variable de cuerpo: nombre)
--   * Bienvenida empleado (1 variable de cuerpo: nombre)
--   * Notificacion        (1 variable de cuerpo: mensaje)
-- y poner sus nombres exactos en las claves whatsapp_template_*.
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT v.clave, v.valor, v.tipo, v.descripcion, NOW()
FROM (
    SELECT 'whatsapp_habilitado' AS clave, '0' AS valor, 'BOOLEAN' AS tipo, 'Activa el envio por WhatsApp (1=si, 0=no)' AS descripcion
    UNION ALL SELECT 'whatsapp_phone_number_id', '', 'STRING', 'Phone Number ID de WhatsApp Cloud API (Meta)'
    UNION ALL SELECT 'whatsapp_api_version', 'v20.0', 'STRING', 'Version de Graph API (ej. v20.0)'
    UNION ALL SELECT 'whatsapp_codigo_pais_default', '52', 'STRING', 'Lada por defecto si el telefono no la trae (52=Mexico)'
    UNION ALL SELECT 'whatsapp_template_idioma', 'es_MX', 'STRING', 'Codigo de idioma de las plantillas (ej. es_MX, es)'
    UNION ALL SELECT 'whatsapp_template_bienvenida_cliente', '', 'STRING', 'Nombre de la plantilla de bienvenida para clientes'
    UNION ALL SELECT 'whatsapp_template_bienvenida_empleado', '', 'STRING', 'Nombre de la plantilla de bienvenida para empleados'
    UNION ALL SELECT 'whatsapp_template_notificacion', '', 'STRING', 'Nombre de la plantilla para notificaciones especiales'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = v.clave);
