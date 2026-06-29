<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/proveedor.php';

header('Content-Type: application/json; charset=utf-8');

api_require_method('POST');

try {
    $data = api_json_body();
    $razonSocial = api_string($data, 'razon_social', true);

    if (strlen($razonSocial) > 255) {
        api_fail('La razón social no puede exceder 255 caracteres.', 422);
    }

    $app = new Proveedor();
    $cantidad = $app->crear(['razon_social' => $razonSocial]);

    if ($cantidad > 0) {
        $lastId = $app->getDb()->lastInsertId();
        
        api_ok([
            'message' => 'Proveedor creado correctamente',
            'success' => true,
            'id_proveedor' => (int) $lastId,
            'razon_social' => $razonSocial
        ], 201);
    } else {
        api_fail('No se pudo crear el proveedor.', 400);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
?>
