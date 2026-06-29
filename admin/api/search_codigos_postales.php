<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_codigo_postal = isset($_GET['id_codigo_postal']) ? (int) $_GET['id_codigo_postal'] : 0;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
if ($limit < 1) {
    $limit = 50;
}
if ($limit > 100) {
    $limit = 100;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    if ($id_codigo_postal > 0) {
        $stmt = $pdo->prepare(
            'SELECT id_codigo_postal, codigo_postal FROM codigos_postales WHERE id_codigo_postal = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id_codigo_postal]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    $sql = 'SELECT id_codigo_postal, codigo_postal FROM codigos_postales ';
    $params = [];

    if ($q !== '') {
        $sql .= 'WHERE codigo_postal LIKE :pref COLLATE utf8mb4_unicode_ci ';
        $params[':pref'] = $q . '%';
    }

    $sql .= 'ORDER BY codigo_postal ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
