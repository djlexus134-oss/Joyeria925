<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_estado = isset($_GET['id_estado']) ? intval($_GET['id_estado']) : null;

if (!$id_estado) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $stmt = $db->getDb()->prepare("SELECT id_municipio, nom_municipio FROM municipios WHERE id_estado_FK = ? ORDER BY nom_municipio ASC");
    $stmt->execute([$id_estado]);
    $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($municipios);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
