<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/familia.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Familia();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Gestión de Familias</h2>
        </header>

        <div class="admin-main">
            <?php
            switch($accion) {
                case 'crear':
                    if(isset($_POST['nom_familia']) && !empty(trim($_POST['nom_familia']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->crear($data);
                            if($cantidad) {
                                $mensaje = "Familia creada correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al crear la familia: ' . $e->getMessage();
                        }
                        $familias = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/familia/index.php");
                    } else {
                        require_once (__DIR__ . "/views/familia/formulario.php");
                    }
                    break;
                
                case 'actualizar':
                    if(isset($_POST['nom_familia']) && !empty(trim($_POST['nom_familia']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->actualizar($id, $data);
                            if($cantidad) {
                                $mensaje = "Familia actualizada correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al actualizar la familia: ' . $e->getMessage();
                        }
                        $familias = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/familia/index.php");
                    } else {
                        $familia = $app->leerUno($id);
                        require_once (__DIR__ . "/views/familia/formulario.php");
                    }
                    break;
                
                case 'borrar':
                    if($id) {
                        try {
                            $cantidad = $app->borrar($id);
                            if($cantidad) {
                                $mensaje = "Familia eliminada correctamente";
                            } else {
                                $mensaje = "No se pudo eliminar la familia";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al eliminar la familia: ' . $e->getMessage();
                        }
                    }
                    $familias = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/familia/index.php");
                    break;
                
                case 'leer':
                default:
                    $familias = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/familia/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
