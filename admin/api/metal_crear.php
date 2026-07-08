<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/metales.php';

header('Content-Type: application/json; charset=utf-8');

api_require_method('POST');

try {
    $data = api_json_body();
    $nombre = api_string($data, 'nom_metal', true);

    if (strlen($nombre) > 50) {
        api_fail('El nombre del metal no puede exceder 50 caracteres.', 422);
    }

    $app = new Metales();
    $cantidad = $app->crear(['nom_metal' => $nombre]);

    if ($cantidad > 0) {
        $lastId = $app->getDb()->lastInsertId();
        
        api_ok([
            'message' => 'Metal creado correctamente',
            'success' => true,
            'id_metal' => (int) $lastId,
            'nom_metal' => $nombre
        ], 201);
    } else {
        api_fail('No se pudo crear el metal.', 400);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
?>
