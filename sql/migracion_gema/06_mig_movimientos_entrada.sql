-- Migracion Gema: movimientos_inventario entrada para stock disponible migrado
-- Ejecutar despues de 05_mig_piezas_stock.sql

SET NAMES utf8mb4;

SET @current_user_id = CAST((SELECT valor FROM mig_config WHERE clave = 'id_usuario_migracion') AS UNSIGNED);
SET @current_ip = '127.0.0.1';
SET @ref = (SELECT valor FROM mig_config WHERE clave = 'referencia_movimiento');

INSERT INTO movimientos_inventario (
    id_pieza_stock_FK,
    tipo_movimiento,
    referencia,
    observaciones,
    id_usuario_FK,
    tipo_referencia
)
SELECT
    ms.id_pieza_stock,
    'entrada',
    @ref,
    CONCAT('Migracion Gema ARTPIE/CODPIE=', ms.artpie, '/', ms.codpie),
    @current_user_id,
    'ajuste'
FROM mig_gema_stock ms
WHERE ms.estado_destino = 'disponible'
  AND NOT EXISTS (
      SELECT 1
      FROM movimientos_inventario mi
      WHERE mi.id_pieza_stock_FK = ms.id_pieza_stock
        AND mi.referencia = @ref
  );

SELECT '06_mig_movimientos_entrada.sql completado.' AS mensaje,
       (SELECT COUNT(*) FROM movimientos_inventario WHERE referencia = @ref) AS movimientos_entrada;
