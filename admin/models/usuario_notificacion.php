<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class UsuarioNotificacion extends Sistema
{
    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT un.id_usuario_FK,
                       un.id_notificacion_FK,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS nombre_usuario,
                       u.correo,
                       n.mensaje,
                       n.leida,
                       n.fecha_envio
                FROM usuario_notificacion un
                INNER JOIN usuarios u ON un.id_usuario_FK = u.id_usuario
                INNER JOIN notificaciones n ON un.id_notificacion_FK = n.id_notificacion
                WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (
                CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) LIKE :busq
                OR u.correo LIKE :busq2 OR n.mensaje LIKE :busq3 OR CAST(n.id_notificacion AS CHAR) LIKE :busq4
            )";
        }
        $sql .= " ORDER BY n.fecha_envio DESC, un.id_usuario_FK ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asignar($data)
    {
        $idUsuario = $this->validarEnteroPositivo($data, 'id_usuario_FK', 'El usuario');
        $idNotificacion = $this->validarEnteroPositivo($data, 'id_notificacion_FK', 'La notificacion');

        $stmt = $this->getDb()->prepare(
            "INSERT INTO usuario_notificacion (id_usuario_FK, id_notificacion_FK)
             VALUES (:id_usuario_FK, :id_notificacion_FK)"
        );

        $stmt->bindValue(':id_usuario_FK', $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':id_notificacion_FK', $idNotificacion, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function desvincular($idUsuario, $idNotificacion)
    {
        $stmt = $this->getDb()->prepare(
            "DELETE FROM usuario_notificacion
             WHERE id_usuario_FK = :id_usuario_FK AND id_notificacion_FK = :id_notificacion_FK"
        );

        $stmt->bindValue(':id_usuario_FK', (int) $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':id_notificacion_FK', (int) $idNotificacion, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function obtenerUsuariosActivos()
    {
        $sql = "SELECT id_usuario,
                       CONCAT(nombre, ' ', primer_apellido, COALESCE(CONCAT(' ', segundo_apellido), '')) AS nombre_completo,
                       correo
                FROM usuarios
                WHERE COALESCE(activo, 1) = 1
                ORDER BY nombre ASC, primer_apellido ASC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerNotificaciones()
    {
        $sql = "SELECT id_notificacion, mensaje, leida, fecha_envio
                FROM notificaciones
                ORDER BY fecha_envio DESC, id_notificacion DESC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validarEnteroPositivo($data, $campo, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatoria.');
        }

        $valor = (int) $data[$campo];
        if ($valor <= 0) {
            throw new InvalidArgumentException($label . ' no es valida.');
        }

        return $valor;
    }
}
