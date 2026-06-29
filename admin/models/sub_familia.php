<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class SubFamilia extends Sistema {

    const TABLE = 'sub_familia';
    const MAX_NAME_LENGTH = 50;

    public function leer($id_familia = null, ?string $busqueda = null) {
        $pat = joyeria_like_pattern($busqueda);
        if ($id_familia) {
            $sql = "SELECT sf.*, f.nom_familia FROM " . self::TABLE . " sf 
                    INNER JOIN familias f ON sf.id_familia_FK = f.id_familia 
                    WHERE sf.activo = 1 AND sf.id_familia_FK = :id_familia ";
            if ($pat !== null) {
                $sql .= " AND (sf.nom_sub_familia LIKE :busq OR f.nom_familia LIKE :busq2)";
            }
            $sql .= " ORDER BY sf.nom_sub_familia ASC";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindParam(':id_familia', $id_familia, PDO::PARAM_INT);
            if ($pat !== null) {
                $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            }
            $stmt->execute();
        } else {
            $sql = "SELECT sf.*, f.nom_familia FROM " . self::TABLE . " sf 
                    INNER JOIN familias f ON sf.id_familia_FK = f.id_familia 
                    WHERE sf.activo = 1 ";
            if ($pat !== null) {
                $sql .= " AND (sf.nom_sub_familia LIKE :busq OR f.nom_familia LIKE :busq2)";
            }
            $sql .= " ORDER BY f.nom_familia ASC, sf.nom_sub_familia ASC";
            $stmt = $this->getDb()->prepare($sql);
            if ($pat !== null) {
                $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            }
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function leerUno($id_sub_familia) {
        $sql = "SELECT sf.*, f.nom_familia FROM " . self::TABLE . " sf 
                INNER JOIN familias f ON sf.id_familia_FK = f.id_familia 
                WHERE sf.id_sub_familia = :id_sub_familia";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':id_sub_familia', $id_sub_familia, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerFamilias() {
        $sql = "SELECT id_familia, nom_familia FROM familias WHERE activo = 1 ORDER BY nom_familia ASC";
        $stmt = $this->getDb()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function crear($data) {
        $nom_sub_familia = $this->validarNombre($data);
        $id_familia_FK = isset($data['id_familia_FK']) ? intval($data['id_familia_FK']) : null;
        
        $sql = "INSERT INTO " . self::TABLE . " (nom_sub_familia, id_familia_FK, activo) 
                VALUES (:nom_sub_familia, :id_familia_FK, 1)";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':nom_sub_familia', $nom_sub_familia, PDO::PARAM_STR);
        $stmt->bindParam(':id_familia_FK', $id_familia_FK, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    public function actualizar($id_sub_familia, $data) {
        $nom_sub_familia = $this->validarNombre($data);
        $id_familia_FK = isset($data['id_familia_FK']) ? intval($data['id_familia_FK']) : null;
        
        $sql = "UPDATE " . self::TABLE . " 
                SET nom_sub_familia = :nom_sub_familia, id_familia_FK = :id_familia_FK 
                WHERE id_sub_familia = :id_sub_familia AND activo = 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':nom_sub_familia', $nom_sub_familia, PDO::PARAM_STR);
        $stmt->bindParam(':id_familia_FK', $id_familia_FK, PDO::PARAM_INT);
        $stmt->bindParam(':id_sub_familia', $id_sub_familia, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    public function borrar($id_sub_familia) {
        $sql = "UPDATE " . self::TABLE . " 
                SET activo = 0, fecha_baja = NOW()
                WHERE id_sub_familia = :id_sub_familia";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':id_sub_familia', $id_sub_familia, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    private function validarNombre($data) {
        return isset($data['nom_sub_familia']) ? trim($data['nom_sub_familia']) : null;
    }
}
