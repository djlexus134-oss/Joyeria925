from pathlib import Path

src = Path(__file__).resolve().parents[2] / 'sql' / '2026_07_06_mayoristas_descuentos_import.sql'
dst = Path(__file__).resolve().parents[2] / 'sql' / '2026_07_06_mayoristas_descuentos_aplicar.sql'
lines = src.read_text(encoding='utf-8').splitlines()
end = next(i for i, line in enumerate(lines) if line.strip().startswith('-- PREVIEW:'))
body = lines[:end]

header = """-- Aplicar descuentos mayoristas (1986 registros)
-- Ejecutar en DataGrip contra schema joyeria
-- Match por nombre completo normalizado -> clientes.descuento_porcentaje

START TRANSACTION;

"""

update = """
-- Aplicar descuentos
UPDATE clientes c
INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK AND u.activo = 1
INNER JOIN tmp_mayoristas_excel m ON LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
    CONCAT_WS(' ', u.nombre, u.primer_apellido, u.segundo_apellido),
    'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'))) = m.nombre_norm
SET c.descuento_porcentaje = m.descuento_porcentaje
WHERE c.activo = 1
  AND (c.descuento_porcentaje IS NULL OR CAST(c.descuento_porcentaje AS DECIMAL(5,2)) <> m.descuento_porcentaje);

SELECT ROW_COUNT() AS filas_actualizadas;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_mayoristas_excel;
"""

out = header + '\n'.join(body) + update
dst.write_text(out, encoding='utf-8')
print(f'OK: {dst} ({len(out.splitlines())} lineas)')
