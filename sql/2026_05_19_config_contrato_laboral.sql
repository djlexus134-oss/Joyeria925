-- Configuracion de contratos laborales (PDF). Ejecutar una sola vez.

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'contrato_ciudad', 'Ciudad, Estado', 'STRING', 'Ciudad donde se firma el contrato laboral.', NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'contrato_ciudad');

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'contrato_domicilio_fuente_trabajo', 'Domicilio de la fuente de trabajo', 'STRING', 'Domicilio de la fuente de trabajo en el contrato.', NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'contrato_domicilio_fuente_trabajo');

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'contrato_nombre_patron', 'Nombre del patrón', 'STRING', 'Nombre del patrón que firma el contrato.', NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'contrato_nombre_patron');

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'contrato_tribunal_ciudad', 'Ciudad de los tribunales', 'STRING', 'Ciudad de los tribunales laborales (cláusula 14).', NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'contrato_tribunal_ciudad');

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'contrato_jornada_horas_semanales', '48', 'INT', 'Horas de la jornada semanal en el contrato.', NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'contrato_jornada_horas_semanales');

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'contrato_nacionalidad_default', 'Mexicana', 'STRING', 'Nacionalidad por defecto si el empleado no tiene país registrado.', NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'contrato_nacionalidad_default');
