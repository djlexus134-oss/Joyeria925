<?php
require_once __DIR__ . '/../services/EtiquetaZplService.php';
require_once __DIR__ . '/../models/cola_impresion.php';
require_once __DIR__ . '/../models/piezas_stock.php';
require_once __DIR__ . '/../models/insumos.php';
require_once __DIR__ . '/auth.php';

/** Maximo de etiquetas por trabajo en cola (insumos o piezas). */
const JOYERIA_ETIQUETAS_MAX_POR_TRABAJO = 500;

function joyeria_encolar_etiquetas_stock(array $idsPiezaStock, ?int $idTienda = null): int
{
    $etiquetas = new EtiquetaZplService();
    if (!$etiquetas->impresionHabilitada()) {
        throw new RuntimeException('La impresion de etiquetas esta deshabilitada en configuracion.');
    }

    $ids = [];
    foreach ($idsPiezaStock as $id) {
        $n = (int) $id;
        if ($n > 0) {
            $ids[] = $n;
        }
    }
    $ids = array_values(array_unique($ids));
    if ($ids === []) {
        throw new InvalidArgumentException('No hay IDs de stock para encolar.');
    }

    $usuario = auth_user();
    $idUsuario = $usuario !== null ? (int) ($usuario['id_usuario'] ?? 0) : null;
    if ($idUsuario !== null && $idUsuario <= 0) {
        $idUsuario = null;
    }

    $cola = new ColaImpresion();
    $tipo = count($ids) === 1 ? 'etiqueta_stock' : 'etiqueta_lote';

    return $cola->encolarEtiquetas($ids, $tipo, $idTienda, $idUsuario);
}

function joyeria_encolar_etiquetas_rango(int $idPieza, int $desde, int $hasta, ?int $idTienda = null, bool $soloDisponibles = false): int
{
    $ids = (new PiezasStock())->resolverIdsRango($idPieza, $desde, $hasta, $soloDisponibles);
    if ($ids === []) {
        $filtro = $soloDisponibles ? " en estado 'disponible'" : '';
        throw new InvalidArgumentException(
            'No hay stock en el rango ' . $desde . '-' . $hasta . ' para la pieza #' . $idPieza . $filtro
            . '. Verifica los codigos auxiliares (ej. 42/1, 42/2).'
        );
    }

    return joyeria_encolar_etiquetas_stock($ids, $idTienda);
}

/**
 * Expande items [{ id_insumo, copias }] o lista plana de ids a ids repetidos.
 *
 * @param array<int>|array<int, array{id_insumo?:int, copias?:int}> $entrada
 * @return array{ids: array<int>, copias_por_id: array<int, int>}
 */
function joyeria_expandir_ids_insumo_etiquetas(array $entrada): array
{
    $ids = [];
    $copiasPorId = [];

    foreach ($entrada as $key => $item) {
        if (is_array($item)) {
            $idInsumo = (int) ($item['id_insumo'] ?? 0);
            $copias = (int) ($item['copias'] ?? 1);
        } else {
            $idInsumo = (int) $item;
            $copias = 1;
        }
        if ($idInsumo <= 0 || $copias <= 0) {
            continue;
        }
        $copias = min(500, $copias);
        $copiasPorId[$idInsumo] = ($copiasPorId[$idInsumo] ?? 0) + $copias;
        for ($i = 0; $i < $copias; $i++) {
            $ids[] = $idInsumo;
        }
    }

    return ['ids' => $ids, 'copias_por_id' => $copiasPorId];
}

/**
 * Valida insumos activos con SKU antes de encolar etiquetas.
 *
 * @param array<int> $idsInsumoUnicos
 */
function joyeria_validar_insumos_para_etiquetas(array $idsInsumoUnicos): void
{
    if ($idsInsumoUnicos === []) {
        throw new InvalidArgumentException('No hay insumos para encolar.');
    }

    $insumos = new Insumos();
    $db = $insumos->getDb();
    $placeholders = implode(',', array_fill(0, count($idsInsumoUnicos), '?'));
    $sql = 'SELECT id_insumo, nombre, sku_codigo, activo
            FROM insumos
            WHERE id_insumo IN (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    foreach (array_values($idsInsumoUnicos) as $i => $id) {
        $stmt->bindValue($i + 1, (int) $id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['id_insumo']] = $row;
    }

    foreach ($idsInsumoUnicos as $id) {
        if (!isset($map[$id])) {
            throw new InvalidArgumentException('Insumo #' . $id . ' no encontrado.');
        }
        $row = $map[$id];
        if ((int) ($row['activo'] ?? 0) !== 1) {
            throw new InvalidArgumentException('El insumo «' . ($row['nombre'] ?? $id) . '» no esta activo.');
        }
        if (trim((string) ($row['sku_codigo'] ?? '')) === '') {
            throw new InvalidArgumentException(
                'El insumo «' . ($row['nombre'] ?? $id) . '» no tiene SKU. Guarda el insumo para generar codigo.'
            );
        }
    }
}

/**
 * @param array<int> $idsInsumo Lista expandida (una entrada por etiqueta fisica).
 * @param array<int, int>|null $copiasPorId Metadata opcional para el payload.
 */
function joyeria_encolar_etiquetas_insumo(array $idsInsumo, ?int $idTienda = null, ?array $copiasPorId = null): int
{
    $etiquetas = new EtiquetaZplService();
    if (!$etiquetas->impresionHabilitada()) {
        throw new RuntimeException('La impresion de etiquetas esta deshabilitada en configuracion.');
    }

    $ids = [];
    foreach ($idsInsumo as $id) {
        $n = (int) $id;
        if ($n > 0) {
            $ids[] = $n;
        }
    }
    if ($ids === []) {
        throw new InvalidArgumentException('No hay IDs de insumo para encolar.');
    }
    if (count($ids) > JOYERIA_ETIQUETAS_MAX_POR_TRABAJO) {
        throw new InvalidArgumentException(
            'Maximo ' . JOYERIA_ETIQUETAS_MAX_POR_TRABAJO . ' etiquetas por encolado.'
        );
    }

    $unicos = array_values(array_unique($ids));
    joyeria_validar_insumos_para_etiquetas($unicos);

    $usuario = auth_user();
    $idUsuario = $usuario !== null ? (int) ($usuario['id_usuario'] ?? 0) : null;
    if ($idUsuario !== null && $idUsuario <= 0) {
        $idUsuario = null;
    }

    $cola = new ColaImpresion();
    $tipo = count($ids) === 1 ? 'etiqueta_insumo' : 'etiqueta_insumo_lote';

    return $cola->encolarEtiquetasInsumos($ids, $tipo, $idTienda, $idUsuario, $copiasPorId);
}
