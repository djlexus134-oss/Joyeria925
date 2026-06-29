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

$nom_localidad = isset($data['nom_localidad']) ? trim((string) $data['nom_localidad']) : '';
$id_municipio = isset($data['id_municipio_FK']) ? (int) $data['id_municipio_FK'] : 0;

if ($nom_localidad === '' || $id_municipio <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para crear localidad']);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $stmt = $pdo->prepare(
        'SELECT id_localidad
         FROM localidades
         WHERE nom_localidad COLLATE utf8mb4_unicode_ci = :nom COLLATE utf8mb4_unicode_ci
           AND id_municipio_FK = :id_municipio
         LIMIT 1'
    );
    $stmt->execute([':nom' => $nom_localidad, ':id_municipio' => $id_municipio]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        echo json_encode([
            'success' => true,
            'id_localidad' => (int) $existente['id_localidad'],
            'nom_localidad' => $nom_localidad,
            'reused' => true,
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO localidades (nom_localidad, id_municipio_FK) VALUES (:nom, :id_municipio)'
    );
    $stmt->execute([':nom' => $nom_localidad, ':id_municipio' => $id_municipio]);

    echo json_encode([
        'success' => true,
        'id_localidad' => (int) $pdo->lastInsertId(),
        'nom_localidad' => $nom_localidad,
        'reused' => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
