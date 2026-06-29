-- Permite alta de proveedor sin direccion registrada en catalogo.
-- Ejecutar en la base donde vive la tabla (ajusta el nombre del esquema si no usas joyeria).

ALTER TABLE joyeria.proveedores
    MODIFY id_direccion_FK int NULL;
