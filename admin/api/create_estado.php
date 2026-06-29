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

$nom_estado = isset($data['nom_estado']) ? trim((string) $data['nom_estado']) : '';
$id_pais = isset($data['id_pais_FK']) ? (int) $data['id_pais_FK'] : 0;

if ($nom_estado === '' || $id_pais <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para crear estado']);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $stmt = $pdo->prepare(
        'SELECT id_estado
         FROM estados
         WHERE nom_estado COLLATE utf8mb4_unicode_ci = :nom COLLATE utf8mb4_unicode_ci
           AND id_pais_FK = :id_pais
         LIMIT 1'
    );
    $stmt->execute([':nom' => $nom_estado, ':id_pais' => $id_pais]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        echo json_encode([
            'success' => true,
            'id_estado' => (int) $existente['id_estado'],
            'nom_estado' => $nom_estado,
            'reused' => true,
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO estados (nom_estado, id_pais_FK) VALUES (:nom, :id_pais)'
    );
    $stmt->execute([':nom' => $nom_estado, ':id_pais' => $id_pais]);

    echo json_encode([
        'success' => true,
        'id_estado' => (int) $pdo->lastInsertId(),
        'nom_estado' => $nom_estado,
        'reused' => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
