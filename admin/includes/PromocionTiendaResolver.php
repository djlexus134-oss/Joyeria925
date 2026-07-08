<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';

/**
 * Resuelve promociones de descuento vigentes para la tienda en linea.
 */
class PromocionTiendaResolver extends Sistema
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $cacheVigentes = null;

    /** @var array<string, bool>|null */
    private static ?array $cacheColumnasPromociones = null;

    /**
     * @return array<string, bool>
     */
    private function columnasPromocionesTabla(): array
    {
        if (self::$cacheColumnasPromociones !== null) {
            return self::$cacheColumnasPromociones;
        }

        $out = [];
        try {
            $stmt = $this->getDb()->query('SHOW COLUMNS FROM promociones');
            $cols = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (is_array($cols)) {
                foreach ($cols as $col) {
                    $out[(string) $col] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('PromocionTiendaResolver::columnasPromocionesTabla ' . $e->getMessage());
        }

        return self::$cacheColumnasPromociones = $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarVigentes(): array
    {
        if (self::$cacheVigentes !== null) {
            return self::$cacheVigentes;
        }

        $cols = $this->columnasPromocionesTabla();
        $selectMetal = !empty($cols['id_metal_FK']) ? 'pr.id_metal_FK,' : '';
        $joinMetal = !empty($cols['id_metal_FK']) ? 'LEFT JOIN metales m ON m.id_metal = pr.id_metal_FK' : '';
        $nomMetal = !empty($cols['id_metal_FK']) ? 'm.nom_metal' : 'NULL AS nom_metal';

        $sql = "SELECT pr.id_promocion,
                       pr.nombre,
                       pr.porcentaje_descuento,
                       pr.fecha_inicio,
                       pr.fecha_fin,
                       pr.id_pieza_FK,
                       pr.id_subfamilia_FK,
                       pr.id_familia_FK,
                       {$selectMetal}
                       pr.aplica_todas_familias,
                       pr.observaciones,
                       p.desc_pieza,
                       sf.nom_sub_familia,
                       f.nom_familia,
                       {$nomMetal}
                FROM promociones pr
                LEFT JOIN piezas p ON p.id_pieza = pr.id_pieza_FK
                LEFT JOIN sub_familia sf ON sf.id_sub_familia = pr.id_subfamilia_FK
                LEFT JOIN familias f ON f.id_familia = pr.id_familia_FK
                {$joinMetal}
                WHERE pr.activa = 1
                  AND pr.fecha_inicio <= CURDATE()
                  AND pr.fecha_fin >= CURDATE()
                ORDER BY pr.porcentaje_descuento DESC, pr.id_promocion DESC";

        $stmt = $this->getDb()->query($sql);
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        self::$cacheVigentes = is_array($rows) ? $rows : [];

        return self::$cacheVigentes;
    }

    /**
     * @return array<string, mixed>|null Promo ganadora o null si no aplica.
     */
    public function resolverParaPieza(int $idPieza, int $idSubfamilia, int $idFamilia, int $idMetal = 0): ?array
    {
        if ($idPieza <= 0) {
            return null;
        }

        $candidatas = [];
        foreach ($this->listarVigentes() as $promo) {
            if (!is_array($promo)) {
                continue;
            }

            $nivel = $this->nivelCoincidencia($promo, $idPieza, $idSubfamilia, $idFamilia, $idMetal);
            if ($nivel === null) {
                continue;
            }

            $candidatas[] = [
                'nivel' => $nivel,
                'promo' => $promo,
                'porcentaje' => (float) ($promo['porcentaje_descuento'] ?? 0),
            ];
        }

        if ($candidatas === []) {
            return null;
        }

        usort($candidatas, static function (array $a, array $b): int {
            if ($a['nivel'] !== $b['nivel']) {
                return $b['nivel'] <=> $a['nivel'];
            }
            if ($a['porcentaje'] !== $b['porcentaje']) {
                return $b['porcentaje'] <=> $a['porcentaje'];
            }

            return (int) ($b['promo']['id_promocion'] ?? 0) <=> (int) ($a['promo']['id_promocion'] ?? 0);
        });

        return $candidatas[0]['promo'];
    }

    /**
     * @return array{precio_lista: float, precio_final: float, descuento_monto: float, porcentaje: float}
     */
    public function calcularPrecios(float $precioLista, float $porcentaje): array
    {
        $lista = max(0.0, round($precioLista, 2));
        $pct = $this->acotarPorcentaje($porcentaje);
        $descuento = round($lista * ($pct / 100), 2);
        $final = round(max(0.0, $lista - $descuento), 2);

        return [
            'precio_lista' => $lista,
            'precio_final' => $final,
            'descuento_monto' => $descuento,
            'porcentaje' => $pct,
        ];
    }

    /**
     * Precio de catalogo (costo + aumento, redondeo a multiplo de 5).
     */
    public static function precioListaDesdePieza(array $pieza): float
    {
        $costo = (float) ($pieza['costo'] ?? 0);
        $aumento = ($pieza['aumento_pct'] !== null && $pieza['aumento_pct'] !== '')
            ? (float) $pieza['aumento_pct']
            : 0.0;
        $pv = round($costo * (1 + $aumento / 100), 2);
        if ($pv > 0) {
            $pv = ceil($pv / 5) * 5;
        }

        return (float) $pv;
    }

    /**
     * @return array{
     *   precio_lista: float,
     *   precio_final: float,
     *   descuento_monto: float,
     *   porcentaje: float,
     *   tiene_promocion: bool,
     *   promocion: ?array
     * }
     */
    public function resolverPrecioPieza(array $pieza): array
    {
        $precioLista = self::precioListaDesdePieza($pieza);
        $idPieza = (int) ($pieza['id_pieza'] ?? 0);
        $idSub = (int) ($pieza['id_sub_familia'] ?? $pieza['id_subfamilia_FK'] ?? 0);
        $idFam = (int) ($pieza['id_familia'] ?? $pieza['id_familia_FK'] ?? 0);

        $promo = $this->resolverParaPieza($idPieza, $idSub, $idFam, (int) ($pieza['id_metal_FK'] ?? $pieza['id_metal'] ?? 0));
        if ($promo === null) {
            return [
                'precio_lista' => $precioLista,
                'precio_final' => $precioLista,
                'descuento_monto' => 0.0,
                'porcentaje' => 0.0,
                'tiene_promocion' => false,
                'promocion' => null,
            ];
        }

        $precios = $this->calcularPrecios($precioLista, (float) ($promo['porcentaje_descuento'] ?? 0));

        return [
            'precio_lista' => $precios['precio_lista'],
            'precio_final' => $precios['precio_final'],
            'descuento_monto' => $precios['descuento_monto'],
            'porcentaje' => $precios['porcentaje'],
            'tiene_promocion' => $precios['descuento_monto'] > 0,
            'promocion' => $promo,
        ];
    }

    /**
     * @param array<string, mixed> $promo
     */
    private function nivelCoincidencia(array $promo, int $idPieza, int $idSubfamilia, int $idFamilia, int $idMetal = 0): ?int
    {
        if (!empty($promo['aplica_todas_familias']) && (int) $promo['aplica_todas_familias'] === 1) {
            return $idPieza > 0 ? 0 : null;
        }

        $idPiezaPromo = isset($promo['id_pieza_FK']) && $promo['id_pieza_FK'] !== null && $promo['id_pieza_FK'] !== ''
            ? (int) $promo['id_pieza_FK']
            : 0;
        if ($idPiezaPromo > 0) {
            return $idPiezaPromo === $idPieza ? 3 : null;
        }

        $idSubPromo = isset($promo['id_subfamilia_FK']) && $promo['id_subfamilia_FK'] !== null && $promo['id_subfamilia_FK'] !== ''
            ? (int) $promo['id_subfamilia_FK']
            : 0;
        if ($idSubPromo > 0) {
            return $idSubPromo === $idSubfamilia ? 2 : null;
        }

        $idFamPromo = isset($promo['id_familia_FK']) && $promo['id_familia_FK'] !== null && $promo['id_familia_FK'] !== ''
            ? (int) $promo['id_familia_FK']
            : 0;
        if ($idFamPromo > 0) {
            return $idFamPromo === $idFamilia ? 1 : null;
        }

        $idMetalPromo = isset($promo['id_metal_FK']) && $promo['id_metal_FK'] !== null && $promo['id_metal_FK'] !== ''
            ? (int) $promo['id_metal_FK']
            : 0;
        if ($idMetalPromo > 0) {
            return $idMetal > 0 && $idMetalPromo === $idMetal ? 1 : null;
        }

        return null;
    }

    private function acotarPorcentaje(float $value): float
    {
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 100) {
            return 100.0;
        }

        return $value;
    }

    public static function limpiarCache(): void
    {
        self::$cacheVigentes = null;
    }
}
