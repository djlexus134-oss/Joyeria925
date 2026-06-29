<?php
require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/insumos.php';
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Insumos();
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$accion = isset($_GET['accion']) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;
$catalogos = $app->obtenerCatalogos();

require_once __DIR__ . '/views/header.php';

$tituloModulo = ($accion === 'etiquetas') ? 'Etiquetas de insumos (POS)' : 'Gestion de Insumos';
?>

<header class="admin-header">
    <h2><?php echo htmlspecialchars($tituloModulo, ENT_QUOTES, 'UTF-8'); ?></h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== '') {
                try {
                    $idGenerado = $app->crear($_POST);
                    $mensaje = $idGenerado > 0
                        ? 'Insumo creado correctamente. SKU: ' . $idGenerado . '/1'
                        : 'No se pudo crear el insumo.';
                    $insumos = $app->leer($busqueda);
                    require __DIR__ . '/views/insumos/index.php';
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el insumo: ' . $e->getMessage();
                    require __DIR__ . '/views/insumos/formulario.php';
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El nombre del insumo es obligatorio.';
                }
                require __DIR__ . '/views/insumos/formulario.php';
            }
            break;

        case 'actualizar':
            if ($id && isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== '') {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    $mensaje = $cantidad ? 'Insumo actualizado correctamente.' : 'No se realizaron cambios.';
                    $insumos = $app->leer($busqueda);
                    require __DIR__ . '/views/insumos/index.php';
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el insumo: ' . $e->getMessage();
                    $insumo = $app->leerUno($id);
                    $existenciasPorTienda = $insumo ? $app->obtenerExistenciasPorTienda($id) : [];
                    require __DIR__ . '/views/insumos/formulario.php';
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El nombre del insumo es obligatorio.';
                }
                $insumo = $id ? $app->leerUno($id) : null;
                $existenciasPorTienda = $insumo ? $app->obtenerExistenciasPorTienda($id) : [];
                require __DIR__ . '/views/insumos/formulario.php';
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    $mensaje = $cantidad ? 'Insumo dado de baja correctamente.' : 'No se pudo dar de baja el insumo.';
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el insumo: ' . $e->getMessage();
                }
            }
            $insumos = $app->leer($busqueda);
            require __DIR__ . '/views/insumos/index.php';
            break;

        case 'etiquetas':
            $insumos = $app->leer($busqueda);
            require __DIR__ . '/views/insumos/etiquetas.php';
            break;

        case 'leer':
        default:
            $insumos = $app->leer($busqueda);
            require __DIR__ . '/views/insumos/index.php';
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
