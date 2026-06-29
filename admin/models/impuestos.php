<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/list_search.php";
require_once __DIR__ . "/../includes/catalog_duplicate_guard.php";

class Impuestos extends Sistema
{
    const TABLE = 'impuestos';

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (tipo_impuesto LIKE :busq OR CAST(porcentaje AS CHAR) LIKE :busq2)";
        }
        $sql .= " ORDER BY tipo_impuesto ASC";
        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_impuesto = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $tipo = $this->validarTexto($data, 'tipo_impuesto', 40, 'El tipo de impuesto');
        $porcentaje = $this->validarPorcentaje($data, 'porcentaje');
        joyeria_assert_impuesto_tipo_unique($this->getDb(), $tipo);

        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("INSERT INTO " . self::TABLE . " (tipo_impuesto, porcentaje) VALUES (:tipo, :porcentaje)");
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':porcentaje', $porcentaje, $porcentaje === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $tipo = $this->validarTexto($data, 'tipo_impuesto', 40, 'El tipo de impuesto');
        $porcentaje = $this->validarPorcentaje($data, 'porcentaje');
        joyeria_assert_impuesto_tipo_unique($this->getDb(), $tipo, (int) $id);

        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("UPDATE " . self::TABLE . " SET tipo_impuesto = :tipo, porcentaje = :porcentaje WHERE id_impuesto = :id");
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':porcentaje', $porcentaje, $porcentaje === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id)
    {
        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("DELETE FROM " . self::TABLE . " WHERE id_impuesto = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerido.');
        }
        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacio.');
        }
        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }
        return $valor;
    }

    private function validarPorcentaje($data, $campo)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }
        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException('El porcentaje debe ser numerico.');
        }
        $valor = (float) $data[$campo];
        if ($valor < 0 || $valor > 100) {
            throw new InvalidArgumentException('El porcentaje debe estar entre 0 y 100.');
        }
        return (int) round($valor);
    }
}
