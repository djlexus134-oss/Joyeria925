-- Cola de impresion termica POS + configuracion de ticket

CREATE TABLE IF NOT EXISTS cola_impresion (
    id_cola_impresion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_venta_FK INT NOT NULL,
    id_tienda_FK INT NULL,
    tipo ENUM('venta', 'reimpresion') NOT NULL DEFAULT 'venta',
    estado ENUM('pendiente', 'impreso', 'error') NOT NULL DEFAULT 'pendiente',
    payload_json LONGTEXT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    mensaje_error VARCHAR(500) NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_impreso DATETIME NULL,
    PRIMARY KEY (id_cola_impresion),
    KEY idx_cola_impresion_estado (estado, fecha_creacion),
    KEY idx_cola_impresion_venta (id_venta_FK)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
VALUES
    ('ticket_nombre_comercial', 'Plateria el Angel', 'STRING', 'Nombre comercial en encabezado del ticket', NOW()),
    ('ticket_leyenda_folio', 'Folio', 'STRING', 'Leyenda mostrada arriba del numero de venta', NOW()),
    ('ticket_horario', 'Horario: 1:30 PM - 7:00 PM', 'STRING', 'Horario de atencion en el ticket', NOW()),
    ('ticket_mensaje_pie', 'Gracias por su preferencia', 'STRING', 'Mensaje al pie del ticket', NOW()),
    ('ticket_ancho_columnas', '42', 'INT', 'Ancho en caracteres para impresora 80mm', NOW()),
    ('ticket_mostrar_impuesto', '1', 'BOOLEAN', 'Mostrar desglose de impuesto en ticket', NOW()),
    ('ticket_mostrar_empleado', '1', 'BOOLEAN', 'Mostrar nombre del empleado en ticket', NOW()),
    ('impresion_habilitada', '1', 'BOOLEAN', 'Encolar tickets al confirmar venta', NOW()),
    ('impresion_caja_token', 'cambiar_token_seguro', 'STRING', 'Token secreto para el agente de impresion (header X-Caja-Token)', NOW()),
    ('impresion_nombre_impresora', 'EPSON TM-T20 Receipt', 'STRING', 'Nombre de impresora en Windows (referencia)', NOW()),
    ('impresion_id_tienda_caja', '', 'INT', 'ID tienda que atiende esta caja (0 = todas)', NOW())
ON DUPLICATE KEY UPDATE fecha_actualizacion = NOW();
