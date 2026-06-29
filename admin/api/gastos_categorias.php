<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/gastos.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Gastos();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    switch ($method) {
        case 'GET':
            if (!auth_has_permission('GASTO_LEER')) {
                api_fail('No tienes permiso para ver categorias de gastos.', 403);
            }
            $q = isset($_GET['q']) ? (string) $_GET['q'] : '';
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
            api_ok([
                'data' => $app->buscarCategorias($q, $limit),
            ]);
            break;

        case 'POST':
            if (!auth_has_permission('GASTO_CREAR')) {
                api_fail('No tienes permiso para crear categorias de gastos.', 403);
            }
            $body = api_json_body();
            $nombre = api_string($body, 'nombre', true);
            $id = $app->crearCategoria((string) $nombre);
            api_ok([
                'data' => [
                    'id_categoria_gasto' => $id,
                    'nombre' => (string) $nombre,
                ],
            ], 201);
            break;

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log('gastos_categorias.php: ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
