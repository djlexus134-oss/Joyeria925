<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_colonia = isset($_GET['id_colonia']) ? (int) $_GET['id_colonia'] : 0;
$id_calle_exact = isset($_GET['id_calle']) ? (int) $_GET['id_calle'] : 0;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
if ($limit < 1) {
    $limit = 30;
}
if ($limit > 100) {
    $limit = 100;
}

if ($id_colonia <= 0 && $id_calle_exact <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    if ($id_calle_exact > 0) {
        $sql = "SELECT id_calle, nom_calle, id_colonia_FK
                FROM calles
                WHERE id_calle = :id_calle ";
        $params = [':id_calle' => $id_calle_exact];
        if ($id_colonia > 0) {
            $sql .= "AND id_colonia_FK = :id_colonia ";
            $params[':id_colonia'] = $id_colonia;
        }
        $sql .= "LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    $sql = "SELECT id_calle, nom_calle
            FROM calles
            WHERE id_colonia_FK = :id_colonia";

    $params = [':id_colonia' => $id_colonia];

    if ($q !== '') {
        $sql .= " AND nom_calle LIKE :pref COLLATE utf8mb4_unicode_ci";
        $params[':pref'] = $q . '%';
    }

    $sql .= " ORDER BY nom_calle ASC LIMIT " . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
