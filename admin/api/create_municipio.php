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

$nom_municipio = isset($data['nom_municipio']) ? trim((string) $data['nom_municipio']) : '';
$id_estado = isset($data['id_estado_FK']) ? (int) $data['id_estado_FK'] : 0;

if ($nom_municipio === '' || $id_estado <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para crear municipio']);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $stmt = $pdo->prepare(
        'SELECT id_municipio
         FROM municipios
         WHERE nom_municipio COLLATE utf8mb4_unicode_ci = :nom COLLATE utf8mb4_unicode_ci
           AND id_estado_FK = :id_estado
         LIMIT 1'
    );
    $stmt->execute([':nom' => $nom_municipio, ':id_estado' => $id_estado]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        echo json_encode([
            'success' => true,
            'id_municipio' => (int) $existente['id_municipio'],
            'nom_municipio' => $nom_municipio,
            'reused' => true,
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO municipios (nom_municipio, id_estado_FK) VALUES (:nom, :id_estado)'
    );
    $stmt->execute([':nom' => $nom_municipio, ':id_estado' => $id_estado]);

    echo json_encode([
        'success' => true,
        'id_municipio' => (int) $pdo->lastInsertId(),
        'nom_municipio' => $nom_municipio,
        'reused' => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
