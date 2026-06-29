-- Migracion Gema: familias, metales (TIPAART), proveedores.
-- Ejecutar despues de 01_mig_tablas_mapeo.sql
-- Requiere gema_staging con familias, tipoarti, prov, artic.

SET NAMES utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

-- Si 01 ya corrio sin estas funciones, crearlas aqui (idempotente)
DROP FUNCTION IF EXISTS mig_fn_ci;
DELIMITER $$
CREATE FUNCTION mig_fn_ci(p TEXT)
RETURNS TEXT
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci
DETERMINISTIC
BEGIN
    IF p IS NULL THEN RETURN NULL; END IF;
    RETURN LOWER(TRIM(CONVERT(p USING utf8mb4))) COLLATE utf8mb4_unicode_ci;
END$$
DELIMITER ;

DROP FUNCTION IF EXISTS mig_fn_cs;
DELIMITER $$
CREATE FUNCTION mig_fn_cs(p TEXT)
RETURNS TEXT
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci
DETERMINISTIC
BEGIN
    IF p IS NULL THEN RETURN NULL; END IF;
    RETURN TRIM(CONVERT(p USING utf8mb4)) COLLATE utf8mb4_unicode_ci;
END$$
DELIMITER ;

SET @current_user_id = COALESCE(
    (SELECT CAST(valor AS UNSIGNED) FROM mig_config WHERE clave = 'id_usuario_migracion' AND valor <> ''),
    (SELECT id_usuario FROM usuarios WHERE activo = 1 ORDER BY id_usuario LIMIT 1),
    (SELECT e.id_usuario_FK FROM empleados e WHERE e.activo = 1 ORDER BY e.id_empleado LIMIT 1)
);
SET @current_ip = '127.0.0.1';

DROP PROCEDURE IF EXISTS sp_mig_gema_init_config;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_init_config()
BEGIN
    DECLARE v_tienda INT;
    DECLARE v_metal INT;
    DECLARE v_usuario INT;
    DECLARE v_cfg VARCHAR(255);

    SET v_tienda = (
        SELECT id_tienda FROM tiendas WHERE COALESCE(activo, 1) = 1 ORDER BY id_tienda LIMIT 1
    );
    SET v_cfg = (SELECT valor FROM mig_config WHERE clave = 'id_tienda_default');
    IF v_tienda IS NOT NULL AND (v_cfg IS NULL OR v_cfg = '') THEN
        UPDATE mig_config SET valor = CAST(v_tienda AS CHAR) WHERE clave = 'id_tienda_default';
    END IF;

    SET v_metal = (
        SELECT id_metal FROM metales WHERE activo = 1 ORDER BY id_metal LIMIT 1
    );
    SET v_cfg = (SELECT valor FROM mig_config WHERE clave = 'id_metal_default');
    IF v_metal IS NOT NULL AND (v_cfg IS NULL OR v_cfg = '') THEN
        UPDATE mig_config SET valor = CAST(v_metal AS CHAR) WHERE clave = 'id_metal_default';
    END IF;

    SET v_usuario = @current_user_id;
    IF v_usuario IS NULL THEN
        SET v_usuario = (
            SELECT e.id_usuario_FK FROM empleados e WHERE e.activo = 1 ORDER BY e.id_empleado LIMIT 1
        );
    END IF;
    SET v_cfg = (SELECT valor FROM mig_config WHERE clave = 'id_usuario_migracion');
    IF v_usuario IS NOT NULL AND (v_cfg IS NULL OR v_cfg = '') THEN
        UPDATE mig_config SET valor = CAST(v_usuario AS CHAR) WHERE clave = 'id_usuario_migracion';
    END IF;
END$$
DELIMITER ;

-- Tras limpiar metales, hace falta al menos uno para TIPAART sin match
INSERT INTO metales (nom_metal, activo, precio_tienda, precio_mercado)
SELECT 'General (Gema)', 1, 0.00, 0.00
FROM (SELECT 1 AS _x) t
WHERE NOT EXISTS (SELECT 1 FROM metales WHERE activo = 1 LIMIT 1);

CALL sp_mig_gema_init_config();

-- Re-ejecutar 02: vaciar mapeos de catalogo (no toca joyeria ni clientes/piezas)
DELETE FROM mig_gema_proveedor;
DELETE FROM mig_gema_metal;
DELETE FROM mig_gema_familia;

-- ---------------------------------------------------------------------------
-- Familias Gema -> familias + sub_familia (1 sub por familia)
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_mig_gema_familias;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_familias()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_codfam VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_nomfam VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_id_familia INT;
    DECLARE v_id_sub INT;
    DECLARE v_nom_norm VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    DECLARE cur CURSOR FOR
        SELECT f.codfam, TRIM(IFNULL(f.nomfam, f.codfam))
        FROM gema_staging.familias f;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_codfam, v_nomfam;
        IF v_done THEN
            LEAVE read_loop;
        END IF;

        IF EXISTS (SELECT 1 FROM mig_gema_familia WHERE mig_fn_cs(codfam) = mig_fn_cs(v_codfam)) THEN
            ITERATE read_loop;
        END IF;

        SET v_nom_norm = LEFT(TRIM(v_nomfam), 50);
        IF CHAR_LENGTH(TRIM(v_nom_norm)) = 0 THEN
            SET v_nom_norm = CONCAT('Gema ', v_codfam);
        END IF;

        SET v_id_sub = NULL;
        SET v_id_familia = NULL;
        BEGIN
            DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
            SELECT sf.id_sub_familia, sf.id_familia_FK
            INTO v_id_sub, v_id_familia
            FROM sub_familia sf
            INNER JOIN familias fa ON fa.id_familia = sf.id_familia_FK
            WHERE fa.activo = 1
              AND mig_fn_ci(fa.nom_familia) = mig_fn_ci(v_nom_norm)
            LIMIT 1;
        END;

        IF v_id_sub IS NULL THEN
            INSERT INTO familias (nom_familia, activo)
            VALUES (v_nom_norm, 1);
            SET v_id_familia = LAST_INSERT_ID();

            INSERT INTO sub_familia (nom_sub_familia, id_familia_FK, activo)
            VALUES (v_nom_norm, v_id_familia, 1);
            SET v_id_sub = LAST_INSERT_ID();
        END IF;

        INSERT INTO mig_gema_familia (codfam, id_familia, id_sub_familia, nom_familia)
        VALUES (v_codfam, v_id_familia, v_id_sub, v_nom_norm);

        SET v_id_sub = NULL;
        SET v_id_familia = NULL;
    END LOOP;
    CLOSE cur;

    INSERT INTO mig_log (etapa, nivel, mensaje)
    SELECT 'familias', 'INFO', CONCAT('Familias mapeadas: ', COUNT(*)) FROM mig_gema_familia;
END$$
DELIMITER ;

CALL sp_mig_gema_familias();

-- Subfamilia fallback si artic tiene FAMART sin match
DROP PROCEDURE IF EXISTS sp_mig_gema_familia_fallback;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_familia_fallback()
BEGIN
  DECLARE v_id_sub INT;
  DECLARE v_id_fam INT;

  IF NOT EXISTS (SELECT 1 FROM mig_gema_familia WHERE mig_fn_cs(codfam) = '#DEF') THEN
    SELECT id_sub_familia, id_familia_FK INTO v_id_sub, v_id_fam
    FROM sub_familia WHERE activo = 1 ORDER BY id_sub_familia LIMIT 1;

    IF v_id_sub IS NULL THEN
      INSERT INTO familias (nom_familia, activo) VALUES ('Importacion Gema', 1);
      SET v_id_fam = LAST_INSERT_ID();
      INSERT INTO sub_familia (nom_sub_familia, id_familia_FK, activo)
      VALUES ('General Gema', v_id_fam, 1);
      SET v_id_sub = LAST_INSERT_ID();
    END IF;

    INSERT INTO mig_gema_familia (codfam, id_familia, id_sub_familia, nom_familia)
    VALUES ('#DEF', v_id_fam, v_id_sub, 'Importacion Gema');
  END IF;
END$$
DELIMITER ;

CALL sp_mig_gema_familia_fallback();

-- ---------------------------------------------------------------------------
-- Metales: TIPAART + heuristica por DESTIPA / nom_metal en Joyeria
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_mig_gema_metales;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_metales()
BEGIN
    DECLARE v_id_metal_def INT;
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_tipa VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_dest VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_id_metal INT;

    DECLARE cur CURSOR FOR
        SELECT DISTINCT TRIM(a.tipaart)
        FROM gema_staging.artic a
        WHERE a.tipaart IS NOT NULL AND TRIM(a.tipaart) <> ''
        UNION
        SELECT DISTINCT TRIM(t.codtipa) FROM gema_staging.tipoarti t;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    SET v_id_metal_def = CAST((SELECT valor FROM mig_config WHERE clave = 'id_metal_default') AS UNSIGNED);

    OPEN cur;
    metal_loop: LOOP
        FETCH cur INTO v_tipa;
        IF v_done THEN LEAVE metal_loop; END IF;
        IF EXISTS (SELECT 1 FROM mig_gema_metal WHERE mig_fn_cs(tipaart) = mig_fn_cs(v_tipa)) THEN
            ITERATE metal_loop;
        END IF;

        SET v_dest = NULL;
        BEGIN
            DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
            SELECT t.destipa INTO v_dest FROM gema_staging.tipoarti t WHERE mig_fn_cs(t.codtipa) = mig_fn_cs(v_tipa) LIMIT 1;
        END;

        SET v_id_metal = NULL;
        BEGIN
            DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
            SELECT m.id_metal INTO v_id_metal
            FROM metales m
            WHERE m.activo = 1
              AND (
                mig_fn_ci(m.nom_metal) LIKE CONCAT('%', mig_fn_ci(IFNULL(v_dest, v_tipa)), '%')
                OR mig_fn_ci(IFNULL(v_dest, '')) LIKE CONCAT('%', mig_fn_ci(m.nom_metal), '%')
                OR (mig_fn_ci(v_tipa) IN ('o','or','oro') AND mig_fn_ci(m.nom_metal) LIKE '%oro%')
                OR (mig_fn_ci(v_tipa) IN ('p','pl','pla') AND mig_fn_ci(m.nom_metal) LIKE '%plata%')
              )
            ORDER BY m.id_metal
            LIMIT 1;
        END;

        IF v_id_metal IS NULL THEN
            SET v_id_metal = v_id_metal_def;
            INSERT INTO mig_log (etapa, nivel, clave, mensaje)
            VALUES ('metales', 'WARN', v_tipa, CONCAT('TIPAART sin match; metal default ', v_id_metal_def));
        END IF;

        INSERT INTO mig_gema_metal (tipaart, id_metal, descripcion)
        VALUES (v_tipa, v_id_metal, v_dest);
        SET v_id_metal = NULL;
        SET v_dest = NULL;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL sp_mig_gema_metales();
CALL sp_mig_gema_init_config();

-- ---------------------------------------------------------------------------
-- Proveedores: prov + PROVART en artic
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_mig_gema_proveedores;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_proveedores()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_codpro DOUBLE;
    DECLARE v_razon VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_id_prov INT;

    DECLARE cur CURSOR FOR
        SELECT DISTINCT p.codpro, LEFT(TRIM(COALESCE(p.razpro, p.nompro, CONCAT('Proveedor ', p.codpro))), 100)
        FROM gema_staging.prov p
        UNION
        SELECT DISTINCT a.provart, CONCAT('Prov Gema ', CAST(a.provart AS UNSIGNED))
        FROM gema_staging.artic a
        WHERE a.provart IS NOT NULL AND a.provart <> 0;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    prov_loop: LOOP
        FETCH cur INTO v_codpro, v_razon;
        IF v_done THEN LEAVE prov_loop; END IF;
        IF v_codpro IS NULL OR v_codpro = 0 THEN ITERATE prov_loop; END IF;
        IF EXISTS (SELECT 1 FROM mig_gema_proveedor WHERE codpro = v_codpro) THEN
            ITERATE prov_loop;
        END IF;

        SET v_id_prov = NULL;
        BEGIN
            DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
            SELECT id_proveedor INTO v_id_prov
            FROM proveedores
            WHERE COALESCE(activo, 1) = 1
              AND mig_fn_ci(razon_social) = mig_fn_ci(v_razon)
            LIMIT 1;
        END;

        IF v_id_prov IS NULL THEN
            INSERT INTO proveedores (razon_social, nom_comercial, activo)
            VALUES (LEFT(v_razon, 100), LEFT(v_razon, 100), 1);
            SET v_id_prov = LAST_INSERT_ID();
        END IF;

        INSERT INTO mig_gema_proveedor (codpro, id_proveedor, razon_legacy)
        VALUES (v_codpro, v_id_prov, v_razon);
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL sp_mig_gema_proveedores();

SELECT '02_mig_catalogos.sql completado.' AS mensaje,
       (SELECT COUNT(*) FROM mig_gema_familia) AS familias_mapeadas,
       (SELECT COUNT(*) FROM mig_gema_metal) AS metales_mapeados,
       (SELECT COUNT(*) FROM mig_gema_proveedor) AS proveedores_mapeados;
