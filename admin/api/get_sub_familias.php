<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_familia = isset($_GET['id_familia']) ? intval($_GET['id_familia']) : null;

if (!$id_familia) {
    echo json_encode([]);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$like = null;
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();
    $cols = $pdo->query("SHOW COLUMNS FROM sub_familia LIKE 'nom_sub_familia_key'")->fetch(PDO::FETCH_ASSOC);
    $nombreCol = $cols ? 'nom_sub_familia_key' : 'nom_sub_familia';

    $sql = "SELECT id_sub_familia, nom_sub_familia FROM sub_familia WHERE id_familia_FK = ? AND activo = 1";
    $params = [$id_familia];
    if ($like !== null) {
        $sql .= ' AND ' . $nombreCol . ' LIKE ? COLLATE utf8mb4_0900_ai_ci';
        $params[] = $like;
    }
    $sql .= ' ORDER BY nom_sub_familia ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subfamilias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($subfamilias);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
