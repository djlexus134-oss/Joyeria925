-- Franjas promocionales editables para catálogo (visitante index + zona cliente).
-- Ejecutar en la base de datos de la joyería.

CREATE TABLE IF NOT EXISTS promociones_banner (
    id_promocion_banner INT NOT NULL AUTO_INCREMENT,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    visible_visitante TINYINT(1) NOT NULL DEFAULT 1,
    visible_cliente TINYINT(1) NOT NULL DEFAULT 1,
    variante VARCHAR(48) NOT NULL DEFAULT 'mayoreo',
    eyebrow VARCHAR(255) NULL DEFAULT NULL,
    titulo VARCHAR(255) NOT NULL,
    texto TEXT NOT NULL,
    cta_label VARCHAR(120) NULL DEFAULT NULL,
    cta_href VARCHAR(512) NULL DEFAULT NULL,
    fuente_imagen ENUM('ninguna', 'catalogo_rotacion', 'pieza_fija') NOT NULL DEFAULT 'ninguna',
    id_pieza_fk INT NULL DEFAULT NULL,
    fecha_inicio DATE NULL DEFAULT NULL,
    fecha_fin DATE NULL DEFAULT NULL,
    fecha_creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_modificado TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_promocion_banner),
    KEY idx_promo_banner_frontend (activo, visible_visitante, visible_cliente, orden),
    CONSTRAINT fk_promo_banner_pieza
        FOREIGN KEY (id_pieza_fk) REFERENCES piezas (id_pieza)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos semilla equivalentes al fallback en código (puedes editar tras aplicar migración).

INSERT INTO promociones_banner
(activo, orden, visible_visitante, visible_cliente, variante, eyebrow, titulo, texto, cta_label, cta_href, fuente_imagen)
VALUES
(1, 1, 1, 1, 'mayoreo', 'Mayoreo', '50% en selección desde $6,000 MXN',
 'En compras a precio de etiqueta desde seis mil pesos elegibles: armamos tu selección y te llevas medio precio sobre piezas marcadas como mayoreo. Pregunta en sala o por correo para condiciones y vigencia.',
 'Ver piezas', '#catalogo', 'ninguna'),
(1, 2, 1, 1, 'pieza', 'Pieza del momento', 'Un detalle que abraza sin apretar',
 'La plata bien trabajada vive contigo todos los días: en la luz del café, en el abrazo de quien amas. Elige algo que te recuerde que lo bello también es sencillo.',
 'Explorar catálogo', '#catalogo', 'catalogo_rotacion'),
(1, 3, 1, 1, 'trabajo', 'Hecho aquí', 'Tradición de taller, acabados de hoy',
 'Combinamos oficio joyero con piezas pensadas para durar y para regalar con orgullo. Pasa por el centro de Celaya o escríbenos para un pedido especial.',
 'Contacto', 'mailto:djlexus134@gmail.com', 'ninguna'),
(1, 4, 1, 1, 'tradicion', '', 'Tradición y exclusividad',
 'En Platería El Ángel combinamos la tradición joyera con diseños modernos, ofreciendo piezas de plata que reflejan calidad, confianza y distinción.',
 '', '#catalogo', 'catalogo_rotacion');
