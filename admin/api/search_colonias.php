<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../sistema.class.php';

$id_codigo_postal = isset($_GET['id_codigo_postal']) ? (int) $_GET['id_codigo_postal'] : 0;
$id_localidad = isset($_GET['id_localidad']) ? (int) $_GET['id_localidad'] : 0;
$id_colonia_exact = isset($_GET['id_colonia']) ? (int) $_GET['id_colonia'] : 0;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
if ($limit < 1) {
    $limit = 30;
}
if ($limit > 100) {
    $limit = 100;
}

if ($id_codigo_postal <= 0 && $id_colonia_exact <= 0 && $id_localidad <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Sistema();
    $pdo = $db->getDb();

    $baseFrom = "FROM colonias col
        INNER JOIN codigos_postales cp ON col.id_codigo_postal_FK = cp.id_codigo_postal
        INNER JOIN localidades loc ON col.id_localidad_FK = loc.id_localidad
        INNER JOIN municipios mun ON loc.id_municipio_FK = mun.id_municipio
        INNER JOIN estados est ON mun.id_estado_FK = est.id_estado
        INNER JOIN paises pai ON est.id_pais_FK = pai.id_pais ";

    $selectList = "SELECT
            col.id_colonia,
            col.nom_colonia,
            col.id_codigo_postal_FK AS id_codigo_postal,
            cp.codigo_postal,
            loc.id_localidad,
            loc.nom_localidad,
            mun.id_municipio,
            mun.nom_municipio,
            est.id_estado,
            est.nom_estado,
            pai.id_pais,
            pai.nom_pais ";

    if ($id_colonia_exact > 0) {
        $sql = $selectList . $baseFrom . "WHERE col.id_colonia = :id_colonia ";
        $params = [':id_colonia' => $id_colonia_exact];
        if ($id_codigo_postal > 0) {
            $sql .= "AND col.id_codigo_postal_FK = :id_cp ";
            $params[':id_cp'] = $id_codigo_postal;
        }
        $sql .= "LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    if ($id_localidad > 0) {
        $sql = $selectList . $baseFrom . "WHERE col.id_localidad_FK = :id_loc";
        $params = [':id_loc' => $id_localidad];

        if ($q !== '') {
            $sql .= " AND col.nom_colonia LIKE :pref COLLATE utf8mb4_unicode_ci";
            $params[':pref'] = $q . '%';
        }

        $sql .= " ORDER BY col.nom_colonia ASC LIMIT " . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    $sql = $selectList . $baseFrom . "WHERE col.id_codigo_postal_FK = :id_cp";

    $params = [':id_cp' => $id_codigo_postal];

    if ($q !== '') {
        $sql .= " AND col.nom_colonia LIKE :pref COLLATE utf8mb4_unicode_ci";
        $params[':pref'] = $q . '%';
    }

    $sql .= " ORDER BY col.nom_colonia ASC LIMIT " . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
