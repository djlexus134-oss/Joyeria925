<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/ordenes_taller.php';

$app = new OrdenesTaller();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method !== 'GET') {
        api_fail('Metodo no soportado.', 405);
    }

    $accion = isset($_GET['accion']) ? trim((string) $_GET['accion']) : '';

    if ($accion === 'buscar_stock') {
        $codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
        if ($codigo === '') {
            api_fail('Codigo requerido.', 422);
        }
        $stock = $app->buscarStockPorCodigo($codigo);
        if (!$stock) {
            api_fail('No se encontro pieza con ese codigo.', 404);
        }
        api_ok(['stock' => $stock]);
    }

    api_fail('Accion no soportada.', 400);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
