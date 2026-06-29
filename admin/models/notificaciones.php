<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Notificaciones extends Sistema
{
    const TABLE = 'notificaciones';

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (mensaje LIKE :busq OR CAST(id_notificacion AS CHAR) LIKE :busq2)";
        }
        $sql .= " ORDER BY fecha_envio DESC, id_notificacion DESC";
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
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_notificacion = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $mensaje = $this->validarMensaje($data, 'mensaje');
        $leida = $this->validarLeida($data['leida'] ?? 0);

        $stmt = $this->getDb()->prepare(
            "INSERT INTO " . self::TABLE . " (mensaje, leida) VALUES (:mensaje, :leida)"
        );
        $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
        $stmt->bindValue(':leida', $leida, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $mensaje = $this->validarMensaje($data, 'mensaje');
        $leida = $this->validarLeida($data['leida'] ?? 0);

        $stmt = $this->getDb()->prepare(
            "UPDATE " . self::TABLE . " SET mensaje = :mensaje, leida = :leida WHERE id_notificacion = :id"
        );
        $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
        $stmt->bindValue(':leida', $leida, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id)
    {
        $stmt = $this->getDb()->prepare("DELETE FROM " . self::TABLE . " WHERE id_notificacion = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function validarMensaje($data, $campo)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException('El mensaje es obligatorio.');
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException('El mensaje no puede estar vacio.');
        }

        if (mb_strlen($valor) > 65535) {
            $valor = mb_substr($valor, 0, 65535);
        }

        return $valor;
    }

    private function validarLeida($valor)
    {
        $v = (int) $valor;
        return $v === 1 ? 1 : 0;
    }
}
