<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_pais = isset($_GET['id_pais']) ? (int) $_GET['id_pais'] : 0;
$id_estado = isset($_GET['id_estado']) ? (int) $_GET['id_estado'] : 0;
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

    if ($id_estado > 0) {
        $stmt = $pdo->prepare(
            'SELECT id_estado, nom_estado, id_pais_FK AS id_pais FROM estados WHERE id_estado = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id_estado]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($id_pais <= 0) {
        echo json_encode([]);
        exit;
    }

    $sql = 'SELECT id_estado, nom_estado, id_pais_FK AS id_pais FROM estados WHERE id_pais_FK = :id_pais ';
    $params = [':id_pais' => $id_pais];

    if ($q !== '') {
        $sql .= 'AND nom_estado LIKE :pref COLLATE utf8mb4_unicode_ci ';
        $params[':pref'] = $q . '%';
    }

    $sql .= 'ORDER BY nom_estado ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
