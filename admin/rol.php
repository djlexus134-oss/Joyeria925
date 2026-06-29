<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/rol.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Rol();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Gestión de Roles</h2>
        </header>

        <div class="admin-main">
            <?php
            switch($accion) {
                case 'crear':
                    if(isset($_POST['nombre_rol']) && !empty(trim($_POST['nombre_rol']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->crear($data);
                            if($cantidad) {
                                $mensaje = "Rol creado correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al crear el rol: ' . $e->getMessage();
                        }
                        $roles = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/rol/index.php");
                    } else {
                        require_once (__DIR__ . "/views/rol/formulario.php");
                    }
                    break;
                
                case 'actualizar':
                    if(isset($_POST['nombre_rol']) && !empty(trim($_POST['nombre_rol']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->actualizar($id, $data);
                            if($cantidad) {
                                $mensaje = "Rol actualizado correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al actualizar el rol: ' . $e->getMessage();
                        }
                        $roles = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/rol/index.php");
                    } else {
                        $rol = $app->leerUno($id);
                        require_once (__DIR__ . "/views/rol/formulario.php");
                    }
                    break;
                
                case 'borrar':
                    if($id) {
                        try {
                            $cantidad = $app->borrar($id, null);
                            if($cantidad) {
                                $mensaje = "Rol eliminado correctamente";
                            } else {
                                $mensaje = "No se pudo eliminar el rol";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al eliminar el rol: ' . $e->getMessage();
                        }
                    }
                    $roles = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/rol/index.php");
                    break;
                
                case 'leer':
                default:
                    $roles = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/rol/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
