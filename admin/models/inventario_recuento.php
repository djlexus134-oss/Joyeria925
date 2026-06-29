<?php

require_once __DIR__ . '/../../sistema.class.php';

/**
 * Recuento físico vs stock disponible por tienda, con cabecera en auditorias_inventario
 * y líneas en auditoria_detalle.
 */
class InventarioRecuento extends Sistema
{
    public const META_KEY = 'inventario_recuento_v1';

    public function obtenerIdEmpleadoPorUsuario(int $idUsuario): ?int
    {
        if ($idUsuario <= 0) {
            return null;
        }
        $stmt = $this->getDb()->prepare(
            'SELECT id_empleado FROM empleados WHERE id_usuario_FK = :u AND activo = 1 LIMIT 1'
        );
        $stmt->bindValue(':u', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        $v = $stmt->fetchColumn();
        return $v !== false ? (int) $v : null;
    }

    public function listarTiendasActivas(): array
    {
        return $this->getDb()->query(
            'SELECT id_tienda, nom_tienda FROM tiendas WHERE activo = 1 ORDER BY nom_tienda ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarFamiliasActivas(): array
    {
        return $this->getDb()->query(
            'SELECT id_familia, nom_familia FROM familias WHERE activo = 1 ORDER BY nom_familia ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeFamiliaActiva(int $idFamilia): bool
    {
        if ($idFamilia <= 0) {
            return false;
        }
        $stmt = $this->getDb()->prepare(
            'SELECT 1 FROM familias WHERE id_familia = :id AND activo = 1 LIMIT 1'
        );
        $stmt->bindValue(':id', $idFamilia, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function construirObservacionesMeta(int $idTienda, int $idUsuario, int $idFamilia = 0): string
    {
        $payload = [
            'meta' => self::META_KEY,
            'id_tienda' => $idTienda,
            'id_usuario' => $idUsuario,
            'id_familia' => max(0, $idFamilia),
        ];
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{id_tienda: int, id_usuario: int, id_familia: int}|null
     */
    public function parsearMetaCabecera(?string $observaciones): ?array
    {
        if ($observaciones === null || trim($observaciones) === '') {
            return null;
        }
        try {
            $dec = json_decode($observaciones, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return null;
        }
        if (!is_array($dec) || ($dec['meta'] ?? '') !== self::META_KEY) {
            return null;
        }
        $idTienda = (int) ($dec['id_tienda'] ?? 0);
        $idUsuario = (int) ($dec['id_usuario'] ?? 0);
        if ($idTienda <= 0 || $idUsuario <= 0) {
            return null;
        }
        return [
            'id_tienda' => $idTienda,
            'id_usuario' => $idUsuario,
            'id_familia' => max(0, (int) ($dec['id_familia'] ?? 0)),
        ];
    }

    public function obtenerCabecera(int $idAuditoria): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id_auditoria, fecha_inicio, fecha_cierre, id_empleado_FK, estado, observaciones
             FROM auditorias_inventario WHERE id_auditoria = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $idAuditoria, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function buscarAuditoriaAbiertaPorEmpleado(int $idEmpleado): ?array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id_auditoria, fecha_inicio, fecha_cierre, id_empleado_FK, estado, observaciones
             FROM auditorias_inventario
             WHERE id_empleado_FK = :e AND estado = 'abierta'
             ORDER BY id_auditoria DESC LIMIT 1"
        );
        $stmt->bindValue(':e', $idEmpleado, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearCabecera(int $idEmpleado, int $idTienda, int $idUsuario, int $idFamilia = 0): int
    {
        if ($idEmpleado <= 0 || $idTienda <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Datos de auditoría no válidos.');
        }
        $obs = $this->construirObservacionesMeta($idTienda, $idUsuario, $idFamilia);
        $stmt = $this->getDb()->prepare(
            "INSERT INTO auditorias_inventario (id_empleado_FK, estado, observaciones)
             VALUES (:emp, 'abierta', :obs)"
        );
        $stmt->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
        $stmt->bindValue(':obs', $obs, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->getDb()->lastInsertId();
    }

    public function contarEsperadosPorTienda(int $idTienda, int $idFamilia = 0): int
    {
        $sql = "SELECT COUNT(*) FROM piezas_stock ps
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK";
        if ($idFamilia > 0) {
            $sql .= ' INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK';
        }
        $sql .= " WHERE ps.activo = 1 AND ps.estado = 'disponible' AND p.id_tienda_FK = :t";
        if ($idFamilia > 0) {
            $sql .= ' AND sf.id_familia_FK = :fam';
        }
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':t', $idTienda, PDO::PARAM_INT);
        if ($idFamilia > 0) {
            $stmt->bindValue(':fam', $idFamilia, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function contarDetalle(int $idAuditoria): int
    {
        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) FROM auditoria_detalle WHERE id_auditoria_FK = :a'
        );
        $stmt->bindValue(':a', $idAuditoria, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resuelve una pieza de stock disponible en la tienda por código de barras, auxiliar o id numérico.
     *
     * @return array<string, mixed>|null
     */
    public function resolverCodigoEnTienda(string $codigoCrudo, int $idTienda, int $idFamilia = 0): ?array
    {
        $codigo = trim($codigoCrudo);
        if ($codigo === '' || $idTienda <= 0) {
            return null;
        }

        $sql = "SELECT ps.id_pieza_stock,
                       ps.codigo_auxiliar,
                       ps.codigo_barras,
                       ps.precio_venta,
                       ps.estado,
                       p.desc_pieza,
                       p.id_tienda_FK
                FROM piezas_stock ps
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK";
        if ($idFamilia > 0) {
            $sql .= ' INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK';
        }
        $sql .= " WHERE ps.activo = 1
                  AND ps.estado = 'disponible'
                  AND p.id_tienda_FK = :tienda";
        if ($idFamilia > 0) {
            $sql .= ' AND sf.id_familia_FK = :fam';
        }
        $sql .= " AND (
                    ps.codigo_barras = :c1
                    OR ps.codigo_auxiliar = :c2";

        $idExacto = 0;
        if (ctype_digit($codigo)) {
            $sql .= ' OR ps.id_pieza_stock = :idstock';
            $idExacto = (int) $codigo;
        }
        $sql .= ') LIMIT 1';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':tienda', $idTienda, PDO::PARAM_INT);
        if ($idFamilia > 0) {
            $stmt->bindValue(':fam', $idFamilia, PDO::PARAM_INT);
        }
        $stmt->bindValue(':c1', $codigo, PDO::PARAM_STR);
        $stmt->bindValue(':c2', $codigo, PDO::PARAM_STR);
        if ($idExacto > 0) {
            $stmt->bindValue(':idstock', $idExacto, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @throws RuntimeException si duplicado (unique)
     */
    public function agregarEscaneo(int $idAuditoria, int $idPiezaStock, string $codigoLeido): void
    {
        $stmt = $this->getDb()->prepare(
            'INSERT INTO auditoria_detalle (id_auditoria_FK, id_pieza_stock_FK, codigo_barras)
             VALUES (:a, :ps, :cb)'
        );
        $stmt->bindValue(':a', $idAuditoria, PDO::PARAM_INT);
        $stmt->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
        $stmt->bindValue(':cb', mb_substr(trim($codigoLeido), 0, 50), PDO::PARAM_STR);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000' || str_contains((string) $e->getMessage(), 'Duplicate')) {
                throw new RuntimeException('Esta pieza ya fue contada en este recuento.');
            }
            throw $e;
        }
    }

    public function cerrarAuditoria(int $idAuditoria): void
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE auditorias_inventario
             SET estado = 'cerrada', fecha_cierre = NOW()
             WHERE id_auditoria = :id AND estado = 'abierta'"
        );
        $stmt->bindValue(':id', $idAuditoria, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Stock disponible en tienda que no aparece en el detalle del recuento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarFaltantes(int $idTienda, int $idAuditoria, int $idFamilia = 0): array
    {
        $sql = "SELECT ps.id_pieza_stock,
                       ps.codigo_auxiliar,
                       ps.codigo_barras,
                       ps.precio_venta,
                       ps.estado,
                       p.desc_pieza,
                       sf.nom_sub_familia,
                       m.nom_metal
                FROM piezas_stock ps
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                WHERE ps.activo = 1
                  AND ps.estado = 'disponible'
                  AND p.id_tienda_FK = :tienda";
        if ($idFamilia > 0) {
            $sql .= ' AND sf.id_familia_FK = :fam';
        }
        $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM auditoria_detalle ad
                    WHERE ad.id_auditoria_FK = :aud
                      AND ad.id_pieza_stock_FK = ps.id_pieza_stock
                  )
                ORDER BY ps.id_pieza_stock ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':tienda', $idTienda, PDO::PARAM_INT);
        $stmt->bindValue(':aud', $idAuditoria, PDO::PARAM_INT);
        if ($idFamilia > 0) {
            $stmt->bindValue(':fam', $idFamilia, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve ids de faltantes que siguen siendo borrables (disponible, activo, misma tienda).
     *
     * @param int[] $idsPiezaStock
     * @return int[]
     */
    /**
     * Recuentos cerrados (inventario_recuento_v1) con filtros opcionales por rango de cierre y familia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarRecuentosRealizados(?string $fechaDesde, ?string $fechaHasta, int $idFamilia = 0, int $limite = 200): array
    {
        $limite = max(1, min(500, $limite));
        $sql = "SELECT ai.id_auditoria,
                       ai.fecha_inicio,
                       ai.fecha_cierre,
                       ai.estado,
                       ai.observaciones,
                       ai.id_empleado_FK,
                       (SELECT COUNT(*) FROM auditoria_detalle ad WHERE ad.id_auditoria_FK = ai.id_auditoria) AS contados,
                       CONCAT(u.nombre, ' ', u.primer_apellido,
                                COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS empleado_nombre
                FROM auditorias_inventario ai
                INNER JOIN empleados e ON e.id_empleado = ai.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                WHERE ai.estado = 'cerrada'
                  AND JSON_UNQUOTE(JSON_EXTRACT(ai.observaciones, '$.meta')) = :meta";
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $sql .= ' AND DATE(ai.fecha_cierre) >= :fd';
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $sql .= ' AND DATE(ai.fecha_cierre) <= :fh';
        }
        if ($idFamilia > 0) {
            $sql .= " AND CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ai.observaciones, '$.id_familia')), '0') AS UNSIGNED) = :fam";
        }
        $sql .= ' ORDER BY ai.fecha_cierre DESC, ai.id_auditoria DESC LIMIT ' . (int) $limite;

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':meta', self::META_KEY, PDO::PARAM_STR);
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $stmt->bindValue(':fd', $fechaDesde, PDO::PARAM_STR);
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $stmt->bindValue(':fh', $fechaHasta, PDO::PARAM_STR);
        }
        if ($idFamilia > 0) {
            $stmt->bindValue(':fam', $idFamilia, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function filtrarIdsBorrables(int $idTienda, array $idsPiezaStock, int $idFamilia = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsPiezaStock))));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT ps.id_pieza_stock
                FROM piezas_stock ps
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK";
        if ($idFamilia > 0) {
            $sql .= ' INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK';
        }
        $sql .= " WHERE ps.activo = 1
                  AND ps.estado = 'disponible'
                  AND p.id_tienda_FK = ?";
        if ($idFamilia > 0) {
            $sql .= ' AND sf.id_familia_FK = ?';
        }
        $sql .= " AND ps.id_pieza_stock IN ($placeholders)";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(1, $idTienda, PDO::PARAM_INT);
        $i = 2;
        if ($idFamilia > 0) {
            $stmt->bindValue($i, $idFamilia, PDO::PARAM_INT);
            $i++;
        }
        foreach ($ids as $id) {
            $stmt->bindValue($i, $id, PDO::PARAM_INT);
            $i++;
        }
        $stmt->execute();
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = (int) $row['id_pieza_stock'];
        }
        return $out;
    }
}
