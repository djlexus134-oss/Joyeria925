<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/familia.php';

header('Content-Type: application/json; charset=utf-8');

api_require_method('POST');

try {
    $data = api_json_body();
    $nombre = api_string($data, 'nom_familia', true);

    if (strlen($nombre) > 50) {
        api_fail('El nombre de la familia no puede exceder 50 caracteres.', 422);
    }

    $app = new Familia();
    $cantidad = $app->crear(['nom_familia' => $nombre]);

    if ($cantidad > 0) {
        $lastId = $app->getDb()->lastInsertId();
        
        api_ok([
            'message' => 'Familia creada correctamente',
            'success' => true,
            'id_familia' => (int) $lastId,
            'nom_familia' => $nombre
        ], 201);
    } else {
        api_fail('No se pudo crear la familia.', 400);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
?>
