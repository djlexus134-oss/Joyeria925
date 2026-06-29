<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/impuestos_historico.php");
require_once (__DIR__ . "/models/ventas.php");

$app = new ImpuestosHistorico();
$ventasApp = new Ventas();
$idImpuestoDefault = $ventasApp->obtenerIdImpuestoDefault();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;
$impuestosBase = $app->leerImpuestos();

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion Historica de Impuestos</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['id_impuesto_FK']) && intval($_POST['id_impuesto_FK']) > 0 && isset($_POST['porcentaje']) && trim((string) $_POST['porcentaje']) !== '' && isset($_POST['fecha_inicio']) && trim((string) $_POST['fecha_inicio']) !== '') {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Historico de impuesto creado correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el historico: ' . $e->getMessage();
                }
                $historicos = $app->leer();
                require_once (__DIR__ . "/views/impuestos_historico/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios para crear el historico.';
                }
                require_once (__DIR__ . "/views/impuestos_historico/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['id_impuesto_FK']) && intval($_POST['id_impuesto_FK']) > 0 && isset($_POST['porcentaje']) && trim((string) $_POST['porcentaje']) !== '' && isset($_POST['fecha_inicio']) && trim((string) $_POST['fecha_inicio']) !== '') {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Historico actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el historico: ' . $e->getMessage();
                }
                $historicos = $app->leer();
                require_once (__DIR__ . "/views/impuestos_historico/index.php");
            } else {
                $historico = $app->leerUno($id);
                require_once (__DIR__ . "/views/impuestos_historico/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    if ($cantidad) {
                        $mensaje = 'Historico eliminado correctamente';
                    } else {
                        $mensaje = 'No se pudo eliminar el historico';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar el historico: ' . $e->getMessage();
                }
            }
            $historicos = $app->leer();
            require_once (__DIR__ . "/views/impuestos_historico/index.php");
            break;

        case 'leer':
        default:
            $historicos = $app->leer();
            require_once (__DIR__ . "/views/impuestos_historico/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
