<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/proveedores.php");
require_once (__DIR__ . "/models/proveedor_contactos.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Proveedores();
$appContactos = new ProveedorContactos();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;
$abrirFormContactoProveedorId = null;
$editarContactoId = null;

$direcciones = [];
try {
    $direcciones = $app->obtenerDirecciones();
} catch (Exception $e) {
    $mensaje = 'No se pudieron cargar las direcciones: ' . $e->getMessage();
}

function proveedoresMapearDireccionRapida(array &$post): void
{
    $post['num_exterior'] = $post['rapida_num_exterior'] ?? ($post['num_exterior'] ?? '');
    $post['num_interior'] = $post['rapida_num_interior'] ?? ($post['num_interior'] ?? '');
    $post['id_calle_FK'] = $post['rapida_id_calle_FK'] ?? ($post['id_calle_FK'] ?? '');
}

function proveedoresConstruirMapaContactos(Proveedores $app, array $proveedores): array
{
    if (empty($proveedores)) {
        return [];
    }
    $idsProveedor = array_map(static function ($proveedor) {
        return (int) ($proveedor['id_proveedor'] ?? 0);
    }, $proveedores);
    return $app->obtenerContactosActivosPorProveedorIds($idsProveedor);
}

function proveedoresCargarProveedoresActivos(ProveedorContactos $appContactos): array
{
    try {
        return $appContactos->obtenerProveedoresActivos();
    } catch (Exception $e) {
        return [];
    }
}

$tituloModulo = ($accion === 'contactos' || ($accion !== null && str_starts_with($accion, 'contacto_')))
    ? 'Contactos de proveedor'
    : 'Gestion de Proveedores';

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2><?php echo htmlspecialchars($tituloModulo, ENT_QUOTES, 'UTF-8'); ?></h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear_contacto':
            $idProveedorContacto = isset($_POST['id_proveedor_FK']) ? (int) $_POST['id_proveedor_FK'] : 0;
            $abrirFormContactoProveedorId = $idProveedorContacto > 0 ? $idProveedorContacto : null;
            if ($idProveedorContacto > 0 && isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== '') {
                try {
                    $cantidad = $appContactos->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Contacto agregado correctamente';
                        $abrirFormContactoProveedorId = null;
                    } else {
                        $mensaje = 'No se pudo agregar el contacto';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al agregar contacto: ' . $e->getMessage();
                }
            } else {
                $mensaje = 'El nombre del contacto es obligatorio.';
            }
            $proveedores = $app->leer($busqueda);
            $contactosPorProveedor = proveedoresConstruirMapaContactos($app, $proveedores);
            require_once (__DIR__ . "/views/proveedores/index.php");
            break;

        case 'actualizar_contacto':
            $idContacto = $id;
            $idProveedorContacto = isset($_POST['id_proveedor_FK']) ? (int) $_POST['id_proveedor_FK'] : 0;
            $abrirFormContactoProveedorId = $idProveedorContacto > 0 ? $idProveedorContacto : null;
            $editarContactoId = $idContacto;
            if ($idContacto && $idProveedorContacto > 0 && isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== '') {
                try {
                    $cantidad = $appContactos->actualizar($idContacto, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Contacto actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios en el contacto';
                    }
                    $editarContactoId = null;
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar contacto: ' . $e->getMessage();
                }
            } else {
                $mensaje = 'El nombre del contacto es obligatorio.';
            }
            $proveedores = $app->leer($busqueda);
            $contactosPorProveedor = proveedoresConstruirMapaContactos($app, $proveedores);
            require_once (__DIR__ . "/views/proveedores/index.php");
            break;

        case 'borrar_contacto':
            $idContacto = $id;
            if ($idContacto) {
                try {
                    $idUsuarioActual = $_SESSION['id_usuario'] ?? null;
                    $cantidad = $appContactos->borrar($idContacto, $idUsuarioActual);
                    if ($cantidad) {
                        $mensaje = 'Contacto eliminado correctamente';
                    } else {
                        $mensaje = 'No se pudo eliminar el contacto';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar contacto: ' . $e->getMessage();
                }
            }
            $proveedores = $app->leer($busqueda);
            $contactosPorProveedor = proveedoresConstruirMapaContactos($app, $proveedores);
            require_once (__DIR__ . "/views/proveedores/index.php");
            break;

        case 'contacto_crear':
            if (isset($_POST['id_proveedor_FK']) && (int) $_POST['id_proveedor_FK'] > 0
                && isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== '') {
                try {
                    $cantidad = $appContactos->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Contacto creado correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el contacto: ' . $e->getMessage();
                }
                $contactos = $appContactos->leer($busqueda);
                require_once (__DIR__ . "/views/proveedores/contactos.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El proveedor y el nombre del contacto son obligatorios.';
                }
                $proveedores = proveedoresCargarProveedoresActivos($appContactos);
                require_once (__DIR__ . "/views/proveedores/contacto_formulario.php");
            }
            break;

        case 'contacto_actualizar':
            if (isset($_POST['id_proveedor_FK']) && (int) $_POST['id_proveedor_FK'] > 0
                && isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== '') {
                try {
                    $cantidad = $appContactos->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Contacto actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el contacto: ' . $e->getMessage();
                }
                $contactos = $appContactos->leer($busqueda);
                require_once (__DIR__ . "/views/proveedores/contactos.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El proveedor y el nombre del contacto son obligatorios.';
                }
                $contacto = $appContactos->leerUno($id);
                $proveedores = proveedoresCargarProveedoresActivos($appContactos);
                require_once (__DIR__ . "/views/proveedores/contacto_formulario.php");
            }
            break;

        case 'contacto_borrar':
            if ($id) {
                try {
                    $idUsuarioActual = $_SESSION['id_usuario'] ?? null;
                    $cantidad = $appContactos->borrar($id, $idUsuarioActual);
                    if ($cantidad) {
                        $mensaje = 'Contacto dado de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja el contacto';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el contacto: ' . $e->getMessage();
                }
            }
            $contactos = $appContactos->leer($busqueda);
            require_once (__DIR__ . "/views/proveedores/contactos.php");
            break;

        case 'contactos':
            $contactos = $appContactos->leer($busqueda);
            require_once (__DIR__ . "/views/proveedores/contactos.php");
            break;

        case 'crear':
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                require_once (__DIR__ . "/views/proveedores/formulario.php");
                break;
            }
            $incProvDir = isset($_POST['incluir_direccion']) && (string) $_POST['incluir_direccion'] === '1';
            $modoProv = isset($_POST['modo_direccion_prov']) ? (string) $_POST['modo_direccion_prov'] : 'catalogo';
            if ($modoProv !== 'rapida') {
                $modoProv = 'catalogo';
            }
            $nuevaDirRapida = $incProvDir && $modoProv === 'rapida';
            $_POST['nueva_direccion_rapida'] = $nuevaDirRapida ? '1' : '0';
            if ($nuevaDirRapida) {
                proveedoresMapearDireccionRapida($_POST);
            }
            $rapidaLista = !$nuevaDirRapida || (
                isset($_POST['id_calle_FK']) && !empty(trim((string) $_POST['id_calle_FK'])) &&
                isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior'])
            );
            $catalogoOk = !$incProvDir || $nuevaDirRapida ||
                (isset($_POST['id_direccion_FK']) && (int) $_POST['id_direccion_FK'] > 0);
            $dirProvOk = !$incProvDir || ($nuevaDirRapida ? $rapidaLista : $catalogoOk);
            if (isset($_POST['razon_social']) && trim((string) $_POST['razon_social']) !== '' && $dirProvOk) {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Proveedor creado correctamente';
                    }
                    $proveedores = $app->leer($busqueda);
                    $contactosPorProveedor = proveedoresConstruirMapaContactos($app, $proveedores);
                    require_once (__DIR__ . "/views/proveedores/index.php");
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el proveedor: ' . $e->getMessage();
                    require_once (__DIR__ . "/views/proveedores/formulario.php");
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Completa la razon social. Si registras direccion, elige del catalogo o captura direccion rapida (calle y numero).';
                }
                require_once (__DIR__ . "/views/proveedores/formulario.php");
            }
            break;

        case 'actualizar':
            $provRow = ($id && !empty($_POST)) ? $app->leerUno($id) : null;
            $tieneDirProv = is_array($provRow) && !empty($provRow['id_direccion_FK']);
            $incProvAct = isset($_POST['incluir_direccion']) && (string) $_POST['incluir_direccion'] === '1';
            $exigeDirProv = $tieneDirProv || $incProvAct;
            $modoProvAct = isset($_POST['modo_direccion_prov']) ? (string) $_POST['modo_direccion_prov'] : 'catalogo';
            if ($modoProvAct !== 'rapida' || $tieneDirProv) {
                $modoProvAct = 'catalogo';
            }
            $nuevaDirRapidaAct = $incProvAct && !$tieneDirProv && $modoProvAct === 'rapida';
            $_POST['nueva_direccion_rapida'] = $nuevaDirRapidaAct ? '1' : '0';
            if ($nuevaDirRapidaAct) {
                proveedoresMapearDireccionRapida($_POST);
            }
            $rapidaActLista = !$nuevaDirRapidaAct || (
                isset($_POST['id_calle_FK']) && !empty(trim((string) $_POST['id_calle_FK'])) &&
                isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior'])
            );
            $catalogoActOk = !$exigeDirProv || $nuevaDirRapidaAct ||
                (isset($_POST['id_direccion_FK']) && (int) $_POST['id_direccion_FK'] > 0);
            $dirProvActOk = !$exigeDirProv || ($nuevaDirRapidaAct ? $rapidaActLista : $catalogoActOk);
            if (isset($_POST['razon_social']) && trim((string) $_POST['razon_social']) !== '' && $dirProvActOk) {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Proveedor actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                    $proveedores = $app->leer($busqueda);
                    $contactosPorProveedor = proveedoresConstruirMapaContactos($app, $proveedores);
                    require_once (__DIR__ . "/views/proveedores/index.php");
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el proveedor: ' . $e->getMessage();
                    $proveedor = $app->leerUno($id);
                    require_once (__DIR__ . "/views/proveedores/formulario.php");
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Si el proveedor ya tiene direccion o eliges registrarla, selecciona una valida.';
                }
                $proveedor = $app->leerUno($id);
                require_once (__DIR__ . "/views/proveedores/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    if ($cantidad) {
                        $mensaje = 'Proveedor dado de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja el proveedor';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el proveedor: ' . $e->getMessage();
                }
            }
            $proveedores = $app->leer($busqueda);
            $contactosPorProveedor = proveedoresConstruirMapaContactos($app, $proveedores);
            require_once (__DIR__ . "/views/proveedores/index.php");
            break;

        case 'leer':
        default:
            $proveedores = $app->leer($busqueda);
            $contactosPorProveedor = proveedoresConstruirMapaContactos($app, $proveedores);
            require_once (__DIR__ . "/views/proveedores/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
