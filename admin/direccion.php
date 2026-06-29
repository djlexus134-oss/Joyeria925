<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/direccion_catalogo.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new DireccionCatalogo();

$entidades = [
    'paises' => [
        'titulo' => 'Paises',
        'singular' => 'Pais',
        'id' => 'id_pais',
        'principal' => 'nom_pais',
        'principal_label' => 'Nombre del pais',
    ],
    'estados' => [
        'titulo' => 'Estados',
        'singular' => 'Estado',
        'id' => 'id_estado',
        'principal' => 'nom_estado',
        'principal_label' => 'Nombre del estado',
    ],
    'municipios' => [
        'titulo' => 'Municipios',
        'singular' => 'Municipio',
        'id' => 'id_municipio',
        'principal' => 'nom_municipio',
        'principal_label' => 'Nombre del municipio',
    ],
    'localidades' => [
        'titulo' => 'Localidades',
        'singular' => 'Localidad',
        'id' => 'id_localidad',
        'principal' => 'nom_localidad',
        'principal_label' => 'Nombre de la localidad',
    ],
    'codigos_postales' => [
        'titulo' => 'Codigos Postales',
        'singular' => 'Codigo Postal',
        'id' => 'id_codigo_postal',
        'principal' => 'codigo_postal',
        'principal_label' => 'Codigo postal',
    ],
    'colonias' => [
        'titulo' => 'Colonias',
        'singular' => 'Colonia',
        'id' => 'id_colonia',
        'principal' => 'nom_colonia',
        'principal_label' => 'Nombre de la colonia',
    ],
    'calles' => [
        'titulo' => 'Calles',
        'singular' => 'Calle',
        'id' => 'id_calle',
        'principal' => 'nom_calle',
        'principal_label' => 'Nombre de la calle',
    ],
    'direcciones' => [
        'titulo' => 'Direcciones',
        'singular' => 'Direccion',
        'id' => 'id_direccion',
        'principal' => 'num_exterior',
        'principal_label' => 'Numero exterior',
    ],
];

$entidad = isset($_GET['entidad']) ? strtolower(trim($_GET['entidad'])) : 'paises';
if (!isset($entidades[$entidad])) {
    $entidad = 'paises';
}

$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : 'leer';
$mensaje = null;
$error = null;

function obtenerRegistrosPorEntidad($app, $entidad)
{
    switch ($entidad) {
        case 'paises':
            return $app->leerPaises();
        case 'estados':
            return $app->leerEstados();
        case 'municipios':
            return $app->leerMunicipios();
        case 'localidades':
            return $app->leerLocalidades();
        case 'codigos_postales':
            return $app->leerCodigosPostales();
        case 'colonias':
            return $app->leerColonias();
        case 'calles':
            return $app->leerCalles();
        case 'direcciones':
            return $app->leerDirecciones();
        default:
            return [];
    }
}

function obtenerRegistroPorEntidad($app, $entidad, $id)
{
    if (!$id) {
        return null;
    }

    $lista = [];
    switch ($entidad) {
        case 'paises':
            $lista = $app->leerPaises($id);
            break;
        case 'estados':
            $lista = $app->leerEstados($id);
            break;
        case 'municipios':
            $lista = $app->leerMunicipios($id);
            break;
        case 'localidades':
            $lista = $app->leerLocalidades($id);
            break;
        case 'codigos_postales':
            $lista = $app->leerCodigosPostales($id);
            break;
        case 'colonias':
            $lista = $app->leerColonias($id);
            break;
        case 'calles':
            $lista = $app->leerCalles($id);
            break;
        case 'direcciones':
            $lista = $app->leerDirecciones($id);
            break;
    }

    return !empty($lista) ? $lista[0] : null;
}

function ejecutarCrear($app, $entidad, $data)
{
    switch ($entidad) {
        case 'paises':
            return $app->crearPais(trim($data['nom_pais']));
        case 'estados':
            return $app->crearEstado(trim($data['nom_estado']), intval($data['id_pais_FK']));
        case 'municipios':
            return $app->crearMunicipio(trim($data['nom_municipio']), intval($data['id_estado_FK']));
        case 'localidades':
            return $app->crearLocalidad(trim($data['nom_localidad']), intval($data['id_municipio_FK']));
        case 'codigos_postales':
            return $app->crearCodigoPostal(trim($data['codigo_postal']));
        case 'colonias':
            return $app->crearColonia(
                trim($data['nom_colonia']),
                intval($data['id_localidad_FK']),
                intval($data['id_codigo_postal_FK'])
            );
        case 'calles':
            return $app->crearCalle(trim($data['nom_calle']), intval($data['id_colonia_FK']));
        case 'direcciones':
            $numInterior = (isset($data['num_interior']) && trim((string) $data['num_interior']) !== '')
                ? intval($data['num_interior'])
                : null;
            return $app->crearDireccion(intval($data['num_exterior']), $numInterior, intval($data['id_calle_FK']));
    }
    return false;
}

function ejecutarActualizar($app, $entidad, $id, $data)
{
    switch ($entidad) {
        case 'paises':
            return $app->actualizarPais($id, trim($data['nom_pais']));
        case 'estados':
            return $app->actualizarEstado($id, trim($data['nom_estado']), intval($data['id_pais_FK']));
        case 'municipios':
            return $app->actualizarMunicipio($id, trim($data['nom_municipio']), intval($data['id_estado_FK']));
        case 'localidades':
            return $app->actualizarLocalidad($id, trim($data['nom_localidad']), intval($data['id_municipio_FK']));
        case 'codigos_postales':
            return $app->actualizarCodigoPostal($id, trim($data['codigo_postal']));
        case 'colonias':
            return $app->actualizarColonia(
                $id,
                trim($data['nom_colonia']),
                intval($data['id_localidad_FK']),
                intval($data['id_codigo_postal_FK'])
            );
        case 'calles':
            return $app->actualizarCalle($id, trim($data['nom_calle']), intval($data['id_colonia_FK']));
        case 'direcciones':
            $numInterior = (isset($data['num_interior']) && trim((string) $data['num_interior']) !== '')
                ? intval($data['num_interior'])
                : null;
            return $app->actualizarDireccion($id, intval($data['num_exterior']), $numInterior, intval($data['id_calle_FK']));
    }
    return false;
}

function ejecutarBorrar($app, $entidad, $id)
{
    switch ($entidad) {
        case 'paises':
            return $app->borrarPais($id);
        case 'estados':
            return $app->borrarEstado($id);
        case 'municipios':
            return $app->borrarMunicipio($id);
        case 'localidades':
            return $app->borrarLocalidad($id);
        case 'codigos_postales':
            return $app->borrarCodigoPostal($id);
        case 'colonias':
            return $app->borrarColonia($id);
        case 'calles':
            return $app->borrarCalle($id);
        case 'direcciones':
            return $app->borrarDireccion($id);
    }
    return false;
}

function validarDatosEntidad($entidad, $data)
{
    switch ($entidad) {
        case 'paises':
            return isset($data['nom_pais']) && trim($data['nom_pais']) !== '';
        case 'estados':
            return isset($data['nom_estado'], $data['id_pais_FK']) && trim($data['nom_estado']) !== '' && intval($data['id_pais_FK']) > 0;
        case 'municipios':
            return isset($data['nom_municipio'], $data['id_estado_FK']) && trim($data['nom_municipio']) !== '' && intval($data['id_estado_FK']) > 0;
        case 'localidades':
            return isset($data['nom_localidad'], $data['id_municipio_FK']) && trim($data['nom_localidad']) !== '' && intval($data['id_municipio_FK']) > 0;
        case 'codigos_postales':
            return isset($data['codigo_postal']) && trim($data['codigo_postal']) !== '';
        case 'colonias':
            return isset($data['nom_colonia'], $data['id_localidad_FK'], $data['id_codigo_postal_FK'])
                && trim($data['nom_colonia']) !== ''
                && intval($data['id_localidad_FK']) > 0
                && intval($data['id_codigo_postal_FK']) > 0;
        case 'calles':
            return isset($data['nom_calle'], $data['id_colonia_FK']) && trim($data['nom_calle']) !== '' && intval($data['id_colonia_FK']) > 0;
        case 'direcciones':
            return isset($data['num_exterior'], $data['id_calle_FK'])
                && trim((string) $data['num_exterior']) !== ''
                && intval($data['id_calle_FK']) > 0;
    }
    return false;
}

function cargarListasRelacionadas($app)
{
    return [
        'paises' => $app->leerPaises(),
        'estados' => $app->leerEstados(),
        'municipios' => $app->leerMunicipios(),
        'localidades' => $app->leerLocalidades(),
        'codigos_postales' => $app->leerCodigosPostales(),
        'colonias' => $app->leerColonias(),
        'calles' => $app->leerCalles(),
    ];
}

function indexarPorId($lista, $idCampo, $valorCampo)
{
    $mapa = [];
    foreach ($lista as $item) {
        $mapa[$item[$idCampo]] = $item[$valorCampo];
    }
    return $mapa;
}

function enriquecerRegistros($entidad, $registros, $listas)
{
    $mapPaises = indexarPorId($listas['paises'], 'id_pais', 'nom_pais');
    $mapEstados = indexarPorId($listas['estados'], 'id_estado', 'nom_estado');
    $mapMunicipios = indexarPorId($listas['municipios'], 'id_municipio', 'nom_municipio');
    $mapLocalidades = indexarPorId($listas['localidades'], 'id_localidad', 'nom_localidad');
    $mapCodigos = indexarPorId($listas['codigos_postales'], 'id_codigo_postal', 'codigo_postal');
    $mapColonias = indexarPorId($listas['colonias'], 'id_colonia', 'nom_colonia');
    $mapCalles = indexarPorId($listas['calles'], 'id_calle', 'nom_calle');

    foreach ($registros as &$item) {
        if ($entidad === 'estados') {
            $item['pais_rel'] = isset($mapPaises[$item['id_pais_FK']]) ? $mapPaises[$item['id_pais_FK']] : 'N/A';
        } elseif ($entidad === 'municipios') {
            $item['estado_rel'] = isset($mapEstados[$item['id_estado_FK']]) ? $mapEstados[$item['id_estado_FK']] : 'N/A';
        } elseif ($entidad === 'localidades') {
            $item['municipio_rel'] = isset($mapMunicipios[$item['id_municipio_FK']]) ? $mapMunicipios[$item['id_municipio_FK']] : 'N/A';
        } elseif ($entidad === 'colonias') {
            $item['localidad_rel'] = isset($mapLocalidades[$item['id_localidad_FK']]) ? $mapLocalidades[$item['id_localidad_FK']] : 'N/A';
            $item['codigo_postal_rel'] = isset($mapCodigos[$item['id_codigo_postal_FK']]) ? $mapCodigos[$item['id_codigo_postal_FK']] : 'N/A';
        } elseif ($entidad === 'calles') {
            $item['colonia_rel'] = isset($mapColonias[$item['id_colonia_FK']]) ? $mapColonias[$item['id_colonia_FK']] : 'N/A';
        } elseif ($entidad === 'direcciones') {
            $item['calle_rel'] = isset($mapCalles[$item['id_calle_FK']]) ? $mapCalles[$item['id_calle_FK']] : 'N/A';
        }
    }
    unset($item);

    return $registros;
}

function columnasEntidad($entidad)
{
    switch ($entidad) {
        case 'paises':
            return ['nom_pais' => 'Nombre'];
        case 'estados':
            return ['nom_estado' => 'Nombre', 'pais_rel' => 'Pais'];
        case 'municipios':
            return ['nom_municipio' => 'Nombre', 'estado_rel' => 'Estado'];
        case 'localidades':
            return ['nom_localidad' => 'Nombre', 'municipio_rel' => 'Municipio'];
        case 'codigos_postales':
            return ['codigo_postal' => 'Codigo Postal'];
        case 'colonias':
            return ['nom_colonia' => 'Nombre', 'localidad_rel' => 'Localidad', 'codigo_postal_rel' => 'Codigo Postal'];
        case 'calles':
            return ['nom_calle' => 'Nombre', 'colonia_rel' => 'Colonia'];
        case 'direcciones':
            return ['num_exterior' => 'No. Exterior', 'num_interior' => 'No. Interior', 'calle_rel' => 'Calle'];
        default:
            return [];
    }
}

/**
 * Filtra en memoria (SP sin LIKE) por ID y columnas visibles del listado.
 *
 * @param array<int, array<string, mixed>> $registros
 * @param array<string, array<string, string>> $entidadesMeta mapa global $entidades
 * @return array<int, array<string, mixed>>
 */
function aplicarBusquedaDireccion(array $registros, ?string $q, string $entidad, array $entidadesMeta): array
{
    if (!joyeria_list_search_active($q)) {
        return $registros;
    }
    $meta = $entidadesMeta[$entidad] ?? [];
    $idField = $meta['id'] ?? 'id';
    $cols = columnasEntidad($entidad);
    $keys = array_merge([$idField], array_keys($cols));

    return joyeria_filter_rows_by_search($registros, $q, $keys);
}

$registro = null;
$listas = [];

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Catalogo de Direcciones</h2>
        </header>

        <div class="admin-main">
            <?php
            switch ($accion) {
                case 'crear':
                    $listas = cargarListasRelacionadas($app);
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarDatosEntidad($entidad, $_POST)) {
                        try {
                            ejecutarCrear($app, $entidad, $_POST);
                            $mensaje = $entidades[$entidad]['singular'] . ' creado correctamente';
                            $registros = obtenerRegistrosPorEntidad($app, $entidad);
                            $registros = enriquecerRegistros($entidad, $registros, $listas);
                            $registros = aplicarBusquedaDireccion($registros, $busqueda, $entidad, $entidades);
                            $columnas = columnasEntidad($entidad);
                            require_once (__DIR__ . "/views/direccion/index.php");
                        } catch (Exception $e) {
                            $error = 'Error al crear: ' . $e->getMessage();
                            require_once (__DIR__ . "/views/direccion/formulario.php");
                        }
                    } else {
                        require_once (__DIR__ . "/views/direccion/formulario.php");
                    }
                    break;

                case 'actualizar':
                    $listas = cargarListasRelacionadas($app);
                    $registro = obtenerRegistroPorEntidad($app, $entidad, $id);

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id && validarDatosEntidad($entidad, $_POST)) {
                        try {
                            ejecutarActualizar($app, $entidad, $id, $_POST);
                            $mensaje = $entidades[$entidad]['singular'] . ' actualizado correctamente';
                            $registros = obtenerRegistrosPorEntidad($app, $entidad);
                            $registros = enriquecerRegistros($entidad, $registros, $listas);
                            $registros = aplicarBusquedaDireccion($registros, $busqueda, $entidad, $entidades);
                            $columnas = columnasEntidad($entidad);
                            require_once (__DIR__ . "/views/direccion/index.php");
                        } catch (Exception $e) {
                            $error = 'Error al actualizar: ' . $e->getMessage();
                            require_once (__DIR__ . "/views/direccion/formulario.php");
                        }
                    } else {
                        require_once (__DIR__ . "/views/direccion/formulario.php");
                    }
                    break;

                case 'borrar':
                    try {
                        if ($id) {
                            ejecutarBorrar($app, $entidad, $id);
                            $mensaje = $entidades[$entidad]['singular'] . ' eliminado correctamente';
                        }
                    } catch (Exception $e) {
                        $error = 'Error al eliminar: ' . $e->getMessage();
                    }
                    $listas = cargarListasRelacionadas($app);
                    $registros = obtenerRegistrosPorEntidad($app, $entidad);
                    $registros = enriquecerRegistros($entidad, $registros, $listas);
                    $registros = aplicarBusquedaDireccion($registros, $busqueda, $entidad, $entidades);
                    $columnas = columnasEntidad($entidad);
                    require_once (__DIR__ . "/views/direccion/index.php");
                    break;

                case 'leer':
                default:
                    $listas = cargarListasRelacionadas($app);
                    $registros = obtenerRegistrosPorEntidad($app, $entidad);
                    $registros = enriquecerRegistros($entidad, $registros, $listas);
                    $registros = aplicarBusquedaDireccion($registros, $busqueda, $entidad, $entidades);
                    $columnas = columnasEntidad($entidad);
                    require_once (__DIR__ . "/views/direccion/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
