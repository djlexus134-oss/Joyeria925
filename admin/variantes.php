<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/variantes.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Variantes();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Catalogo de Variantes</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['nombre']) && trim($_POST['nombre']) !== '') {
                try {
                    $nuevoId = $app->crearTipo($_POST);
                    if ($nuevoId > 0) {
                        $mensaje = 'Tipo de variante creado correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el tipo: ' . $e->getMessage();
                }
                $tipos = $app->leerTipos($busqueda);
                require_once (__DIR__ . "/views/variantes/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El nombre del tipo es obligatorio.';
                }
                require_once (__DIR__ . "/views/variantes/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['nombre']) && trim($_POST['nombre']) !== '') {
                try {
                    $cantidad = $app->actualizarTipo((int) $id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Tipo actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el tipo: ' . $e->getMessage();
                }
                $tipos = $app->leerTipos($busqueda);
                require_once (__DIR__ . "/views/variantes/index.php");
            } else {
                $tipo = $app->leerUnoTipo((int) $id);
                $valores = $tipo ? $app->leerValoresPorTipo((int) $id) : [];
                require_once (__DIR__ . "/views/variantes/formulario.php");
            }
            break;

        case 'borrar_valor':
            $idValor = isset($_GET['id_valor']) ? (int) $_GET['id_valor'] : 0;
            if ($id && $idValor > 0) {
                try {
                    $cantidad = $app->borrarValor($idValor);
                    $mensaje = $cantidad
                        ? 'Valor dado de baja correctamente'
                        : 'No se pudo dar de baja el valor';
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el valor: ' . $e->getMessage();
                }
            }
            $tipo = $app->leerUnoTipo((int) $id);
            $valores = $tipo ? $app->leerValoresPorTipo((int) $id) : [];
            require_once (__DIR__ . "/views/variantes/formulario.php");
            break;

        case 'crear_valor':
            if ($id && isset($_POST['valor']) && trim($_POST['valor']) !== '') {
                try {
                    $_POST['id_variante_tipo_FK'] = (int) $id;
                    $app->crearValor($_POST);
                    $mensaje = 'Valor agregado correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al agregar el valor: ' . $e->getMessage();
                }
            } else {
                $mensaje = 'Indica un valor valido.';
            }
            $tipo = $app->leerUnoTipo((int) $id);
            $valores = $tipo ? $app->leerValoresPorTipo((int) $id) : [];
            require_once (__DIR__ . "/views/variantes/formulario.php");
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrarTipo((int) $id);
                    $mensaje = $cantidad
                        ? 'Tipo dado de baja correctamente'
                        : 'No se pudo dar de baja el tipo';
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el tipo: ' . $e->getMessage();
                }
            }
            $tipos = $app->leerTipos($busqueda);
            require_once (__DIR__ . "/views/variantes/index.php");
            break;

        case 'leer':
        default:
            $tipos = $app->leerTipos($busqueda);
            require_once (__DIR__ . "/views/variantes/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
