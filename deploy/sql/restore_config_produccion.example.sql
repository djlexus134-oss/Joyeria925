-- Restaurar configuracion de PRODUCCION despues del import del dump.
-- NO uses INSERT con ids (da ERROR 1062). Solo UPDATE por clave.
--
-- 1) Copia este archivo en el VPS:
--    cp deploy/sql/restore_config_produccion.example.sql /root/restore_config.sql
-- 2) Edita /root/restore_config.sql con tus valores reales
-- 3) mariadb -u joyeria_app -p joyeria < /root/restore_config.sql

SET NAMES utf8mb4;

UPDATE configuracion_general
SET valor = 'PONER_TOKEN_IMPRESION_AQUI', fecha_actualizacion = NOW()
WHERE clave = 'impresion_caja_token';

UPDATE configuracion_general
SET valor = '', fecha_actualizacion = NOW()
WHERE clave = 'etiqueta_impresion_token';

-- Opcional: ticket / impresoras (ajusta si hace falta)
-- UPDATE configuracion_general SET valor = 'Plateria el Angel', fecha_actualizacion = NOW() WHERE clave = 'ticket_nombre_comercial';
-- UPDATE configuracion_general SET valor = 'EPSON TM-T20 Receipt', fecha_actualizacion = NOW() WHERE clave = 'impresion_nombre_impresora';
-- UPDATE configuracion_general SET valor = 'Argox OS-2140 PPLA', fecha_actualizacion = NOW() WHERE clave = 'etiqueta_impresion_nombre_impresora';

SELECT clave, valor FROM configuracion_general
WHERE clave IN ('impresion_caja_token', 'etiqueta_impresion_token', 'impresion_habilitada', 'etiqueta_impresion_habilitada');
