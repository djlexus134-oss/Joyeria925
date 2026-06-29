<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/sub_familia.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new SubFamilia();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Gestión de Subfamilias</h2>
        </header>

        <div class="admin-main">
            <?php
            switch($accion) {
                case 'crear':
                    if(isset($_POST['nom_sub_familia']) && !empty(trim($_POST['nom_sub_familia'])) && isset($_POST['id_familia_FK'])) {
                        $data = $_POST;
                        $cantidad = $app->crear($data);
                        if($cantidad) {
                            $mensaje = "Subfamilia creada correctamente";
                        }
                        $subfamilias = $app->leer(null, $busqueda);
                        require_once (__DIR__ . "/views/sub_familia/index.php");
                    } else {
                        $familias = $app->obtenerFamilias();
                        require_once (__DIR__ . "/views/sub_familia/formulario.php");
                    }
                    break;
                
                case 'actualizar':
                    if(isset($_POST['nom_sub_familia']) && !empty(trim($_POST['nom_sub_familia'])) && isset($_POST['id_familia_FK'])) {
                        $data = $_POST;
                        $cantidad = $app->actualizar($id, $data);
                        if($cantidad) {
                            $mensaje = "Subfamilia actualizada correctamente";
                        }
                        $subfamilias = $app->leer(null, $busqueda);
                        require_once (__DIR__ . "/views/sub_familia/index.php");
                    } else {
                        $subfamilia = $app->leerUno($id);
                        $familias = $app->obtenerFamilias();
                        require_once (__DIR__ . "/views/sub_familia/formulario.php");
                    }
                    break;
                
                case 'borrar':
                    if($id) {
                        $cantidad = $app->borrar($id);
                        if($cantidad) {
                            $mensaje = "Subfamilia eliminada correctamente";
                        } else {
                            $mensaje = "No se pudo eliminar la subfamilia";
                        }
                    }
                    $subfamilias = $app->leer(null, $busqueda);
                    require_once (__DIR__ . "/views/sub_familia/index.php");
                    break;
                
                case 'leer':
                default:
                    $subfamilias = $app->leer(null, $busqueda);
                    require_once (__DIR__ . "/views/sub_familia/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
