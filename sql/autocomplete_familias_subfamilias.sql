ALTER TABLE familias
  ADD COLUMN nom_familia_busqueda VARCHAR(50)
    GENERATED ALWAYS AS (nom_familia) STORED,
  ADD INDEX idx_familias_nom_familia_busqueda (nom_familia_busqueda);

ALTER TABLE sub_familia
  ADD COLUMN nom_sub_familia_busqueda VARCHAR(50)
    GENERATED ALWAYS AS (nom_sub_familia) STORED,
  ADD INDEX idx_sub_familia_nom_sub_familia_busqueda (nom_sub_familia_busqueda);
