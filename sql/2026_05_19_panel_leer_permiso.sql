-- Permiso de acceso al panel de inicio (admin/index.php, KPIs de ventas/ganancias).
-- Ejecutar una sola vez sobre la misma BD que usa la app.
--
-- IMPORTANTE: PANEL_LEER es solo para administracion/gerencia, NO para el rol Empleado.
-- Si ya asignaste PANEL_LEER a Empleado, ejecuta tambien:
--   sql/2026_05_19_panel_leer_empleado_revoke.sql
--
-- Checklist operativo (panel web):
-- 1) Seguridad > Usuario Rol: cada empleado debe tener un rol (ej. Empleado).
-- 2) usuarios.activo = 1 para la cuenta que inicia sesion.
-- 3) Seguridad > Rol Permiso > Empleado: permisos operativos (*_LEER de modulos que use).
--    NO incluir PANEL_LEER en el rol Empleado.
-- 4) Quitar USUARIO_LEER del rol si el empleado no debe administrar cuentas.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('PANEL_LEER', 'Permite LEER en PANEL (inicio del admin, KPIs)', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) IN ('ADMINISTRADOR', 'ADMIN')
  AND p.nombre_permiso = 'PANEL_LEER';
