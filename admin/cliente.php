<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/cliente.php");
require_once __DIR__ . '/includes/list_search.php';
require_once __DIR__ . '/includes/cliente_correo.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Cliente();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

$cliente = null;
if (($accion === 'actualizar' || $accion === 'ver') && $id) {
    $cliente = $app->leerUno($id);
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Clientes</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            $quiereDir = isset($_POST['incluir_direccion']) && (string) $_POST['incluir_direccion'] === '1';
            $dirLista = !$quiereDir || (
                isset($_POST['id_calle_FK']) && !empty(trim((string) $_POST['id_calle_FK'])) &&
                isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior'])
            );
            if (isset($_POST['nombre']) && !empty(trim($_POST['nombre'])) &&
                isset($_POST['primer_apellido']) && !empty(trim($_POST['primer_apellido'])) &&
                isset($_POST['telefono']) && !empty(trim($_POST['telefono'])) &&
                $dirLista) {

                $data = $_POST;

                try {
                    $idGenerado = $app->crear($data);
                    if ($idGenerado > 0) {
                        $mensaje = 'Cliente creado correctamente.';
                        $clienteNuevo = $app->leerUno($idGenerado);
                        $contrasenaPlano = $app->ultimaContrasenaPlanoAlta() ?? '';
                        if (is_array($clienteNuevo) && $contrasenaPlano !== '') {
                            $resultMail = joyeria_cliente_enviar_credenciales_mail(
                                joyeria_cliente_datos_para_correo($clienteNuevo),
                                $contrasenaPlano,
                                false
                            );
                            if (!empty($resultMail['success']) && empty($resultMail['skipped'])) {
                                $mensaje .= ' Correo de credenciales enviado a '
                                    . htmlspecialchars((string) ($resultMail['correo'] ?? $clienteNuevo['correo']));
                            } elseif (empty($resultMail['skipped']) && empty($resultMail['success'])) {
                                $mensaje .= ' Advertencia: no se pudo enviar el correo de credenciales ('
                                    . htmlspecialchars((string) ($resultMail['message'] ?? 'error desconocido')) . ')';
                            }
                        }
                        $clientes = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/cliente/index.php");
                    } else {
                        $mensaje = 'No se pudo crear el cliente.';
                        require_once (__DIR__ . "/views/cliente/formulario.php");
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el cliente: ' . $e->getMessage();
                    require_once (__DIR__ . "/views/cliente/formulario.php");
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios para crear el cliente.';
                }
                require_once (__DIR__ . "/views/cliente/formulario.php");
            }
            break;

        case 'actualizar':
            $clienteParaDir = ($id && !empty($_POST)) ? $app->leerUno($id) : null;
            $tieneDir = $clienteParaDir && !empty($clienteParaDir['id_direccion_FK']);
            $quiereDirActual = isset($_POST['incluir_direccion']) && (string) $_POST['incluir_direccion'] === '1';
            $exigeDir = $tieneDir || $quiereDirActual;
            $dirListaActual = !$exigeDir || (
                isset($_POST['id_calle_FK']) && !empty(trim((string) $_POST['id_calle_FK'])) &&
                isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior'])
            );
            if ($id &&
                isset($_POST['nombre']) && !empty(trim($_POST['nombre'])) &&
                isset($_POST['primer_apellido']) && !empty(trim($_POST['primer_apellido'])) &&
                isset($_POST['telefono']) && !empty(trim($_POST['telefono'])) &&
                $dirListaActual) {

                $data = $_POST;
                $clienteAntes = $app->leerUno($id);
                if (!$clienteAntes) {
                    $mensaje = 'Cliente no encontrado.';
                    require_once (__DIR__ . "/views/cliente/formulario.php");
                    break;
                }

                $contrasenaCapturada = isset($data['contrasena']) && trim((string) $data['contrasena']) !== '';
                $contrasenaPlano = joyeria_cliente_resolver_contrasena_para_correo($clienteAntes, $data);
                $contrasenaGenerada = $contrasenaPlano !== null
                    && !$contrasenaCapturada
                    && joyeria_cliente_correo_cambio($clienteAntes, $data);

                try {
                    $app->actualizar($id, $data);
                    $mensaje = 'Cliente actualizado correctamente';
                    if ($contrasenaPlano !== null) {
                        $clienteDespues = $app->leerUno($id);
                        $datosCorreo = is_array($clienteDespues)
                            ? joyeria_cliente_datos_para_correo($clienteDespues)
                            : joyeria_cliente_datos_para_correo($data);
                        $resultMail = joyeria_cliente_enviar_credenciales_mail(
                            $datosCorreo,
                            $contrasenaPlano,
                            true
                        );
                        if (!empty($resultMail['skipped'])) {
                            // Sin correo entregable; no advertir SMTP.
                        } elseif (!empty($resultMail['success'])) {
                            $mensaje .= '. Correo de acceso enviado a '
                                . htmlspecialchars((string) ($resultMail['correo'] ?? $datosCorreo['correo']));
                            if ($contrasenaGenerada) {
                                $mensaje .= ' (se generó una contraseña temporal por el cambio de correo)';
                            }
                        } else {
                            $mensaje .= '. Advertencia: no se pudo enviar el correo de acceso ('
                                . htmlspecialchars((string) ($resultMail['message'] ?? 'error desconocido')) . ')';
                        }
                    }
                    $clientes = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/cliente/index.php");
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el cliente: ' . $e->getMessage();
                    $cliente = $app->leerUno($id);
                    require_once (__DIR__ . "/views/cliente/formulario.php");
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios para actualizar el cliente.';
                }
                $cliente = $app->leerUno($id);
                require_once (__DIR__ . "/views/cliente/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $id_usuario_actual = $_SESSION['id_usuario'] ?? null;
                    $cantidad = $app->borrar($id, $id_usuario_actual);
                    if ($cantidad) {
                        $mensaje = 'Cliente dado de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja el cliente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el cliente: ' . $e->getMessage();
                }
            }
            $clientes = $app->leer($busqueda);
            require_once (__DIR__ . "/views/cliente/index.php");
            break;

        case 'leer':
        default:
            $clientes = $app->leer($busqueda);
            require_once (__DIR__ . "/views/cliente/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
