<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/puesto.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Puesto();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Gestión de Puestos</h2>
        </header>

        <div class="admin-main">
            <?php
            switch($accion) {
                case 'crear':
                    if(isset($_POST['nombre_puesto']) && !empty(trim($_POST['nombre_puesto']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->crear($data);
                            if($cantidad) {
                                $mensaje = "Puesto creado correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al crear el puesto: ' . $e->getMessage();
                        }
                        $puestos = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/puesto/index.php");
                    } else {
                        require_once (__DIR__ . "/views/puesto/formulario.php");
                    }
                    break;
                
                case 'actualizar':
                    if(isset($_POST['nombre_puesto']) && !empty(trim($_POST['nombre_puesto']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->actualizar($id, $data);
                            if($cantidad) {
                                $mensaje = "Puesto actualizado correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al actualizar el puesto: ' . $e->getMessage();
                        }
                        $puestos = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/puesto/index.php");
                    } else {
                        $puesto = $app->leerUno($id);
                        require_once (__DIR__ . "/views/puesto/formulario.php");
                    }
                    break;
                
                case 'borrar':
                    if($id) {
                        try {
                            $cantidad = $app->borrar($id, null);
                            if($cantidad) {
                                $mensaje = "Puesto eliminado correctamente";
                            } else {
                                $mensaje = "No se pudo eliminar el puesto";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al eliminar el puesto: ' . $e->getMessage();
                        }
                    }
                    $puestos = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/puesto/index.php");
                    break;
                
                case 'leer':
                default:
                    $puestos = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/puesto/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
