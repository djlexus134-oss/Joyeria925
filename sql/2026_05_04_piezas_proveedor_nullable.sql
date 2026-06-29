ALTER TABLE piezas
    DROP FOREIGN KEY piezas_ibfk_3;

ALTER TABLE piezas
    MODIFY id_proveedor_FK INT NULL;

ALTER TABLE piezas
    ADD CONSTRAINT piezas_ibfk_3
        FOREIGN KEY (id_proveedor_FK) REFERENCES proveedores (id_proveedor)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
