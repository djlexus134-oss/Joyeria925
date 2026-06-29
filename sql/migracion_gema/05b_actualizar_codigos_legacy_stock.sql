-- Corrige codigo_auxiliar y codigo_barras de stock ya migrado (formato id_pieza/codpie -> ARTPIE/CODPIE).
-- Ejecutar si migro antes del cambio en 05_mig_piezas_stock.sql.
-- Requiere funciones de 01_mig_tablas_mapeo.sql y tabla mig_gema_stock poblada.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS sp_mig_fix_codigos_legacy_stock;
DELIMITER $$
CREATE PROCEDURE sp_mig_fix_codigos_legacy_stock()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_id_stock INT;
    DECLARE v_artpie BIGINT;
    DECLARE v_codpie BIGINT;
    DECLARE v_aux VARCHAR(20);
    DECLARE v_bar VARCHAR(50);
    DECLARE v_tipo ENUM('EAN13','CODE128','QR');

    DECLARE cur CURSOR FOR
        SELECT ms.id_pieza_stock, ms.artpie, ms.codpie
        FROM mig_gema_stock ms
        ORDER BY ms.artpie, ms.codpie;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    fix_loop: LOOP
        FETCH cur INTO v_id_stock, v_artpie, v_codpie;
        IF v_done THEN LEAVE fix_loop; END IF;

        SET v_aux = mig_fn_legacy_auxiliar_gema(v_artpie, v_codpie);
        IF EXISTS (
            SELECT 1 FROM piezas_stock ps
            WHERE ps.id_pieza_stock <> v_id_stock
              AND mig_fn_cs(ps.codigo_auxiliar) = mig_fn_cs(v_aux)
        ) THEN
            SET v_aux = LEFT(CONCAT(v_aux, 'M'), 20);
        END IF;

        SET v_bar = mig_fn_legacy_barras_gema(v_artpie, v_codpie);
        SET v_tipo = 'EAN13';
        IF EXISTS (
            SELECT 1 FROM piezas_stock ps
            WHERE ps.id_pieza_stock <> v_id_stock
              AND mig_fn_cs(ps.codigo_barras) = mig_fn_cs(v_bar)
        ) THEN
            SET v_bar = LEFT(REGEXP_REPLACE(CONCAT('M', v_artpie, 'X', v_codpie), '[^0-9A-Za-z]', ''), 50);
            SET v_tipo = 'CODE128';
        END IF;

        UPDATE piezas_stock
        SET codigo_auxiliar = v_aux,
            codigo_barras = v_bar,
            tipo_codigo = v_tipo
        WHERE id_pieza_stock = v_id_stock;

        UPDATE mig_gema_stock
        SET codigo_auxiliar = v_aux,
            codigo_barras = v_bar
        WHERE id_pieza_stock = v_id_stock;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL sp_mig_fix_codigos_legacy_stock();
DROP PROCEDURE IF EXISTS sp_mig_fix_codigos_legacy_stock;

SET FOREIGN_KEY_CHECKS = 1;

SELECT '05b_actualizar_codigos_legacy_stock.sql completado.' AS mensaje;

SELECT ms.artpie, ms.codpie, ps.codigo_auxiliar, ps.codigo_barras
FROM mig_gema_stock ms
INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ms.id_pieza_stock
ORDER BY ms.artpie, ms.codpie
LIMIT 10;
