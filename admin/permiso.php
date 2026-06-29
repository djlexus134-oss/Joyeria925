<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/permiso.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Permiso();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Gestión de Permisos</h2>
        </header>

        <div class="admin-main">
            <?php
            switch($accion) {
                case 'crear':
                    if(isset($_POST['nombre_permiso']) && !empty(trim($_POST['nombre_permiso']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->crear($data);
                            if($cantidad) {
                                $mensaje = "Permiso creado correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al crear el permiso: ' . $e->getMessage();
                        }
                        $permisos = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/permiso/index.php");
                    } else {
                        require_once (__DIR__ . "/views/permiso/formulario.php");
                    }
                    break;
                
                case 'actualizar':
                    if(isset($_POST['nombre_permiso']) && !empty(trim($_POST['nombre_permiso']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->actualizar($id, $data);
                            if($cantidad) {
                                $mensaje = "Permiso actualizado correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al actualizar el permiso: ' . $e->getMessage();
                        }
                        $permisos = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/permiso/index.php");
                    } else {
                        $permiso = $app->leerUno($id);
                        require_once (__DIR__ . "/views/permiso/formulario.php");
                    }
                    break;
                
                case 'borrar':
                    if($id) {
                        try {
                            $cantidad = $app->borrar($id, null);
                            if($cantidad) {
                                $mensaje = "Permiso eliminado correctamente";
                            } else {
                                $mensaje = "No se pudo eliminar el permiso";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al eliminar el permiso: ' . $e->getMessage();
                        }
                    }
                    $permisos = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/permiso/index.php");
                    break;
                
                case 'leer':
                default:
                    $permisos = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/permiso/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
