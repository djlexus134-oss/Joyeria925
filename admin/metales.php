<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/metales.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Metales();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Gestion de Metales</h2>
        </header>

        <div class="admin-main">
            <?php
            switch($accion) {
                case 'crear':
                    if(isset($_POST['nom_metal']) && !empty(trim($_POST['nom_metal']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->crear($data);
                            if($cantidad) {
                                $mensaje = "Metal creado correctamente";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al crear el metal: ' . $e->getMessage();
                        }
                        $metales = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/metales/index.php");
                    } else {
                        if (!empty($_POST)) {
                            $mensaje = 'Faltan campos obligatorios para crear el metal.';
                        }
                        require_once (__DIR__ . "/views/metales/formulario.php");
                    }
                    break;

                case 'actualizar':
                    if(isset($_POST['nom_metal']) && !empty(trim($_POST['nom_metal']))) {
                        $data = $_POST;
                        try {
                            $cantidad = $app->actualizar($id, $data);
                            if($cantidad) {
                                $mensaje = "Metal actualizado correctamente";
                            } else {
                                $mensaje = "No se realizaron cambios en el metal";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al actualizar el metal: ' . $e->getMessage();
                        }
                        $metales = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/metales/index.php");
                    } else {
                        $metal = $app->leerUno($id);
                        require_once (__DIR__ . "/views/metales/formulario.php");
                    }
                    break;

                case 'borrar':
                    if($id) {
                        try {
                            $id_usuario_actual = $_SESSION['id_usuario'] ?? null;
                            $cantidad = $app->borrar($id, $id_usuario_actual);
                            if($cantidad) {
                                $mensaje = "Metal dado de baja correctamente";
                            } else {
                                $mensaje = "No se pudo dar de baja el metal";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al dar de baja el metal: ' . $e->getMessage();
                        }
                    }
                    $metales = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/metales/index.php");
                    break;

                case 'leer':
                default:
                    $metales = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/metales/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
