<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/catalogo_compra.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new CatalogoCompra();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

$catalogos = $app->obtenerCatalogos();

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Catalogo de Compra</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['tipo']) && trim((string) $_POST['tipo']) !== ''
                && isset($_POST['descripcion']) && trim((string) $_POST['descripcion']) !== '') {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Articulo de compra creado correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el articulo: ' . $e->getMessage();
                }
                $articulos = $app->leer($busqueda);
                require_once (__DIR__ . "/views/catalogo_compra/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El tipo y la descripcion son obligatorios.';
                }
                require_once (__DIR__ . "/views/catalogo_compra/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['tipo']) && trim((string) $_POST['tipo']) !== ''
                && isset($_POST['descripcion']) && trim((string) $_POST['descripcion']) !== '') {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Articulo actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el articulo: ' . $e->getMessage();
                }
                $articulos = $app->leer($busqueda);
                require_once (__DIR__ . "/views/catalogo_compra/index.php");
            } else {
                $articulo = $app->leerUno($id);
                require_once (__DIR__ . "/views/catalogo_compra/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $idUsuarioActual = $_SESSION['id_usuario'] ?? null;
                    $cantidad = $app->borrar($id, $idUsuarioActual);
                    if ($cantidad) {
                        $mensaje = 'Articulo dado de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja el articulo';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el articulo: ' . $e->getMessage();
                }
            }
            $articulos = $app->leer($busqueda);
            require_once (__DIR__ . "/views/catalogo_compra/index.php");
            break;

        case 'leer':
        default:
            $articulos = $app->leer($busqueda);
            require_once (__DIR__ . "/views/catalogo_compra/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
