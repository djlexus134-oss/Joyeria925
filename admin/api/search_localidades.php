<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_municipio = isset($_GET['id_municipio']) ? (int) $_GET['id_municipio'] : 0;
$id_localidad = isset($_GET['id_localidad']) ? (int) $_GET['id_localidad'] : 0;
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

    if ($id_localidad > 0) {
        $stmt = $pdo->prepare(
            'SELECT id_localidad, nom_localidad, id_municipio_FK AS id_municipio FROM localidades WHERE id_localidad = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id_localidad]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($id_municipio <= 0) {
        echo json_encode([]);
        exit;
    }

    $sql = 'SELECT id_localidad, nom_localidad, id_municipio_FK AS id_municipio FROM localidades WHERE id_municipio_FK = :id_municipio ';
    $params = [':id_municipio' => $id_municipio];

    if ($q !== '') {
        $sql .= 'AND nom_localidad LIKE :pref COLLATE utf8mb4_unicode_ci ';
        $params[':pref'] = $q . '%';
    }

    $sql .= 'ORDER BY nom_localidad ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
