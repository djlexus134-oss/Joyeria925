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

$nom_pais = isset($data['nom_pais']) ? trim((string) $data['nom_pais']) : '';

if ($nom_pais === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para crear pais']);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $stmt = $pdo->prepare(
        'SELECT id_pais
         FROM paises
         WHERE nom_pais COLLATE utf8mb4_unicode_ci = :nom COLLATE utf8mb4_unicode_ci
         LIMIT 1'
    );
    $stmt->execute([':nom' => $nom_pais]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        echo json_encode([
            'success' => true,
            'id_pais' => (int) $existente['id_pais'],
            'nom_pais' => $nom_pais,
            'reused' => true,
        ]);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO paises (nom_pais) VALUES (:nom)');
    $stmt->execute([':nom' => $nom_pais]);

    echo json_encode([
        'success' => true,
        'id_pais' => (int) $pdo->lastInsertId(),
        'nom_pais' => $nom_pais,
        'reused' => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
