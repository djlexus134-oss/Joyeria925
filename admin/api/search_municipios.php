<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_estado = isset($_GET['id_estado']) ? (int) $_GET['id_estado'] : 0;
$id_municipio = isset($_GET['id_municipio']) ? (int) $_GET['id_municipio'] : 0;
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

    if ($id_municipio > 0) {
        $stmt = $pdo->prepare(
            'SELECT id_municipio, nom_municipio, id_estado_FK AS id_estado FROM municipios WHERE id_municipio = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id_municipio]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($id_estado <= 0) {
        echo json_encode([]);
        exit;
    }

    $sql = 'SELECT id_municipio, nom_municipio, id_estado_FK AS id_estado FROM municipios WHERE id_estado_FK = :id_estado ';
    $params = [':id_estado' => $id_estado];

    if ($q !== '') {
        $sql .= 'AND nom_municipio LIKE :pref COLLATE utf8mb4_unicode_ci ';
        $params[':pref'] = $q . '%';
    }

    $sql .= 'ORDER BY nom_municipio ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
