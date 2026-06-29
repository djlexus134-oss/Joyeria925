<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class CatalogoCompra extends Sistema
{
    const TABLE = 'catalogo_compra';

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT cc.*, f.nom_familia, sf.nom_sub_familia, m.nom_metal
                FROM " . self::TABLE . " cc
                LEFT JOIN familias f ON cc.id_familia_FK = f.id_familia
                LEFT JOIN sub_familia sf ON cc.id_sub_familia_FK = sf.id_sub_familia
                LEFT JOIN metales m ON cc.id_metal_FK = m.id_metal
                WHERE cc.activo = 1";
        if ($pat !== null) {
            $sql .= " AND (
                cc.descripcion LIKE :busq OR cc.tipo LIKE :busq2 OR IFNULL(cc.observaciones, '') LIKE :busq3
                OR f.nom_familia LIKE :busq4 OR sf.nom_sub_familia LIKE :busq5 OR m.nom_metal LIKE :busq6
            )";
        }
        $sql .= " ORDER BY cc.tipo ASC, cc.descripcion ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_articulo_compra = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $tipo = $this->validarTipo($data['tipo'] ?? null);
        $descripcion = $this->validarTexto($data, 'descripcion', 255, 'La descripcion');
        $idFamilia = $this->validarEnteroOpcional($data, 'id_familia_FK');
        $idSubFamilia = $this->validarEnteroOpcional($data, 'id_sub_familia_FK');
        $idMetal = $this->validarEnteroOpcional($data, 'id_metal_FK');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 2000);

        $stmt = $this->getDb()->prepare(
            "INSERT INTO " . self::TABLE . "
             (tipo, descripcion, id_familia_FK, id_sub_familia_FK, id_metal_FK, observaciones, activo)
             VALUES
             (:tipo, :descripcion, :id_familia_FK, :id_sub_familia_FK, :id_metal_FK, :observaciones, 1)"
        );
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':id_familia_FK', $idFamilia, $idFamilia === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_sub_familia_FK', $idSubFamilia, $idSubFamilia === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_metal_FK', $idMetal, $idMetal === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $tipo = $this->validarTipo($data['tipo'] ?? null);
        $descripcion = $this->validarTexto($data, 'descripcion', 255, 'La descripcion');
        $idFamilia = $this->validarEnteroOpcional($data, 'id_familia_FK');
        $idSubFamilia = $this->validarEnteroOpcional($data, 'id_sub_familia_FK');
        $idMetal = $this->validarEnteroOpcional($data, 'id_metal_FK');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 2000);

        $stmt = $this->getDb()->prepare(
            "UPDATE " . self::TABLE . "
             SET tipo = :tipo,
                 descripcion = :descripcion,
                 id_familia_FK = :id_familia_FK,
                 id_sub_familia_FK = :id_sub_familia_FK,
                 id_metal_FK = :id_metal_FK,
                 observaciones = :observaciones
             WHERE id_articulo_compra = :id AND activo = 1"
        );
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':id_familia_FK', $idFamilia, $idFamilia === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_sub_familia_FK', $idSubFamilia, $idSubFamilia === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_metal_FK', $idMetal, $idMetal === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function borrar($id, $idUsuarioBaja = null)
    {
        $idUsuarioBaja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $stmt = $this->getDb()->prepare(
            "UPDATE " . self::TABLE . "
             SET activo = 0,
                 fecha_baja = NOW(),
                 id_usuario_baja = :id_usuario_baja
             WHERE id_articulo_compra = :id AND activo = 1"
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario_baja', $idUsuarioBaja !== null ? (int) $idUsuarioBaja : null, $idUsuarioBaja !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function obtenerCatalogos()
    {
        return [
            'familias' => $this->getDb()->query("SELECT id_familia, nom_familia FROM familias WHERE activo = 1 ORDER BY nom_familia ASC")->fetchAll(PDO::FETCH_ASSOC),
            'subfamilias' => $this->getDb()->query("SELECT id_sub_familia, nom_sub_familia FROM sub_familia WHERE activo = 1 ORDER BY nom_sub_familia ASC")->fetchAll(PDO::FETCH_ASSOC),
            'metales' => $this->getDb()->query("SELECT id_metal, nom_metal FROM metales WHERE activo = 1 ORDER BY nom_metal ASC")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function validarTipo($tipo)
    {
        $permitidos = ['pieza', 'metal', 'insumo', 'servicio'];
        $valor = trim((string) $tipo);
        if (!in_array($valor, $permitidos, true)) {
            throw new InvalidArgumentException('El tipo no es valido.');
        }

        return $valor;
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatoria.');
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacia.');
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
    }

    private function validarTextoOpcional($data, $campo, $max)
    {
        if (!isset($data[$campo])) {
            return null;
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            return null;
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
    }

    private function validarEnteroOpcional($data, $campo)
    {
        if (!isset($data[$campo])) {
            return null;
        }

        $valor = trim((string) $data[$campo]);
        if ($valor === '') {
            return null;
        }

        $entero = (int) $valor;
        return $entero > 0 ? $entero : null;
    }
}
