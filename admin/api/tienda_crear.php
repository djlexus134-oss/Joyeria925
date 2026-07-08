<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/tienda.php';

header('Content-Type: application/json; charset=utf-8');

api_require_method('POST');

try {
    $data = api_json_body();
    $nombre = api_string($data, 'nom_tienda', true);

    if (strlen($nombre) > 100) {
        api_fail('El nombre de la tienda no puede exceder 100 caracteres.', 422);
    }

    $app = new Tienda();
    $cantidad = $app->crear(['nom_tienda' => $nombre]);

    if ($cantidad > 0) {
        $lastId = $app->getDb()->lastInsertId();
        
        api_ok([
            'message' => 'Tienda creada correctamente',
            'success' => true,
            'id_tienda' => (int) $lastId,
            'nom_tienda' => $nombre
        ], 201);
    } else {
        api_fail('No se pudo crear la tienda.', 400);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
?>
