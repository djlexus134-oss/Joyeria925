<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_colonia = isset($_GET['id_colonia']) ? intval($_GET['id_colonia']) : null;

if (!$id_colonia) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $stmt = $db->getDb()->prepare("SELECT id_calle, nom_calle FROM calles WHERE id_colonia_FK = ? ORDER BY nom_calle ASC");
    $stmt->execute([$id_colonia]);
    $calles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($calles);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
