<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/list_search.php';

/**
 * Franjas HTML del catálogo público / cliente (marketing), distintas de promociones (descuento operativo).
 */
class PromocionesBanner extends Sistema
{
    public function leer(?string $busqueda = null): array
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT pb.*,
                       p.desc_pieza AS desc_pieza_imagen
                FROM promociones_banner pb
                LEFT JOIN piezas p ON p.id_pieza = pb.id_pieza_fk
                WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (
                pb.titulo LIKE :busq OR pb.texto LIKE :busq2 OR IFNULL(pb.eyebrow, '') LIKE :busq3
                OR CAST(pb.id_promocion_banner AS CHAR) LIKE :busq4
                OR pb.variante LIKE :busq5
            )";
        }
        $sql .= " ORDER BY pb.orden ASC, pb.id_promocion_banner ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno(int $id): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT * FROM promociones_banner WHERE id_promocion_banner = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param 'visitante'|'cliente' $audiencia
     * @return array<int, array<string, mixed>>
     */
    public function listarActivosParaFrontend(string $audiencia): array
    {
        $campoAud = ($audiencia === 'cliente') ? 'visible_cliente' : 'visible_visitante';

        $sql = "SELECT id_promocion_banner, variante, eyebrow, titulo, texto, cta_label, cta_href,
                       fuente_imagen, id_pieza_fk, orden
                FROM promociones_banner
                WHERE activo = 1
                  AND {$campoAud} = 1
                  AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
                  AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                ORDER BY orden ASC, id_promocion_banner ASC";

        $stmt = $this->getDb()->query($sql);
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $rows ?: [];
    }

    /**
     * @param 'visitante'|'cliente' $audiencia
     * @return array<int, array<string, mixed>>
     */
    public function listarActivosParaTicker(string $audiencia): array
    {
        $campoAud = ($audiencia === 'cliente') ? 'visible_cliente' : 'visible_visitante';

        $sql = "SELECT id_promocion_banner, eyebrow, titulo, cta_label, fecha_inicio, fecha_fin,
                       ticker_segmentos, orden
                FROM promociones_banner
                WHERE activo = 1
                  AND visible_ticker = 1
                  AND {$campoAud} = 1
                  AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
                  AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                ORDER BY orden ASC, id_promocion_banner ASC";

        $stmt = $this->getDb()->query($sql);
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $rows ?: [];
    }

    /**
     * @param 'visitante'|'cliente' $audiencia
     * @return array<int, array<string, mixed>>
     */
    public function listarActivosParaBarraInferior(string $audiencia): array
    {
        $campoAud = ($audiencia === 'cliente') ? 'visible_cliente' : 'visible_visitante';

        $sql = "SELECT id_promocion_banner, eyebrow, titulo, cta_label, fecha_inicio, fecha_fin,
                       ticker_segmentos, orden
                FROM promociones_banner
                WHERE activo = 1
                  AND visible_barra_inferior = 1
                  AND {$campoAud} = 1
                  AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
                  AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                ORDER BY orden ASC, id_promocion_banner ASC";

        $stmt = $this->getDb()->query($sql);
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $rows ?: [];
    }

    public function obtenerPiezasOpciones(): array
    {
        $sql = "SELECT p.id_pieza, p.desc_pieza, sf.nom_sub_familia, m.nom_metal
                FROM piezas p
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                INNER JOIN imagenes_piezas img ON img.id_pieza_FK = p.id_pieza AND img.es_principal = 1
                WHERE p.activo = 1
                ORDER BY p.desc_pieza ASC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crear(array $data): int
    {
        $orden = isset($data['orden']) ? max(0, (int) $data['orden']) : 0;
        $visibleVisitante = !empty($data['visible_visitante']) ? 1 : 0;
        $visibleCliente = !empty($data['visible_cliente']) ? 1 : 0;
        $visibleTicker = !empty($data['visible_ticker']) ? 1 : 0;
        $visibleBarraInferior = !empty($data['visible_barra_inferior']) ? 1 : 0;
        $esBannerBarra = $visibleTicker === 1 || $visibleBarraInferior === 1;

        if ($esBannerBarra) {
            $titulo = $this->validarTextoOpcional($data, 'titulo', 255);
            $texto = $this->validarTextoLargoOpcional($data, 'texto');
        } else {
            $titulo = $this->validarTexto($data, 'titulo', 255, 'Titulo');
            $texto = $this->validarTextoLargo($data, 'texto', 'Texto');
        }

        $variante = isset($data['variante']) ? strtolower(trim(strip_tags((string) $data['variante']))) : '';
        $variante = $variante !== '' ? $variante : 'mayoreo';
        $this->assertVariante($variante);
        $this->assertFuenteImagen(isset($data['fuente_imagen']) ? (string) $data['fuente_imagen'] : '');
        $fuente = strtolower(trim((string) ($data['fuente_imagen'] ?? 'ninguna')));

        $eyebrow = $this->validarTextoLibreOpcional($data, 'eyebrow', 255);
        $ctaLabel = $this->validarTextoLibreOpcional($data, 'cta_label', 120);
        $ctaHref = $this->validarTextoLibreOpcional($data, 'cta_href', 512);

        $idPieza = $this->validarEnteroOpcional($data, 'id_pieza_fk');
        if ($fuente === 'pieza_fija' && $idPieza === null) {
            throw new InvalidArgumentException('Con imagen desde pieza fija debes seleccionar una pieza.');
        }
        if ($fuente !== 'pieza_fija') {
            $idPieza = null;
        }

        $tickerSegmentos = $this->validarTextoLibreOpcional($data, 'ticker_segmentos', 1024);

        [$fecIni, $fecFin] = $this->validarVentanaOpcional($data);

        $stmt = $this->getDb()->prepare(
            'INSERT INTO promociones_banner
            (activo, orden, visible_visitante, visible_cliente, visible_ticker, visible_barra_inferior, ticker_segmentos,
             variante, eyebrow, titulo, texto,
             cta_label, cta_href, fuente_imagen, id_pieza_fk, fecha_inicio, fecha_fin)
             VALUES (1, :orden, :vv, :vc, :vt, :vbi, :tseg,
             :var, :eyebrow, :titulo, :texto,
             :ctal, :ctah, :fuente, :idp, :fecha_i, :fecha_f)'
        );
        $stmt->bindValue(':orden', $orden, PDO::PARAM_INT);
        $stmt->bindValue(':vv', $visibleVisitante, PDO::PARAM_INT);
        $stmt->bindValue(':vc', $visibleCliente, PDO::PARAM_INT);
        $stmt->bindValue(':vt', $visibleTicker, PDO::PARAM_INT);
        $stmt->bindValue(':vbi', $visibleBarraInferior, PDO::PARAM_INT);
        $stmt->bindValue(':tseg', $tickerSegmentos, $tickerSegmentos === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':var', $variante, PDO::PARAM_STR);
        $stmt->bindValue(':eyebrow', $eyebrow, $eyebrow === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':texto', $texto, PDO::PARAM_STR);
        $stmt->bindValue(':ctal', $ctaLabel, $ctaLabel === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ctah', $ctaHref, $ctaHref === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':fuente', $fuente, PDO::PARAM_STR);
        $stmt->bindValue(':idp', $idPieza, $idPieza === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':fecha_i', $fecIni, $fecIni === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':fecha_f', $fecFin, $fecFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->getDb()->lastInsertId();
    }

    public function actualizar(int $id, array $data): void
    {
        if ($this->leerUno($id) === null) {
            throw new InvalidArgumentException('Banner no encontrado.');
        }

        $orden = isset($data['orden']) ? max(0, (int) $data['orden']) : 0;
        $activo = !empty($data['activo']) ? 1 : 0;
        $visibleVisitante = !empty($data['visible_visitante']) ? 1 : 0;
        $visibleCliente = !empty($data['visible_cliente']) ? 1 : 0;
        $visibleTicker = !empty($data['visible_ticker']) ? 1 : 0;
        $visibleBarraInferior = !empty($data['visible_barra_inferior']) ? 1 : 0;
        $esBannerBarra = $visibleTicker === 1 || $visibleBarraInferior === 1;

        if ($esBannerBarra) {
            $titulo = $this->validarTextoOpcional($data, 'titulo', 255);
            $texto = $this->validarTextoLargoOpcional($data, 'texto');
        } else {
            $titulo = $this->validarTexto($data, 'titulo', 255, 'Titulo');
            $texto = $this->validarTextoLargo($data, 'texto', 'Texto');
        }

        $variante = isset($data['variante']) ? strtolower(trim(strip_tags((string) $data['variante']))) : '';
        $variante = $variante !== '' ? $variante : 'mayoreo';
        $this->assertVariante($variante);
        $this->assertFuenteImagen(isset($data['fuente_imagen']) ? (string) $data['fuente_imagen'] : '');
        $fuente = strtolower(trim((string) ($data['fuente_imagen'] ?? 'ninguna')));

        $eyebrow = $this->validarTextoLibreOpcional($data, 'eyebrow', 255);
        $ctaLabel = $this->validarTextoLibreOpcional($data, 'cta_label', 120);
        $ctaHref = $this->validarTextoLibreOpcional($data, 'cta_href', 512);

        $idPieza = $this->validarEnteroOpcional($data, 'id_pieza_fk');
        if ($fuente === 'pieza_fija' && $idPieza === null) {
            throw new InvalidArgumentException('Con imagen desde pieza fija debes seleccionar una pieza.');
        }
        if ($fuente !== 'pieza_fija') {
            $idPieza = null;
        }

        $tickerSegmentos = $this->validarTextoLibreOpcional($data, 'ticker_segmentos', 1024);

        [$fecIni, $fecFin] = $this->validarVentanaOpcional($data);

        $stmt = $this->getDb()->prepare(
            'UPDATE promociones_banner SET
             activo = :activo,
             orden = :orden,
             visible_visitante = :vv,
             visible_cliente = :vc,
             visible_ticker = :vt,
             visible_barra_inferior = :vbi,
             ticker_segmentos = :tseg,
             variante = :var,
             eyebrow = :eyebrow,
             titulo = :titulo,
             texto = :texto,
             cta_label = :ctal,
             cta_href = :ctah,
             fuente_imagen = :fuente,
             id_pieza_fk = :idp,
             fecha_inicio = :fecha_i,
             fecha_fin = :fecha_f
             WHERE id_promocion_banner = :id'
        );
        $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
        $stmt->bindValue(':orden', $orden, PDO::PARAM_INT);
        $stmt->bindValue(':vv', $visibleVisitante, PDO::PARAM_INT);
        $stmt->bindValue(':vc', $visibleCliente, PDO::PARAM_INT);
        $stmt->bindValue(':vt', $visibleTicker, PDO::PARAM_INT);
        $stmt->bindValue(':vbi', $visibleBarraInferior, PDO::PARAM_INT);
        $stmt->bindValue(':tseg', $tickerSegmentos, $tickerSegmentos === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':var', $variante, PDO::PARAM_STR);
        $stmt->bindValue(':eyebrow', $eyebrow, $eyebrow === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':texto', $texto, PDO::PARAM_STR);
        $stmt->bindValue(':ctal', $ctaLabel, $ctaLabel === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ctah', $ctaHref, $ctaHref === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':fuente', $fuente, PDO::PARAM_STR);
        $stmt->bindValue(':idp', $idPieza, $idPieza === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':fecha_i', $fecIni, $fecIni === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':fecha_f', $fecFin, $fecFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE promociones_banner SET activo = 0 WHERE id_promocion_banner = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function assertVariante(string $v): void
    {
        $allowed = ['mayoreo', 'pieza', 'trabajo', 'tradicion'];
        if (!preg_match('/^[a-z][a-z0-9_-]{0,47}$/', $v)) {
            throw new InvalidArgumentException('Variante de estilo invalida.');
        }
        if ($v !== '' && !in_array($v, $allowed, true)) {
            throw new InvalidArgumentException('Variante debe ser una de: ' . implode(', ', $allowed) . '.');
        }
    }

    private function assertFuenteImagen(string $f): void
    {
        $allowed = ['ninguna', 'catalogo_rotacion', 'pieza_fija'];
        if (!in_array($f, $allowed, true)) {
            throw new InvalidArgumentException('Fuente de imagen invalida.');
        }
    }

    /**
     * @return array{0: ?string, 1: ?string} fecha_inicio, fecha_fin opcionales
     */
    private function validarVentanaOpcional(array $data): array
    {
        $raw1 = isset($data['fecha_inicio']) ? trim((string) $data['fecha_inicio']) : '';
        $raw2 = isset($data['fecha_fin']) ? trim((string) $data['fecha_fin']) : '';
        $fi = $raw1 === '' ? null : $raw1;
        $ff = $raw2 === '' ? null : $raw2;

        foreach ([$fi, $ff] as $d) {
            if ($d === null) {
                continue;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                throw new InvalidArgumentException('Las fechas de vigencia deben ser YYYY-MM-DD.');
            }
        }

        if ($fi !== null && $ff !== null && strtotime($ff) < strtotime($fi)) {
            throw new InvalidArgumentException('La fecha fin debe ser mayor o igual que la fecha inicio.');
        }

        return [$fi, $ff];
    }

    private function validarTexto(array $data, string $campo, int $max, string $label): string
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }
        $v = trim((string) $data[$campo]);
        if ($v === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacio.');
        }
        if (mb_strlen($v) > $max) {
            $v = mb_substr($v, 0, $max);
        }

        return strip_tags($v);
    }

    private function validarTextoLargo(array $data, string $campo, string $label): string
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }
        $v = trim((string) $data[$campo]);
        if ($v === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacio.');
        }

        return $v;
    }

    private function validarTextoOpcional(array $data, string $campo, int $max): string
    {
        if (!isset($data[$campo])) {
            return '';
        }
        $v = trim((string) $data[$campo]);
        if ($v === '') {
            return '';
        }
        if (mb_strlen($v) > $max) {
            $v = mb_substr($v, 0, $max);
        }

        return strip_tags($v);
    }

    private function validarTextoLargoOpcional(array $data, string $campo): string
    {
        if (!isset($data[$campo])) {
            return '';
        }

        return trim((string) $data[$campo]);
    }

    /** Permite texto vacío (NULL en BD). */
    private function validarTextoLibreOpcional(array $data, string $campo, int $max): ?string
    {
        if (!isset($data[$campo])) {
            return null;
        }
        $v = trim((string) $data[$campo]);
        if ($v === '') {
            return null;
        }

        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }

    private function validarEnteroOpcional(array $data, string $campo): ?int
    {
        if (!isset($data[$campo]) || $data[$campo] === '' || (int) $data[$campo] <= 0) {
            return null;
        }

        return (int) $data[$campo];
    }
}
