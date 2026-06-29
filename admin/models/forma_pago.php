<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";
require_once __DIR__ . "/../includes/catalog_duplicate_guard.php";

class FormaPago extends Sistema
{
    const TABLE = 'forma_pago';
    const MAX_NAME_LENGTH = 40;

    private ?bool $cacheColumnaEsEfectivo = null;
    private ?bool $cacheColumnaClaveSat = null;

    private function tablaTieneEsEfectivo(): bool
    {
        if ($this->cacheColumnaEsEfectivo !== null) {
            return $this->cacheColumnaEsEfectivo;
        }
        try {
            $stmt = $this->getDb()->query("SHOW COLUMNS FROM " . self::TABLE . " LIKE 'es_efectivo'");
            $this->cacheColumnaEsEfectivo = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->cacheColumnaEsEfectivo = false;
        }

        return $this->cacheColumnaEsEfectivo;
    }

    private function tablaTieneClaveSat(): bool
    {
        if ($this->cacheColumnaClaveSat !== null) {
            return $this->cacheColumnaClaveSat;
        }
        try {
            $stmt = $this->getDb()->query("SHOW COLUMNS FROM " . self::TABLE . " LIKE 'clave_sat'");
            $this->cacheColumnaClaveSat = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->cacheColumnaClaveSat = false;
        }

        return $this->cacheColumnaClaveSat;
    }

    private function parseEsEfectivo(array $data): int
    {
        $v = $data['es_efectivo'] ?? '0';

        return ((string) $v === '1' || (int) $v === 1) ? 1 : 0;
    }

    private function parseClaveSat(array $data): ?string
    {
        $clave = trim((string) ($data['clave_sat'] ?? ''));
        if ($clave === '') {
            return null;
        }
        return mb_substr($clave, 0, 2);
    }

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE activo = 1";
        if ($pat !== null) {
            $sql .= " AND forma_pago LIKE :busq";
        }
        $sql .= " ORDER BY forma_pago ASC";
        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_forma_pago = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $nombre = $this->validarNombre($data);
        joyeria_assert_catalog_name_unique(
            $this->getDb(),
            self::TABLE,
            'forma_pago',
            $nombre,
            'id_forma_pago'
        );
        $esEfectivo = $this->parseEsEfectivo($data);
        $claveSat = $this->parseClaveSat($data);
        $cols = ['forma_pago', 'activo'];
        $vals = [':nombre', '1'];
        if ($this->tablaTieneEsEfectivo()) {
            $cols[] = 'es_efectivo';
            $vals[] = ':es_efectivo';
        }
        if ($this->tablaTieneClaveSat()) {
            $cols[] = 'clave_sat';
            $vals[] = ':clave_sat';
        }
        $stmt = $this->getDb()->prepare(
            'INSERT INTO ' . self::TABLE . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
        );
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        if ($this->tablaTieneEsEfectivo()) {
            $stmt->bindValue(':es_efectivo', $esEfectivo, PDO::PARAM_INT);
        }
        if ($this->tablaTieneClaveSat()) {
            $stmt->bindValue(':clave_sat', $claveSat, $claveSat === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $nombre = $this->validarNombre($data);
        joyeria_assert_catalog_name_unique(
            $this->getDb(),
            self::TABLE,
            'forma_pago',
            $nombre,
            'id_forma_pago',
            (int) $id
        );
        $esEfectivo = $this->parseEsEfectivo($data);
        $claveSat = $this->parseClaveSat($data);
        $sets = ['forma_pago = :nombre'];
        if ($this->tablaTieneEsEfectivo()) {
            $sets[] = 'es_efectivo = :es_efectivo';
        }
        if ($this->tablaTieneClaveSat()) {
            $sets[] = 'clave_sat = :clave_sat';
        }
        $stmt = $this->getDb()->prepare(
            'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE id_forma_pago = :id AND activo = 1'
        );
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        if ($this->tablaTieneEsEfectivo()) {
            $stmt->bindValue(':es_efectivo', $esEfectivo, PDO::PARAM_INT);
        }
        if ($this->tablaTieneClaveSat()) {
            $stmt->bindValue(':clave_sat', $claveSat, $claveSat === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function tieneCampoEsEfectivo(): bool
    {
        return $this->tablaTieneEsEfectivo();
    }

    public function tieneCampoClaveSat(): bool
    {
        return $this->tablaTieneClaveSat();
    }

    public function borrar($id, $id_usuario_baja = null)
    {
        $id_usuario_baja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $stmt = $this->getDb()->prepare("UPDATE " . self::TABLE . " SET activo = 0, fecha_baja = NOW(), id_usuario_baja = :id_usuario_baja WHERE id_forma_pago = :id");
        $stmt->bindValue(':id_usuario_baja', $id_usuario_baja, $id_usuario_baja === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function validarNombre($data)
    {
        if (!isset($data['forma_pago'])) {
            throw new InvalidArgumentException('La forma de pago es requerida.');
        }

        $nombre = trim(strip_tags((string) $data['forma_pago']));
        if ($nombre === '') {
            throw new InvalidArgumentException('La forma de pago no puede estar vacia.');
        }

        if (mb_strlen($nombre) > self::MAX_NAME_LENGTH) {
            $nombre = mb_substr($nombre, 0, self::MAX_NAME_LENGTH);
        }

        return $nombre;
    }
}
