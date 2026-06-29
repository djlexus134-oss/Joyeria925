-- Migracion Gema: piezas (unidades) -> piezas_stock
-- Ejecutar despues de 04_mig_piezas.sql

SET NAMES utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

SET @current_user_id = CAST((SELECT valor FROM mig_config WHERE clave = 'id_usuario_migracion') AS UNSIGNED);
SET @current_ip = '127.0.0.1';

DROP PROCEDURE IF EXISTS sp_mig_gema_piezas_stock;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_piezas_stock()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_artpie BIGINT;
    DECLARE v_codpie BIGINT;
    DECLARE v_id_pieza INT;
    DECLARE v_id_stock INT;
    DECLARE v_estado VARCHAR(20);
    DECLARE v_precio DECIMAL(10,2);
    DECLARE v_fecha DATETIME;
    DECLARE v_aux VARCHAR(20);
    DECLARE v_bar VARCHAR(50);
    DECLARE v_tipo ENUM('EAN13','CODE128','QR');
    DECLARE v_peso_pieza DECIMAL(10,2);
    DECLARE v_costo_pieza DECIMAL(10,2);
    DECLARE v_btaltpie CHAR(1);
    DECLARE v_brespie CHAR(1);
    DECLARE v_brespie2 CHAR(1);
    DECLARE v_clipie DOUBLE;
    DECLARE v_clipie2 DOUBLE;
    DECLARE v_factpie VARCHAR(20);
    DECLARE v_albpie CHAR(1);

    DECLARE cur CURSOR FOR
        SELECT p.artpie, p.codpie,
               p.btalpie, p.brespie, p.brespie2, p.clipie, p.clipie2,
               p.factpie, p.albpie,
               GREATEST(COALESCE(NULLIF(p.pvppie, 0), NULLIF(p.prcpie, 0), 0.01), 0.01),
               COALESCE(p.fcompie, NOW()),
               NULLIF(p.pespie, 0),
               NULLIF(p.prcpie, 0)
        FROM gema_staging.piezas p
        ORDER BY p.artpie, p.codpie;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    stock_loop: LOOP
        FETCH cur INTO v_artpie, v_codpie, v_btaltpie, v_brespie, v_brespie2,
            v_clipie, v_clipie2, v_factpie, v_albpie, v_precio, v_fecha,
            v_peso_pieza, v_costo_pieza;
        IF v_done THEN LEAVE stock_loop; END IF;

        IF EXISTS (SELECT 1 FROM mig_gema_stock WHERE artpie = v_artpie AND codpie = v_codpie) THEN
            ITERATE stock_loop;
        END IF;

        SET v_id_pieza = NULL;
        BEGIN
            DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
            SELECT ma.id_pieza INTO v_id_pieza
            FROM mig_gema_artic ma
            WHERE ma.codart = v_artpie;
        END;

        IF v_id_pieza IS NULL THEN
            INSERT INTO mig_log (etapa, nivel, clave, mensaje)
            VALUES ('stock', 'ERROR', CONCAT(v_artpie, '/', v_codpie),
                    'ARTPIE sin pieza catalogo; ejecute 04_mig_piezas.sql');
            ITERATE stock_loop;
        END IF;

        SET v_estado = mig_fn_estado_pieza_gema(
            v_btaltpie, v_brespie, v_brespie2, v_clipie, v_clipie2, v_factpie, v_albpie
        );

        -- Mismo criterio que Gema: ARTPIE/CODPIE (no id_pieza Joyeria)
        SET v_aux = mig_fn_legacy_auxiliar_gema(v_artpie, v_codpie);
        IF EXISTS (SELECT 1 FROM piezas_stock WHERE mig_fn_cs(codigo_auxiliar) = mig_fn_cs(v_aux)) THEN
            SET v_aux = LEFT(CONCAT(v_aux, 'M'), 20);
        END IF;

        -- EAN-13 interno derivado de ARTPIE+CODPIE (prefijo 20 + 6 + 4 + digito)
        SET v_bar = mig_fn_legacy_barras_gema(v_artpie, v_codpie);
        SET v_tipo = 'EAN13';

        IF EXISTS (SELECT 1 FROM piezas_stock WHERE mig_fn_cs(codigo_barras) = mig_fn_cs(v_bar))
           OR EXISTS (SELECT 1 FROM mig_gema_stock WHERE mig_fn_cs(codigo_barras) = mig_fn_cs(v_bar)) THEN
            SET v_bar = CONCAT('M', v_artpie, 'X', v_codpie);
            SET v_bar = LEFT(REGEXP_REPLACE(v_bar, '[^0-9A-Za-z]', ''), 50);
            SET v_tipo = 'CODE128';
        END IF;

        INSERT INTO piezas_stock (
            id_pieza_FK, codigo_auxiliar, precio_venta, codigo_barras,
            estado, tipo_codigo, fecha_alta, activo
        ) VALUES (
            v_id_pieza, v_aux, v_precio, v_bar,
            v_estado, v_tipo, v_fecha, 1
        );
        SET v_id_stock = LAST_INSERT_ID();

        INSERT INTO mig_gema_stock (
            artpie, codpie, id_pieza_stock, id_pieza, estado_destino,
            codigo_auxiliar, codigo_barras
        ) VALUES (
            v_artpie, v_codpie, v_id_stock, v_id_pieza, v_estado, v_aux, v_bar
        );

        IF v_peso_pieza IS NOT NULL THEN
            UPDATE piezas p
            SET p.peso_gr = COALESCE(p.peso_gr, v_peso_pieza)
            WHERE p.id_pieza = v_id_pieza AND p.peso_gr IS NULL;
        END IF;

    END LOOP;
    CLOSE cur;

    INSERT INTO mig_log (etapa, nivel, mensaje)
    SELECT 'stock', 'INFO', CONCAT('Unidades stock migradas: ', COUNT(*)) FROM mig_gema_stock;
END$$
DELIMITER ;

-- Procedimiento usa COMMIT interno; iniciar transaccion explicita si se desea todo-o-nada
CALL sp_mig_gema_piezas_stock();

SELECT '05_mig_piezas_stock.sql completado.' AS mensaje,
       (SELECT COUNT(*) FROM mig_gema_stock) AS stock_migrado,
       (SELECT COUNT(*) FROM gema_staging.piezas) AS stock_origen;

SELECT estado_destino, COUNT(*) AS cantidad
FROM mig_gema_stock
GROUP BY estado_destino
ORDER BY cantidad DESC;
