-- Revoca PANEL_LEER del rol Empleado (dashboard con ventas/KPIs solo para gerencia).
-- Ejecutar despues de 2026_05_19_panel_leer_permiso.sql si ese script ya asigno PANEL_LEER a EMPLEADO.
--
-- Verificacion manual en el panel:
-- 1) Seguridad > Rol Permiso > rol Empleado: NO debe aparecer PANEL_LEER.
-- 2) Login empleado (sin PANEL_LEER): entra al primer modulo permitido, sin menu "Panel".
-- 3) Login administrador: ve admin/index.php con KPIs.
-- 4) GET admin/api/kpi_dashboard.php?section=summary con sesion empleado -> 403.

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

DELETE rp FROM rol_permiso rp
INNER JOIN roles r ON r.id_rol = rp.id_rol_FK
INNER JOIN permisos p ON p.id_permiso = rp.id_permiso_FK
WHERE UPPER(TRIM(r.nombre_rol)) = 'EMPLEADO'
  AND p.nombre_permiso = 'PANEL_LEER';
