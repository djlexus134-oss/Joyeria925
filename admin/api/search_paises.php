<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_pais = isset($_GET['id_pais']) ? (int) $_GET['id_pais'] : 0;
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

    if ($id_pais > 0) {
        $stmt = $pdo->prepare(
            'SELECT id_pais, nom_pais FROM paises WHERE id_pais = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id_pais]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    $sql = 'SELECT id_pais, nom_pais FROM paises ';
    $params = [];

    if ($q !== '') {
        $sql .= 'WHERE nom_pais LIKE :pref COLLATE utf8mb4_unicode_ci ';
        $params[':pref'] = $q . '%';
    }

    $sql .= 'ORDER BY nom_pais ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
