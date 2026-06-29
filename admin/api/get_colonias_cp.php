<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_codigo_postal = isset($_GET['id_codigo_postal']) ? intval($_GET['id_codigo_postal']) : null;

if (!$id_codigo_postal) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $stmt = $db->getDb()->prepare(
        "SELECT
            col.id_colonia,
            col.nom_colonia,
            loc.id_localidad,
            loc.nom_localidad,
            mun.id_municipio,
            mun.nom_municipio,
            est.id_estado,
            est.nom_estado,
            pai.id_pais,
            pai.nom_pais
        FROM colonias col
        INNER JOIN localidades loc ON col.id_localidad_FK = loc.id_localidad
        INNER JOIN municipios mun ON loc.id_municipio_FK = mun.id_municipio
        INNER JOIN estados est ON mun.id_estado_FK = est.id_estado
        INNER JOIN paises pai ON est.id_pais_FK = pai.id_pais
        WHERE col.id_codigo_postal_FK = ?
        ORDER BY col.nom_colonia ASC"
    );
    $stmt->execute([$id_codigo_postal]);
    $colonias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($colonias);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
