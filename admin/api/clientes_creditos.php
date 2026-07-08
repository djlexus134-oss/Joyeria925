<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/apartado_gestion.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method !== 'GET') {
        api_fail('Metodo no soportado.', 405);
    }

    // Permitir consulta a quien tenga acceso al monedero (lectura),
    // o a quien opere POS / apartados (necesita ver el saldo del cliente).
    $puedeLeer = auth_has_permission('CLIENTE_CREDITO_LEER')
        || auth_has_permission('CLIENTE_CREDITO_APLICAR')
        || auth_has_permission('VENTA_CREAR')
        || auth_has_permission('APARTADO_GESTION_LEER')
        || auth_has_permission('APARTADO_GESTION_ACTUALIZAR')
        || auth_has_permission('APARTADO_GESTION_CREAR');
    if (!$puedeLeer) {
        api_fail('No tienes permiso para consultar creditos del cliente.', 403);
    }

    $idCliente = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
    if ($idCliente <= 0) {
        api_fail('Falta el parametro id_cliente.', 422);
    }

    $estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : 'disponible';
    if ($estado === 'todos' || $estado === '') {
        $estado = null;
    }

    $incluirConsumos = isset($_GET['incluir_consumos']) && (string) $_GET['incluir_consumos'] === '1';

    $app = new ApartadoGestion();
    $creditos = $app->listarCreditosCliente($idCliente, $estado);

    $totalDisponible = 0.0;
    foreach ($creditos as $row) {
        if (($row['estado'] ?? '') === 'disponible') {
            $totalDisponible += (float) ($row['monto_disponible'] ?? 0);
        }
    }

    $creditosEnriquecidos = [];
    foreach ($creditos as $row) {
        $tipo = (string) ($row['tipo'] ?? '');
        $origen = '';
        if ($tipo === 'excedente_apartado' && !empty($row['id_apartado_origen_FK'])) {
            $origen = 'Apartado #' . (int) $row['id_apartado_origen_FK'];
        } elseif ($tipo === 'devolucion') {
            if (!empty($row['devolucion_id'])) {
                $origen = 'Devolucion #' . (int) $row['devolucion_id'];
            } elseif (!empty($row['id_devolucion_origen_FK'])) {
                $origen = 'Devolucion #' . (int) $row['id_devolucion_origen_FK'];
            } else {
                $origen = 'Devolucion';
            }
            if (!empty($row['id_venta_origen_FK'])) {
                $origen .= ' (venta #' . (int) $row['id_venta_origen_FK'] . ')';
            }
        } elseif ($tipo === 'ajuste') {
            $origen = 'Ajuste manual';
        }
        $row['origen_descripcion'] = $origen;
        $creditosEnriquecidos[] = $row;
    }

    $payload = [
        'creditos' => $creditosEnriquecidos,
        'total_disponible' => number_format($totalDisponible, 2, '.', ''),
    ];

    if ($incluirConsumos) {
        $payload['consumos'] = $app->listarConsumosCreditoCliente($idCliente, 200);
    }

    api_ok(['data' => $payload]);
} catch (InvalidArgumentException $e) {
    api_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
