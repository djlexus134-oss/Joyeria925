<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/sub_familia.php';

header('Content-Type: application/json; charset=utf-8');

api_require_method('POST');

try {
    $data = api_json_body();
    $nombre = api_string($data, 'nom_sub_familia', true);
    $idFamilia = api_int($data, 'id_familia_FK', true);

    if (strlen($nombre) > 50) {
        api_fail('El nombre de la subfamilia no puede exceder 50 caracteres.', 422);
    }

    $app = new SubFamilia();
    $cantidad = $app->crear(['nom_sub_familia' => $nombre, 'id_familia_FK' => $idFamilia]);

    if ($cantidad > 0) {
        $lastId = $app->getDb()->lastInsertId();
        
        api_ok([
            'message' => 'Subfamilia creada correctamente',
            'success' => true,
            'id_sub_familia' => (int) $lastId,
            'nom_sub_familia' => $nombre,
            'id_familia_FK' => $idFamilia
        ], 201);
    } else {
        api_fail('No se pudo crear la subfamilia.', 400);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
?>
