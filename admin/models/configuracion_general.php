<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/list_search.php";
require_once __DIR__ . "/../includes/configuracion_plantilla_defaults.php";
require_once __DIR__ . "/../includes/SpeiDepositoPayloadBuilder.php";

class ConfiguracionGeneral extends Sistema
{
    const TABLE = 'configuracion_general';
    private $tiposValidos = ['INT', 'DECIMAL', 'BOOLEAN', 'STRING', 'JSON'];

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (
                clave LIKE :busq OR IFNULL(valor, '') LIKE :busq2 OR tipo LIKE :busq3
                OR IFNULL(descripcion, '') LIKE :busq4
            )";
        }
        $sql .= " ORDER BY clave ASC";
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

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_configuracion_global = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ID de forma de pago por defecto (clave id_forma_pago_default en configuracion_general).
     * Solo devuelve un valor si existe en forma_pago y esta activa.
     */
    public function resolverIdFormaPagoDefault(): ?int
    {
        $map = $this->leerPorClaves(['id_forma_pago_default']);
        if (!isset($map['id_forma_pago_default'])) {
            return null;
        }
        $id = (int) $map['id_forma_pago_default'];
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->getDb()->prepare(
            'SELECT 1 FROM forma_pago WHERE id_forma_pago = :id AND activo = 1 LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return $id;
        }

        return null;
    }

    /**
     * ID de impuesto por defecto (clave id_impuesto_default en configuracion_general).
     * Solo devuelve un valor si existe en la tabla impuestos.
     */
    /**
     * Configuración del patrón y fuente de trabajo para PDFs de contratos laborales.
     */
    public function leerConfigContratoLaboral(): array
    {
        $claves = [
            'contrato_ciudad',
            'contrato_domicilio_fuente_trabajo',
            'contrato_nombre_patron',
            'contrato_tribunal_ciudad',
            'contrato_jornada_horas_semanales',
            'contrato_nacionalidad_default',
        ];
        $merged = $this->leerConDefaults($claves);

        return [
            'ciudad' => (string) $merged['contrato_ciudad'],
            'domicilio_fuente_trabajo' => (string) $merged['contrato_domicilio_fuente_trabajo'],
            'nombre_patron' => (string) $merged['contrato_nombre_patron'],
            'tribunal_ciudad' => (string) $merged['contrato_tribunal_ciudad'],
            'jornada_horas_semanales' => max(1, (int) $merged['contrato_jornada_horas_semanales']),
            'nacionalidad_default' => (string) $merged['contrato_nacionalidad_default'],
        ];
    }

    public function leerConDefaults(array $claves, ?array $defaults = null): array
    {
        $defaults = $defaults ?? configuracion_plantilla_defaults();
        $filtrados = array_intersect_key($defaults, array_flip($claves));
        $map = $this->leerPorClaves($claves);
        foreach ($map as $clave => $valor) {
            if ($valor !== null && $valor !== '') {
                $filtrados[$clave] = $valor;
            }
        }

        return $filtrados;
    }

    public function leerDatosDepositoSpei(): array
    {
        $map = $this->leerConDefaults([
            'spei_deposito_habilitado',
            'spei_beneficiario',
            'spei_banco',
            'spei_clabe',
            'spei_instrucciones',
            'spei_referencia_prefijo',
        ]);

        $clabe = SpeiDepositoPayloadBuilder::normalizarClabe((string) ($map['spei_clabe'] ?? ''));
        $habilitadoCfg = !empty($map['spei_deposito_habilitado']);
        $clabeValida = $clabe !== '' && SpeiDepositoPayloadBuilder::validarClabe($clabe);

        return [
            'habilitado' => $habilitadoCfg && $clabeValida,
            'beneficiario' => trim((string) ($map['spei_beneficiario'] ?? '')),
            'banco' => trim((string) ($map['spei_banco'] ?? '')),
            'clabe' => $clabe,
            'instrucciones' => trim((string) ($map['spei_instrucciones'] ?? '')),
            'referencia_prefijo' => trim((string) ($map['spei_referencia_prefijo'] ?? 'VENTA')) ?: 'VENTA',
        ];
    }

    public function resolverIdImpuestoDefault(): ?int
    {
        $map = $this->leerPorClaves(['id_impuesto_default']);
        if (!isset($map['id_impuesto_default'])) {
            return null;
        }
        $id = (int) $map['id_impuesto_default'];
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->getDb()->prepare(
            'SELECT 1 FROM impuestos WHERE id_impuesto = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return $id;
        }

        return null;
    }

    public function leerPorClaves(array $claves): array
    {
        $clavesLimpias = [];
        foreach ($claves as $clave) {
            $valor = trim((string) $clave);
            if ($valor !== '') {
                $clavesLimpias[] = $valor;
            }
        }
        if (empty($clavesLimpias)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clavesLimpias), '?'));
        $stmt = $this->getDb()->prepare(
            "SELECT cg.clave, cg.valor, cg.tipo
             FROM " . self::TABLE . " cg
             INNER JOIN (
                 SELECT clave, MAX(id_configuracion_global) AS max_id
                 FROM " . self::TABLE . "
                 WHERE clave IN ($placeholders)
                 GROUP BY clave
             ) ult ON ult.max_id = cg.id_configuracion_global
             WHERE cg.clave IN ($placeholders)"
        );
        $n = count($clavesLimpias);
        foreach ($clavesLimpias as $idx => $clave) {
            $stmt->bindValue($idx + 1, $clave, PDO::PARAM_STR);
            $stmt->bindValue($n + $idx + 1, $clave, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $clave = (string) ($row['clave'] ?? '');
            if ($clave === '') {
                continue;
            }
            $map[$clave] = $this->parsearValorTipado(
                (string) ($row['valor'] ?? ''),
                (string) ($row['tipo'] ?? 'STRING')
            );
        }
        return $map;
    }

    public function crear($data)
    {
        $clave = $this->textoOpcionalConLimite($data, 'clave', 50);
        $valor = $this->validarTexto($data, 'valor', 255, 'El valor');
        $tipo = $this->validarTipo($data);
        $descripcion = $this->textoOpcional($data, 'descripcion');

        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("INSERT INTO " . self::TABLE . " (clave, valor, tipo, descripcion, fecha_actualizacion) VALUES (:clave, :valor, :tipo, :descripcion, NOW())");
        $stmt->bindValue(':clave', $clave, $clave === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':valor', $valor, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $clave = $this->textoOpcionalConLimite($data, 'clave', 50);
        $valor = $this->validarTexto($data, 'valor', 255, 'El valor');
        $tipo = $this->validarTipo($data);
        $descripcion = $this->textoOpcional($data, 'descripcion');

        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("UPDATE " . self::TABLE . " SET clave = :clave, valor = :valor, tipo = :tipo, descripcion = :descripcion, fecha_actualizacion = NOW() WHERE id_configuracion_global = :id");
        $stmt->bindValue(':clave', $clave, $clave === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':valor', $valor, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id)
    {
        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("DELETE FROM " . self::TABLE . " WHERE id_configuracion_global = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function tipos()
    {
        return $this->tiposValidos;
    }

    public function guardarPorClave(string $clave, string $valor, string $tipo, ?string $descripcion = null): void
    {
        $clave = trim($clave);
        $tipo = strtoupper(trim($tipo));
        if ($clave === '') {
            throw new InvalidArgumentException('La clave es requerida.');
        }
        if (!in_array($tipo, $this->tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de configuracion no valido.');
        }

        $stmt = $this->getDb()->prepare(
            'SELECT id_configuracion_global FROM ' . self::TABLE . '
             WHERE clave = :clave
             ORDER BY id_configuracion_global DESC
             LIMIT 1'
        );
        $stmt->bindValue(':clave', $clave, PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->fetchColumn();

        auth_mysql_set_audit_vars($this->getDb());

        if ($id) {
            $this->actualizar((int) $id, [
                'clave' => $clave,
                'valor' => $valor,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
            ]);
            return;
        }

        $this->crear([
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
        ]);
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerida.');
        }
        $valor = trim((string) $data[$campo]);
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacia.');
        }
        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }
        return $valor;
    }

    private function validarTipo($data)
    {
        if (!isset($data['tipo'])) {
            throw new InvalidArgumentException('El tipo es requerido.');
        }
        $tipo = strtoupper(trim((string) $data['tipo']));
        if (!in_array($tipo, $this->tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de configuracion no valido.');
        }
        return $tipo;
    }

    private function textoOpcional($data, $campo)
    {
        if (!isset($data[$campo])) {
            return null;
        }
        $texto = trim((string) $data[$campo]);
        return $texto === '' ? null : $texto;
    }

    private function textoOpcionalConLimite($data, $campo, $max)
    {
        $texto = $this->textoOpcional($data, $campo);
        if ($texto === null) {
            return null;
        }
        if (mb_strlen($texto) > $max) {
            return mb_substr($texto, 0, $max);
        }
        return $texto;
    }

    private function parsearValorTipado(string $valor, string $tipo)
    {
        $tipo = strtoupper(trim($tipo));
        switch ($tipo) {
            case 'INT':
                return (int) $valor;
            case 'DECIMAL':
                return is_numeric($valor) ? number_format((float) $valor, 2, '.', '') : '0.00';
            case 'BOOLEAN':
                return in_array(strtolower($valor), ['1', 'true', 'si', 'yes'], true);
            case 'JSON':
                $decoded = json_decode($valor, true);
                return $decoded === null && strtolower(trim($valor)) !== 'null' ? $valor : $decoded;
            case 'STRING':
            default:
                return $valor;
        }
    }
}
