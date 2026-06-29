-- =============================================================================
-- Datos masivos de prueba: familias + subfamilia + piezas + imagen principal
-- Para probar vitrina, carrusel por familia y filtros del catálogo público.
--
-- ANTES DE EJECUTAR:
--   1. Ajusta las variables @url_demo y los contadores (@n_familias, @n_piezas).
--   2. La ruta @url_demo debe coincidir con un archivo real bajo admin/imagenes/piezas/
--      (la app guarda en DB como imagenes/piezas/nombre.jpg).
--   3. Debe existir al menos un metal activo y una tienda activa.
--
-- REVERTIR: ejecuta sql/bulk_seed_familias_y_piezas_demo_cleanup.sql
-- =============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS joyeria_bulk_seed_demo_familias_piezas;

DELIMITER $$

CREATE PROCEDURE joyeria_bulk_seed_demo_familias_piezas(
    IN p_num_familias INT,
    IN p_num_piezas_por_familia INT,
    IN p_url_imagen VARCHAR(512)
)
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE j INT DEFAULT 1;
    DECLARE v_id_familia INT;
    DECLARE v_id_subfamilia INT;
    DECLARE v_id_pieza INT;

    DECLARE v_id_metal INT;
    DECLARE v_id_tienda INT;

    IF p_num_familias < 1 OR p_num_familias > 200 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'p_num_familias debe estar entre 1 y 200';
    END IF;

    IF p_num_piezas_por_familia < 1 OR p_num_piezas_por_familia > 500 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'p_num_piezas_por_familia debe estar entre 1 y 500';
    END IF;

    SELECT id_metal INTO v_id_metal
    FROM metales
    WHERE activo = 1
    ORDER BY id_metal ASC
    LIMIT 1;

    SELECT id_tienda INTO v_id_tienda
    FROM tiendas
    WHERE COALESCE(activo, 1) = 1
    ORDER BY id_tienda ASC
    LIMIT 1;

    IF v_id_metal IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No hay ningún metal activo (metales.activo=1).';
    END IF;

    IF v_id_tienda IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No hay ninguna tienda activa (tiendas).';
    END IF;

    SET i = 1;
    WHILE i <= p_num_familias DO
        INSERT INTO familias (nom_familia, activo)
        VALUES (CONCAT('DEMO_BULK_Fam ', LPAD(i, 3, '0')), 1);

        SET v_id_familia = LAST_INSERT_ID();

        INSERT INTO sub_familia (nom_sub_familia, id_familia_FK, activo)
        VALUES (CONCAT('Demo sub ', LPAD(i, 3, '0')), v_id_familia, 1);

        SET v_id_subfamilia = LAST_INSERT_ID();

        SET j = 1;
        WHILE j <= p_num_piezas_por_familia DO
            INSERT INTO piezas (
                desc_pieza,
                id_sub_familia_FK,
                id_metal_FK,
                id_proveedor_FK,
                id_tienda_FK,
                peso_gr,
                costo,
                precio_por_gramo,
                aumento_pct,
                largo,
                ancho,
                observaciones,
                activo
            ) VALUES (
                CONCAT('Pieza demo ', LPAD(i, 3, '0'), '-', LPAD(j, 2, '0')),
                v_id_subfamilia,
                v_id_metal,
                NULL,
                v_id_tienda,
                5.0000,
                100.00,
                NULL,
                25.00,
                NULL,
                NULL,
                'Carga masiva de prueba (DEMO_BULK)',
                1
            );

            SET v_id_pieza = LAST_INSERT_ID();

            INSERT INTO imagenes_piezas (id_pieza_FK, url_imagen, es_principal)
            VALUES (v_id_pieza, p_url_imagen, 1);

            SET j = j + 1;
        END WHILE;

        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------------
-- Parámetros de la demo (cámbialos aquí)
-- ---------------------------------------------------------------------------
SET @n_familias := 12;
SET @n_piezas_por_familia := 5;
-- Ruta tal como la guarda la app (debe existir el archivo en disco)
SET @url_demo := 'imagenes/piezas/pieza_19_20260505234939_8d6dbd99bbd0073bf708.jpg';

CALL joyeria_bulk_seed_demo_familias_piezas(@n_familias, @n_piezas_por_familia, @url_demo);

-- Opcional: dejar el procedimiento para reutilizar; si no quieres dejarlo:
-- DROP PROCEDURE IF EXISTS joyeria_bulk_seed_demo_familias_piezas;
