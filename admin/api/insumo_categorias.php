<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/insumos.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Insumos();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    switch ($method) {
        case 'GET':
            $q = isset($_GET['q']) ? (string) $_GET['q'] : '';
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
            api_ok([
                'data' => $app->buscarCategorias($q, $limit),
            ]);

        case 'POST':
            $body = api_json_body();
            $nombre = api_string($body, 'nombre', true);
            $id = $app->crearCategoria((string) $nombre);
            api_ok([
                'data' => [
                    'id_categoria' => $id,
                    'nombre' => (string) $nombre,
                ],
            ], 201);

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}

