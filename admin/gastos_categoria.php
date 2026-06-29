<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/gastos_categoria.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new GastosCategoria();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Categorias de Gasto</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['nombre']) && !empty(trim($_POST['nombre']))) {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Categoria creada correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear la categoria: ' . $e->getMessage();
                }
                $categorias = $app->leer($busqueda);
                require_once (__DIR__ . "/views/gastos_categoria/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El nombre de la categoria es obligatorio.';
                }
                require_once (__DIR__ . "/views/gastos_categoria/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['nombre']) && !empty(trim($_POST['nombre']))) {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Categoria actualizada correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la categoria: ' . $e->getMessage();
                }
                $categorias = $app->leer($busqueda);
                require_once (__DIR__ . "/views/gastos_categoria/index.php");
            } else {
                $categoria = $app->leerUno($id);
                require_once (__DIR__ . "/views/gastos_categoria/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    if ($cantidad) {
                        $mensaje = 'Categoria dada de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja la categoria';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja la categoria: ' . $e->getMessage();
                }
            }
            $categorias = $app->leer($busqueda);
            require_once (__DIR__ . "/views/gastos_categoria/index.php");
            break;

        case 'leer':
        default:
            $categorias = $app->leer($busqueda);
            require_once (__DIR__ . "/views/gastos_categoria/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
