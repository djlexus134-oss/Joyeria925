<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$raw = joyeria_request_raw_body();
$data = json_decode($raw, true);

$nom_colonia = isset($data['nom_colonia']) ? trim((string) $data['nom_colonia']) : '';
$id_localidad = isset($data['id_localidad_FK']) ? (int) $data['id_localidad_FK'] : 0;
$id_codigo_postal = isset($data['id_codigo_postal_FK']) ? (int) $data['id_codigo_postal_FK'] : 0;

if ($nom_colonia === '' || $id_localidad <= 0 || $id_codigo_postal <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para crear colonia']);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $stmt = $pdo->prepare(
        "SELECT id_colonia
         FROM colonias
         WHERE nom_colonia COLLATE utf8mb4_unicode_ci = :nom_colonia COLLATE utf8mb4_unicode_ci
           AND id_localidad_FK = :id_localidad
           AND id_codigo_postal_FK = :id_codigo_postal
         LIMIT 1"
    );
    $stmt->execute([
        ':nom_colonia' => $nom_colonia,
        ':id_localidad' => $id_localidad,
        ':id_codigo_postal' => $id_codigo_postal,
    ]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        echo json_encode([
            'success' => true,
            'id_colonia' => (int) $existente['id_colonia'],
            'nom_colonia' => $nom_colonia,
            'reused' => true,
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO colonias (nom_colonia, id_localidad_FK, id_codigo_postal_FK)
         VALUES (:nom_colonia, :id_localidad, :id_codigo_postal)"
    );
    $stmt->execute([
        ':nom_colonia' => $nom_colonia,
        ':id_localidad' => $id_localidad,
        ':id_codigo_postal' => $id_codigo_postal,
    ]);

    echo json_encode([
        'success' => true,
        'id_colonia' => (int) $pdo->lastInsertId(),
        'nom_colonia' => $nom_colonia,
        'reused' => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
