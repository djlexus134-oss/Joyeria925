-- Claves SMTP para MailService (configuracion_general). Valores vacios: completar en admin o config.php.
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT v.clave, v.valor, v.tipo, v.descripcion, NOW()
FROM (
    SELECT 'smtp_host' AS clave, '' AS valor, 'STRING' AS tipo, 'Servidor SMTP (ej. smtp.gmail.com)' AS descripcion
    UNION ALL SELECT 'smtp_port', '587', 'INT', 'Puerto SMTP (587=tls, 465=ssl)'
    UNION ALL SELECT 'smtp_secure', 'tls', 'STRING', 'Cifrado: tls, ssl o none'
    UNION ALL SELECT 'smtp_username', '', 'STRING', 'Usuario SMTP (correo completo si es Gmail)'
    UNION ALL SELECT 'smtp_password', '', 'STRING', 'Contrasena de aplicacion SMTP'
    UNION ALL SELECT 'smtp_from_email', '', 'STRING', 'Correo remitente (From)'
    UNION ALL SELECT 'smtp_from_name', 'Plateria El Angel', 'STRING', 'Nombre remitente'
    UNION ALL SELECT 'smtp_debug', '0', 'INT', 'Debug PHPMailer (0=off, 2=verbose)'
    UNION ALL SELECT 'app_url', 'https://plateria-el-angel.shop', 'STRING', 'URL base para enlaces en correos'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = v.clave);
