<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_pais = isset($_GET['id_pais']) ? intval($_GET['id_pais']) : null;

if (!$id_pais) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $stmt = $db->getDb()->prepare("SELECT id_estado, nom_estado FROM estados WHERE id_pais_FK = ? ORDER BY nom_estado ASC");
    $stmt->execute([$id_pais]);
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($estados);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
