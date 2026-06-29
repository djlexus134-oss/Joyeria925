-- Migracion Gema: artic (solo usados en piezas) -> piezas
-- Ejecutar despues de 03_mig_clientes.sql

SET NAMES utf8mb4;

SET @current_user_id = CAST((SELECT valor FROM mig_config WHERE clave = 'id_usuario_migracion') AS UNSIGNED);
SET @current_ip = '127.0.0.1';

SET @id_tienda = CAST((SELECT valor FROM mig_config WHERE clave = 'id_tienda_default') AS UNSIGNED);
SET @id_metal_def = CAST((SELECT valor FROM mig_config WHERE clave = 'id_metal_default') AS UNSIGNED);
SET @id_sub_def = (SELECT id_sub_familia FROM mig_gema_familia WHERE codfam = '#DEF' LIMIT 1);

DROP PROCEDURE IF EXISTS sp_mig_gema_piezas;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_piezas()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_codart BIGINT;
    DECLARE v_peso DECIMAL(10,2);
    DECLARE v_costo DECIMAL(10,2);
    DECLARE v_pvp DECIMAL(10,2);
    DECLARE v_aumento DECIMAL(6,2);
    DECLARE v_famart VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_tipaart VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_desc VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_provart DOUBLE;
    DECLARE v_obs TEXT;
    DECLARE v_id_sub INT;
    DECLARE v_id_metal INT;
    DECLARE v_id_prov INT;
    DECLARE v_id_pieza INT;

    DECLARE cur CURSOR FOR
        SELECT DISTINCT a.codart
        FROM gema_staging.artic a
        INNER JOIN gema_staging.piezas p ON p.artpie = a.codart
        ORDER BY a.codart;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    art_loop: LOOP
        FETCH cur INTO v_codart;
        IF v_done THEN LEAVE art_loop; END IF;

        IF EXISTS (SELECT 1 FROM mig_gema_artic WHERE codart = v_codart) THEN
            ITERATE art_loop;
        END IF;

        SELECT
            LEFT(TRIM(COALESCE(NULLIF(TRIM(a.desart), ''), NULLIF(TRIM(a.descoart), ''), CONCAT('Articulo ', a.codart))), 100),
            NULLIF(a.pesart, 0),
            GREATEST(
                COALESCE(NULLIF(a.cosuniart, 0), NULLIF(a.prcart, 0), NULLIF(a.prvart, 0), 0.01),
                0.01
            ),
            NULLIF(GREATEST(COALESCE(a.pvpart, 0), 0), 0),
            TRIM(IFNULL(a.famart, '')),
            TRIM(IFNULL(a.tipaart, '')),
            NULLIF(a.provart, 0),
            a.obsart
        INTO v_desc, v_peso, v_costo, v_pvp, v_famart, v_tipaart, v_provart, v_obs
        FROM gema_staging.artic a
        WHERE a.codart = v_codart;

        SET v_id_sub = NULL;
        BEGIN
            DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
            SELECT mf.id_sub_familia INTO v_id_sub
            FROM mig_gema_familia mf
            WHERE mig_fn_cs(mf.codfam) = mig_fn_cs(v_famart)
            LIMIT 1;
        END;
        IF v_id_sub IS NULL THEN
            SET v_id_sub = @id_sub_def;
        END IF;

        SET v_id_metal = NULL;
        BEGIN
            DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
            SELECT mm.id_metal INTO v_id_metal
            FROM mig_gema_metal mm
            WHERE mig_fn_cs(mm.tipaart) = mig_fn_cs(v_tipaart)
            LIMIT 1;
        END;
        IF v_id_metal IS NULL THEN
            SET v_id_metal = @id_metal_def;
        END IF;

        SET v_id_prov = NULL;
        IF v_provart IS NOT NULL THEN
            BEGIN
                DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
                SELECT mp.id_proveedor INTO v_id_prov
                FROM mig_gema_proveedor mp
                WHERE mp.codpro = v_provart
                LIMIT 1;
            END;
        END IF;

        SET v_aumento = NULL;
        IF v_pvp IS NOT NULL AND v_costo > 0 AND v_pvp > v_costo THEN
            SET v_aumento = ROUND(((v_pvp / v_costo) - 1) * 100, 2);
        END IF;

        INSERT INTO piezas (
            desc_pieza, id_sub_familia_FK, id_metal_FK, id_proveedor_FK, id_tienda_FK,
            peso_gr, costo, precio_por_gramo, aumento_pct, observaciones, activo
        ) VALUES (
            v_desc, v_id_sub, v_id_metal, v_id_prov, @id_tienda,
            v_peso, v_costo, NULL, v_aumento,
            LEFT(CONCAT('[GEMA codart=', v_codart, '] ', IFNULL(v_obs, '')), 2000),
            1
        );
        SET v_id_pieza = LAST_INSERT_ID();

        INSERT INTO mig_gema_artic (codart, id_pieza) VALUES (v_codart, v_id_pieza);

        SET v_id_sub = NULL;
        SET v_id_metal = NULL;
        SET v_id_prov = NULL;
    END LOOP;
    CLOSE cur;

    INSERT INTO mig_log (etapa, nivel, mensaje)
    SELECT 'piezas', 'INFO', CONCAT('Piezas catalogo migradas: ', COUNT(*)) FROM mig_gema_artic;
END$$
DELIMITER ;

CALL sp_mig_gema_piezas();

SELECT '04_mig_piezas.sql completado.' AS mensaje,
       (SELECT COUNT(*) FROM mig_gema_artic) AS piezas_catalogo,
       (SELECT COUNT(DISTINCT artpie) FROM gema_staging.piezas) AS articulos_con_stock_origen;
