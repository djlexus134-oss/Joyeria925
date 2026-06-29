<?php
declare(strict_types=1);

require_once __DIR__ . '/models/promociones_banner.php';
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');
$app = new PromocionesBanner();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$accion = isset($_GET['accion']) ? (string) $_GET['accion'] : null;
$mensaje = null;

$catalogosPiezas = [];
try {
    $catalogosPiezas = $app->obtenerPiezasOpciones();
} catch (Throwable $e) {
    $mensaje = 'No se pudieron cargar las piezas: ' . $e->getMessage();
}

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2>Banners del catálogo (web)</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
                $irListado = true;
                try {
                    $nuevoId = $app->crear($_POST);
                    if ($nuevoId > 0) {
                        $mensaje = 'Banner creado correctamente';
                    }
                } catch (Throwable $e) {
                    $mensaje = 'Error al crear: ' . $e->getMessage();
                    $irListado = false;
                }
                if ($irListado) {
                    $banners = $app->leer($busqueda);
                    require_once __DIR__ . '/views/promociones_banner/index.php';
                } else {
                    require_once __DIR__ . '/views/promociones_banner/formulario.php';
                }
            } else {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
                    $mensaje = ($mensaje ?? '') ?: 'Revisa los campos obligatorios.';
                }
                require_once __DIR__ . '/views/promociones_banner/formulario.php';
            }
            break;

        case 'actualizar':
            if ($id !== null && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
                $irListado = true;
                try {
                    $app->actualizar($id, $_POST);
                    $mensaje = 'Banner actualizado correctamente';
                } catch (Throwable $e) {
                    $mensaje = 'Error al actualizar: ' . $e->getMessage();
                    $irListado = false;
                }
                if ($irListado) {
                    $banners = $app->leer($busqueda);
                    require_once __DIR__ . '/views/promociones_banner/index.php';
                } else {
                    $banner = $app->leerUno((int) $id);
                    require_once __DIR__ . '/views/promociones_banner/formulario.php';
                }
            } else {
                $banner = $id !== null && $id > 0 ? $app->leerUno($id) : null;
                require_once __DIR__ . '/views/promociones_banner/formulario.php';
            }
            break;

        case 'borrar':
            if ($id !== null && $id > 0) {
                try {
                    $app->eliminar($id);
                    $mensaje = 'Banner desactivado';
                } catch (Throwable $e) {
                    $mensaje = 'Error: ' . $e->getMessage();
                }
            }
            $banners = $app->leer($busqueda);
            require_once __DIR__ . '/views/promociones_banner/index.php';
            break;

        case 'leer':
        default:
            $banners = $app->leer($busqueda);
            require_once __DIR__ . '/views/promociones_banner/index.php';
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
