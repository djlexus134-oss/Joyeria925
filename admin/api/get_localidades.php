<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_municipio = isset($_GET['id_municipio']) ? intval($_GET['id_municipio']) : null;

if (!$id_municipio) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $stmt = $db->getDb()->prepare("SELECT id_localidad, nom_localidad FROM localidades WHERE id_municipio_FK = ? ORDER BY nom_localidad ASC");
    $stmt->execute([$id_municipio]);
    $localidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($localidades);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
