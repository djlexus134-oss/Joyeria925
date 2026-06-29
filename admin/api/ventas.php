<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/ventas.php';
require_once __DIR__ . '/../includes/list_search.php';
require_once __DIR__ . '/../includes/list_filters.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Ventas();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $venta = $app->leerUno($id);
                if (!$venta) {
                    api_fail('Venta no encontrada.', 404);
                }
                api_ok(['data' => $venta]);
            }

            $busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');
            $filtros = joyeria_ventas_filtros_desde_get($_GET);
            api_ok(['data' => $app->leer($busqueda, $filtros)]);

        case 'POST':
            $data = api_json_body();
            foreach (['id_cliente_FK', 'id_empleado_FK', 'id_impuesto_FK', 'total', 'impuesto_porcentaje', 'impuesto_monto'] as $campo) {
                if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                    api_fail('Falta el campo requerido: ' . $campo, 422);
                }
            }

            $idVenta = $app->crear($data);
            api_ok(['message' => 'Venta creada correctamente', 'id_venta' => $idVenta], 201);

        case 'PUT':
            if (!$id) {
                api_fail('Debes indicar el id de la venta.', 422);
            }
            $data = api_json_body();
            $cantidad = $app->actualizar($id, $data);
            api_ok(['message' => $cantidad ? 'Venta actualizada correctamente' : 'No se realizaron cambios']);

        case 'DELETE':
            if (!$id) {
                api_fail('Debes indicar el id de la venta.', 422);
            }
            $cantidad = $app->borrar($id);
            if (!$cantidad) {
                api_fail('No se pudo cancelar la venta.', 404);
            }
            api_ok(['message' => 'Venta cancelada correctamente']);

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
