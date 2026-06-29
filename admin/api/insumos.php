<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/insumos.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Insumos();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$idTiendaParam = isset($_GET['id_tienda']) ? (int) $_GET['id_tienda'] : 0;
$idTienda = $idTiendaParam > 0 ? $idTiendaParam : null;
$soloConStock = isset($_GET['solo_con_stock']) && (string) $_GET['solo_con_stock'] === '1';

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $insumo = $app->leerUno($id);
                if (!$insumo) {
                    api_fail('Insumo no encontrado.', 404);
                }
                $insumo['existencia_por_tienda'] = $app->obtenerExistenciasPorTienda($id);
                api_ok(['data' => $insumo]);
            }

            api_ok([
                'data' => $app->leerParaVentaPorTienda($idTienda, $soloConStock),
            ]);

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
