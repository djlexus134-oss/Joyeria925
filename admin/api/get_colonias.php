<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_localidad = isset($_GET['id_localidad']) ? intval($_GET['id_localidad']) : null;

if (!$id_localidad) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $stmt = $db->getDb()->prepare("SELECT id_colonia, nom_colonia FROM colonias WHERE id_localidad_FK = ? ORDER BY nom_colonia ASC");
    $stmt->execute([$id_localidad]);
    $colonias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($colonias);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
