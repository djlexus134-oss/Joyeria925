-- Variante "Medida" para cadenas y otras piezas con longitud variable.
-- es_talla=0 -> disponible para todas las familias (no requiere usa_talla=1).
-- Idempotente: ejecutar una sola vez.

-- ---------------------------------------------------------------------------
-- 1) Tipo "Medida"
-- ---------------------------------------------------------------------------
INSERT INTO variante_tipos (nombre, slug, es_talla, orden, activo)
VALUES ('Medida', 'medida', 0, 3, 1)
ON DUPLICATE KEY UPDATE
    nombre  = VALUES(nombre),
    es_talla = VALUES(es_talla),
    orden   = VALUES(orden),
    activo  = 1;

-- ---------------------------------------------------------------------------
-- 2) Seed de medidas comunes (cm)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO variante_valores (id_variante_tipo_FK, valor, orden, activo)
SELECT vt.id_variante_tipo, v.valor, v.orden, 1
FROM variante_tipos vt
CROSS JOIN (
    SELECT '40cm' AS valor, 1  AS orden UNION ALL
    SELECT '45cm',          2           UNION ALL
    SELECT '50cm',          3           UNION ALL
    SELECT '55cm',          4           UNION ALL
    SELECT '60cm',          5           UNION ALL
    SELECT '70cm',          6           
) v
WHERE vt.slug = 'medida';
