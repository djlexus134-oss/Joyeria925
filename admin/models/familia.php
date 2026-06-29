<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";
require_once __DIR__ . "/../includes/catalog_duplicate_guard.php";

class Familia extends Sistema {

    const TABLE = 'familias';
    const MAX_NAME_LENGTH = 50;

    public function leer(?string $busqueda = null) {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE activo = 1";
        if ($pat !== null) {
            $sql .= " AND nom_familia LIKE :busq";
        }
        $sql .= " ORDER BY nom_familia ASC";
        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function leerUno($id_familia) {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE id_familia = :id_familia";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':id_familia', $id_familia, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function crear($data) {
        $nom_familia = $this->validarNombre($data);
        $usaTalla = $this->normalizarUsaTalla($data);
        joyeria_assert_catalog_name_unique(
            $this->getDb(),
            self::TABLE,
            'nom_familia',
            $nom_familia,
            'id_familia'
        );

        $sql = "INSERT INTO " . self::TABLE . " (nom_familia, usa_talla, activo) VALUES (:nom_familia, :usa_talla, 1)";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':nom_familia', $nom_familia, PDO::PARAM_STR);
        $stmt->bindValue(':usa_talla', $usaTalla, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    public function actualizar($id_familia, $data) {
        $nom_familia = $this->validarNombre($data);
        $usaTalla = $this->normalizarUsaTalla($data);
        joyeria_assert_catalog_name_unique(
            $this->getDb(),
            self::TABLE,
            'nom_familia',
            $nom_familia,
            'id_familia',
            (int) $id_familia
        );

        $sql = "UPDATE " . self::TABLE . " SET nom_familia = :nom_familia, usa_talla = :usa_talla WHERE id_familia = :id_familia AND activo = 1";
        $stmt = $this->getDb()->prepare($sql);
        $nom_familia = trim($nom_familia);
        $stmt->bindParam(':nom_familia', $nom_familia, PDO::PARAM_STR);
        $stmt->bindValue(':usa_talla', $usaTalla, PDO::PARAM_INT);
        $stmt->bindParam(':id_familia', $id_familia, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    private function normalizarUsaTalla($data): int {
        if (!isset($data['usa_talla'])) {
            return 0;
        }
        $valor = $data['usa_talla'];
        if (is_string($valor)) {
            $valor = trim($valor);
        }
        return in_array($valor, ['1', 1, true, 'on', 'true'], true) ? 1 : 0;
    }

    public function borrar($id_familia) {
        $sql = "UPDATE " . self::TABLE . " SET activo = 0, fecha_baja = NOW() WHERE id_familia = :id_familia";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':id_familia', $id_familia, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    private function validarNombre($data) {
        if (!isset($data['nom_familia'])) {
            throw new InvalidArgumentException('El nombre de la familia es requerido.');
        }

        $nombre = trim($data['nom_familia']);
        $nombre = strip_tags($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la familia no puede estar vacío.');
        }

        if (mb_strlen($nombre) > self::MAX_NAME_LENGTH) {
            $nombre = mb_substr($nombre, 0, self::MAX_NAME_LENGTH);
        }

        return $nombre;
    }
}