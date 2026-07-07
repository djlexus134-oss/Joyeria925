<?php
require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/list_search.php';

class Variantes extends Sistema
{
    public function tieneTablas(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $stmt = $this->getDb()->query("SHOW TABLES LIKE 'variante_tipos'");
            $cache = (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    public function leerTipos(?string $busqueda = null): array
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = 'SELECT vt.*,
                       (SELECT COUNT(*) FROM variante_valores vv WHERE vv.id_variante_tipo_FK = vt.id_variante_tipo AND vv.activo = 1) AS total_valores
                FROM variante_tipos vt
                WHERE vt.activo = 1';
        if ($pat !== null) {
            $sql .= ' AND (vt.nombre LIKE :busq OR vt.slug LIKE :busq2)';
        }
        $sql .= ' ORDER BY vt.id_variante_tipo ASC';

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUnoTipo(int $id): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT * FROM variante_tipos WHERE id_variante_tipo = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function leerValoresPorTipo(int $idTipo, ?string $busqueda = null): array
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = 'SELECT * FROM variante_valores WHERE id_variante_tipo_FK = :id_tipo AND activo = 1';
        if ($pat !== null) {
            $sql .= ' AND valor LIKE :busq';
        }
        $sql .= ' ORDER BY id_variante_valor ASC';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_tipo', $idTipo, PDO::PARAM_INT);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows !== []) {
            require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
            if ($this->tipoEsTalla($idTipo)) {
                return joyeria_ordenar_filas_variante_por_talla($rows);
            }

            return joyeria_ordenar_filas_variante_natural($rows);
        }

        return $rows;
    }

    private function tipoEsTalla(int $idTipo): bool
    {
        if ($idTipo <= 0) {
            return false;
        }
        $st = $this->getDb()->prepare('SELECT es_talla FROM variante_tipos WHERE id_variante_tipo = :id LIMIT 1');
        $st->bindValue(':id', $idTipo, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row && (int) ($row['es_talla'] ?? 0) === 1;
    }

    public function leerUnoValor(int $id): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT vv.*, vt.nombre AS tipo_nombre, vt.slug AS tipo_slug, vt.es_talla
             FROM variante_valores vv
             INNER JOIN variante_tipos vt ON vt.id_variante_tipo = vv.id_variante_tipo_FK
             WHERE vv.id_variante_valor = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{tipos: list<array<string, mixed>>}
     */
    public function listarCatalogoParaSelect(): array
    {
        if (!$this->tieneTablas()) {
            return ['tipos' => []];
        }

        $tipos = $this->leerTipos();
        foreach ($tipos as &$tipo) {
            $tipo['valores'] = $this->leerValoresPorTipo((int) $tipo['id_variante_tipo']);
        }
        unset($tipo);

        return ['tipos' => $tipos];
    }

    public function crearTipo(array $data): int
    {
        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre del tipo');
        $slug = $this->normalizarSlug($data['slug'] ?? $nombre);
        $esTalla = $this->normalizarEsTalla($data);

        $stmt = $this->getDb()->prepare(
            'INSERT INTO variante_tipos (nombre, slug, es_talla, orden, activo)
             VALUES (:nombre, :slug, :es_talla, 0, 1)'
        );
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':es_talla', $esTalla, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->getDb()->lastInsertId();
    }

    public function actualizarTipo(int $id, array $data): int
    {
        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre del tipo');
        $slug = $this->normalizarSlug($data['slug'] ?? $nombre);
        $esTalla = $this->normalizarEsTalla($data);

        $stmt = $this->getDb()->prepare(
            'UPDATE variante_tipos
             SET nombre = :nombre, slug = :slug, es_talla = :es_talla
             WHERE id_variante_tipo = :id AND activo = 1'
        );
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':es_talla', $esTalla, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function borrarTipo(int $id): int
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE variante_tipos SET activo = 0 WHERE id_variante_tipo = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function crearValor(array $data): int
    {
        $idTipo = $this->validarEnteroPositivo($data, 'id_variante_tipo_FK', 'El tipo de variante');
        if (!$this->leerUnoTipo($idTipo)) {
            throw new InvalidArgumentException('El tipo de variante no existe.');
        }

        $valor = $this->validarTexto($data, 'valor', 40, 'El valor');

        $stmt = $this->getDb()->prepare(
            'INSERT INTO variante_valores (id_variante_tipo_FK, valor, orden, activo)
             VALUES (:id_tipo, :valor, 0, 1)
             ON DUPLICATE KEY UPDATE activo = 1'
        );
        $stmt->bindValue(':id_tipo', $idTipo, PDO::PARAM_INT);
        $stmt->bindValue(':valor', $valor, PDO::PARAM_STR);
        $stmt->execute();

        $lastId = (int) $this->getDb()->lastInsertId();
        if ($lastId > 0) {
            return $lastId;
        }

        $existing = $this->getDb()->prepare(
            'SELECT id_variante_valor FROM variante_valores
             WHERE id_variante_tipo_FK = :id_tipo AND valor = :valor LIMIT 1'
        );
        $existing->bindValue(':id_tipo', $idTipo, PDO::PARAM_INT);
        $existing->bindValue(':valor', $valor, PDO::PARAM_STR);
        $existing->execute();

        return (int) $existing->fetchColumn();
    }

    public function borrarValor(int $id): int
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE variante_valores SET activo = 0 WHERE id_variante_valor = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function normalizarSlug(string $raw): string
    {
        $slug = mb_strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug) ?? '';
        $slug = trim($slug, '_');
        if ($slug === '') {
            throw new InvalidArgumentException('El identificador del tipo no es valido.');
        }
        if (mb_strlen($slug) > 40) {
            $slug = mb_substr($slug, 0, 40);
        }

        return $slug;
    }

    private function normalizarEsTalla(array $data): int
    {
        if (!isset($data['es_talla'])) {
            return 0;
        }

        return !empty($data['es_talla']) && (string) $data['es_talla'] !== '0' ? 1 : 0;
    }

    private function validarTexto(array $data, string $campo, int $max, string $label): string
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

    private function validarEnteroPositivo(array $data, string $campo, string $label): int
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerido.');
        }
        $valor = (int) $data[$campo];
        if ($valor <= 0) {
            throw new InvalidArgumentException($label . ' no es valido.');
        }

        return $valor;
    }
}
