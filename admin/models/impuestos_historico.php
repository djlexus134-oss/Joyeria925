<?php
require_once __DIR__ . "/../../sistema.class.php";

class ImpuestosHistorico extends Sistema
{
    const TABLE = 'impuestos_historico';

    public function leer()
    {
        $sql = "SELECT ih.*, i.tipo_impuesto FROM " . self::TABLE . " ih INNER JOIN impuestos i ON ih.id_impuesto_FK = i.id_impuesto ORDER BY ih.fecha_inicio DESC";
        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_impuesto_historico = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function leerImpuestos()
    {
        return $this->getDb()->query("SELECT id_impuesto, tipo_impuesto FROM impuestos ORDER BY tipo_impuesto ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $idImpuesto = $this->validarId($data, 'id_impuesto_FK', 'El impuesto');
        $porcentaje = $this->validarDecimal($data, 'porcentaje', 0, 100, 'El porcentaje');
        $fechaInicio = $this->validarFecha($data, 'fecha_inicio', true);
        $fechaFin = $this->validarFecha($data, 'fecha_fin', false);
        $activo = $this->validarActivo($data);
        $observaciones = $this->textoOpcional($data, 'observaciones');

        $stmt = $this->getDb()->prepare("INSERT INTO " . self::TABLE . " (id_impuesto_FK, porcentaje, fecha_inicio, fecha_fin, activo, observaciones) VALUES (:id_impuesto, :porcentaje, :fecha_inicio, :fecha_fin, :activo, :observaciones)");
        $stmt->bindParam(':id_impuesto', $idImpuesto, PDO::PARAM_INT);
        $stmt->bindParam(':porcentaje', $porcentaje);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $fechaFin, $fechaFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':activo', $activo, PDO::PARAM_INT);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $idImpuesto = $this->validarId($data, 'id_impuesto_FK', 'El impuesto');
        $porcentaje = $this->validarDecimal($data, 'porcentaje', 0, 100, 'El porcentaje');
        $fechaInicio = $this->validarFecha($data, 'fecha_inicio', true);
        $fechaFin = $this->validarFecha($data, 'fecha_fin', false);
        $activo = $this->validarActivo($data);
        $observaciones = $this->textoOpcional($data, 'observaciones');

        $stmt = $this->getDb()->prepare("UPDATE " . self::TABLE . " SET id_impuesto_FK = :id_impuesto, porcentaje = :porcentaje, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, activo = :activo, observaciones = :observaciones WHERE id_impuesto_historico = :id");
        $stmt->bindParam(':id_impuesto', $idImpuesto, PDO::PARAM_INT);
        $stmt->bindParam(':porcentaje', $porcentaje);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $fechaFin, $fechaFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':activo', $activo, PDO::PARAM_INT);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id)
    {
        $stmt = $this->getDb()->prepare("DELETE FROM " . self::TABLE . " WHERE id_impuesto_historico = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function validarId($data, $campo, $label)
    {
        if (!isset($data[$campo]) || intval($data[$campo]) <= 0) {
            throw new InvalidArgumentException($label . ' es requerido.');
        }
        return intval($data[$campo]);
    }

    private function validarDecimal($data, $campo, $min, $max, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es requerido.');
        }
        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException($label . ' debe ser numerico.');
        }
        $valor = (float) $data[$campo];
        if ($valor < $min || $valor > $max) {
            throw new InvalidArgumentException($label . ' debe estar entre ' . $min . ' y ' . $max . '.');
        }
        return $valor;
    }

    private function validarFecha($data, $campo, $requerido)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            if ($requerido) {
                throw new InvalidArgumentException('La fecha es requerida para ' . $campo . '.');
            }
            return null;
        }
        return trim((string) $data[$campo]);
    }

    private function validarActivo($data)
    {
        if (!isset($data['activo'])) {
            return 1;
        }
        return intval($data['activo']) === 0 ? 0 : 1;
    }

    private function textoOpcional($data, $campo)
    {
        if (!isset($data[$campo])) {
            return null;
        }
        $texto = trim((string) $data[$campo]);
        return $texto === '' ? null : $texto;
    }
}
