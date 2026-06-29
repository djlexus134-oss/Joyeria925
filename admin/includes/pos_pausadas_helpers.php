<?php
/**
 * Tickets de punto de venta guardados en espera (sesion).
 */

const POS_PAUSADAS_KEY = 'joyeria_pos_pausadas';
const POS_PAUSADAS_MAX = 15;

function pos_obtener_lista_pausadas(): array
{
    if (!isset($_SESSION[POS_PAUSADAS_KEY]) || !is_array($_SESSION[POS_PAUSADAS_KEY])) {
        $_SESSION[POS_PAUSADAS_KEY] = [];
    }

    return $_SESSION[POS_PAUSADAS_KEY];
}

function pos_guardar_lista_pausadas(array $lista): void
{
    $_SESSION[POS_PAUSADAS_KEY] = array_values($lista);
}

function pos_estado_es_pausable(array $estado): bool
{
    if (!empty($estado['detalles']) && is_array($estado['detalles'])) {
        return true;
    }
    if (!empty($estado['creditos_canje']) && is_array($estado['creditos_canje'])) {
        return true;
    }
    $idCliente = isset($estado['id_cliente']) ? (int) $estado['id_cliente'] : 0;
    if ($idCliente > 0) {
        return true;
    }

    return false;
}

function pos_mapa_nombres_clientes(array $catalogos): array
{
    $map = [];
    $clientes = $catalogos['clientes'] ?? [];
    if (!is_array($clientes)) {
        return $map;
    }
    foreach ($clientes as $cliente) {
        if (!is_array($cliente)) {
            continue;
        }
        $id = (int) ($cliente['id_cliente'] ?? 0);
        if ($id > 0) {
            $map[$id] = trim((string) ($cliente['nombre_completo'] ?? ''));
        }
    }

    return $map;
}

function pos_generar_etiqueta_pausada(array $estado, array $mapClientes): string
{
    $partes = [];
    $idCliente = isset($estado['id_cliente']) ? (int) $estado['id_cliente'] : 0;
    if ($idCliente > 0) {
        $nombre = $mapClientes[$idCliente] ?? ('Cliente #' . $idCliente);
        $partes[] = $nombre;
    }
    $nDet = is_array($estado['detalles'] ?? null) ? count($estado['detalles']) : 0;
    if ($nDet > 0) {
        $partes[] = $nDet . ' producto' . ($nDet === 1 ? '' : 's');
    }
    $nCred = is_array($estado['creditos_canje'] ?? null) ? count($estado['creditos_canje']) : 0;
    if ($nCred > 0) {
        $partes[] = $nCred . ' credito' . ($nCred === 1 ? '' : 's') . ' canje';
    }
    if ($partes === []) {
        return 'Venta en espera ' . date('H:i');
    }

    return implode(' · ', $partes);
}

function pos_normalizar_pagos_borrador($raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $pago) {
        if (!is_array($pago)) {
            continue;
        }
        $idForma = (int) ($pago['id_forma_pago_FK'] ?? 0);
        $monto = isset($pago['monto']) ? (float) $pago['monto'] : 0.0;
        if ($idForma > 0 && $monto > 0) {
            $out[] = [
                'id_forma_pago_FK' => $idForma,
                'monto' => number_format($monto, 2, '.', ''),
            ];
        }
    }

    return $out;
}

function pos_agregar_pausada(array $estado, ?string $etiqueta, ?array $pagosBorrador, array $mapClientes): string
{
    $lista = pos_obtener_lista_pausadas();
    if (count($lista) >= POS_PAUSADAS_MAX) {
        throw new RuntimeException('Solo puedes tener hasta ' . POS_PAUSADAS_MAX . ' ventas en espera. Retoma o elimina alguna antes de guardar otra.');
    }

    $etiquetaLimpia = trim((string) $etiqueta);
    if ($etiquetaLimpia === '') {
        $etiquetaLimpia = pos_generar_etiqueta_pausada($estado, $mapClientes);
    }
    if (mb_strlen($etiquetaLimpia) > 120) {
        $etiquetaLimpia = mb_substr($etiquetaLimpia, 0, 120);
    }

    $id = 'p_' . bin2hex(random_bytes(8));
    $lista[] = [
        'id' => $id,
        'etiqueta' => $etiquetaLimpia,
        'creado_en' => date('c'),
        'estado' => $estado,
        'pagos_borrador' => is_array($pagosBorrador) ? $pagosBorrador : [],
    ];
    pos_guardar_lista_pausadas($lista);

    return $id;
}

function pos_buscar_pausada(string $id): ?array
{
    $id = trim($id);
    if ($id === '') {
        return null;
    }
    foreach (pos_obtener_lista_pausadas() as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['id'] ?? '') === $id) {
            return $item;
        }
    }

    return null;
}

function pos_eliminar_pausada(string $id): bool
{
    $id = trim($id);
    if ($id === '') {
        return false;
    }
    $lista = pos_obtener_lista_pausadas();
    $nueva = [];
    $eliminado = false;
    foreach ($lista as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['id'] ?? '') === $id) {
            $eliminado = true;
            continue;
        }
        $nueva[] = $item;
    }
    if ($eliminado) {
        pos_guardar_lista_pausadas($nueva);
    }

    return $eliminado;
}

function pos_resumir_pausadas(Ventas $app, array $mapClientes): array
{
    $resumenes = [];
    foreach (pos_obtener_lista_pausadas() as $item) {
        if (!is_array($item) || !isset($item['estado']) || !is_array($item['estado'])) {
            continue;
        }
        $estado = $item['estado'];
        $idImpuesto = isset($estado['id_impuesto']) ? (int) $estado['id_impuesto'] : 0;
        $idCliente = isset($estado['id_cliente']) ? (int) $estado['id_cliente'] : 0;
        $mCanje = 0.0;
        if (!empty($estado['creditos_canje']) && is_array($estado['creditos_canje'])) {
            foreach ($estado['creditos_canje'] as $cr) {
                if (is_array($cr)) {
                    $mCanje += (float) ($cr['monto_credito'] ?? 0);
                }
            }
        }
        $totales = [
            'total' => '0.00',
            'conteo_piezas' => 0,
        ];
        if ($idImpuesto > 0) {
            try {
                $totales = $app->calcularTotalesPuntoVenta(
                    $estado['detalles'] ?? [],
                    $idCliente > 0 ? $idCliente : null,
                    $idImpuesto,
                    $mCanje
                );
            } catch (Throwable $e) {
                $totales['total'] = '0.00';
            }
        }
        $resumenes[] = [
            'id' => (string) ($item['id'] ?? ''),
            'etiqueta' => (string) ($item['etiqueta'] ?? ''),
            'creado_en' => (string) ($item['creado_en'] ?? ''),
            'conteo_piezas' => (int) ($totales['conteo_piezas'] ?? 0),
            'total' => (string) ($totales['total'] ?? '0.00'),
            'id_cliente' => $idCliente > 0 ? $idCliente : null,
            'nombre_cliente' => $idCliente > 0 ? ($mapClientes[$idCliente] ?? null) : null,
        ];
    }

    return $resumenes;
}

function pos_respuesta_pos_con_pausadas(Ventas $app, array $catalogos, array $payload): array
{
    $mapClientes = pos_mapa_nombres_clientes($catalogos);
    $payload['pausadas'] = pos_resumir_pausadas($app, $mapClientes);
    $payload['pausadas_total'] = count($payload['pausadas']);

    return $payload;
}
