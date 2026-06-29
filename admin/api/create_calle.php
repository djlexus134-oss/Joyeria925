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

$nom_calle = isset($data['nom_calle']) ? trim((string) $data['nom_calle']) : '';
$id_colonia = isset($data['id_colonia_FK']) ? (int) $data['id_colonia_FK'] : 0;

if ($nom_calle === '' || $id_colonia <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para crear calle']);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $stmt = $pdo->prepare(
        "SELECT id_calle
         FROM calles
         WHERE nom_calle COLLATE utf8mb4_unicode_ci = :nom_calle COLLATE utf8mb4_unicode_ci
           AND id_colonia_FK = :id_colonia
         LIMIT 1"
    );
    $stmt->execute([
        ':nom_calle' => $nom_calle,
        ':id_colonia' => $id_colonia,
    ]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        echo json_encode([
            'success' => true,
            'id_calle' => (int) $existente['id_calle'],
            'nom_calle' => $nom_calle,
            'reused' => true,
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO calles (nom_calle, id_colonia_FK)
         VALUES (:nom_calle, :id_colonia)"
    );
    $stmt->execute([
        ':nom_calle' => $nom_calle,
        ':id_colonia' => $id_colonia,
    ]);

    echo json_encode([
        'success' => true,
        'id_calle' => (int) $pdo->lastInsertId(),
        'nom_calle' => $nom_calle,
        'reused' => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
