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

$codigo_postal = isset($data['codigo_postal']) ? trim((string) $data['codigo_postal']) : '';

if ($codigo_postal === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para crear codigo postal']);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $stmt = $pdo->prepare(
        'SELECT id_codigo_postal
         FROM codigos_postales
         WHERE codigo_postal COLLATE utf8mb4_unicode_ci = :cp COLLATE utf8mb4_unicode_ci
         LIMIT 1'
    );
    $stmt->execute([':cp' => $codigo_postal]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        echo json_encode([
            'success' => true,
            'id_codigo_postal' => (int) $existente['id_codigo_postal'],
            'codigo_postal' => $codigo_postal,
            'reused' => true,
        ]);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO codigos_postales (codigo_postal) VALUES (:cp)');
    $stmt->execute([':cp' => $codigo_postal]);

    echo json_encode([
        'success' => true,
        'id_codigo_postal' => (int) $pdo->lastInsertId(),
        'codigo_postal' => $codigo_postal,
        'reused' => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
