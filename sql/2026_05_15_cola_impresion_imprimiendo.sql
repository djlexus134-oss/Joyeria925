-- Agrega el estado intermedio 'imprimiendo' a la cola de impresion
-- para evitar que el agente reciba el mismo job dos veces mientras
-- esta procesando (estampa atomica).
--
-- Idempotente: si el ENUM ya tiene 'imprimiendo' no hace cambios.

ALTER TABLE cola_impresion
    MODIFY COLUMN estado ENUM('pendiente', 'imprimiendo', 'impreso', 'error')
    NOT NULL DEFAULT 'pendiente';

-- Recuperacion: cualquier job que quedo atrapado en 'imprimiendo' antes
-- del despliegue lo dejamos como 'pendiente' (intentos sin tocar para no
-- saltar el cap MAX_INTENTOS).
UPDATE cola_impresion
   SET estado = 'pendiente'
 WHERE estado = 'imprimiendo';
