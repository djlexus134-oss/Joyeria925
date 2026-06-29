<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/empleado.php");

// Cargar modelos auxiliares para obtener puestos y calles
require_once (__DIR__ . "/models/puesto.php");
require_once __DIR__ . '/includes/list_search.php';
require_once __DIR__ . '/includes/MailService.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Empleado();
$puestoApp = new Puesto();

$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

// Obtener datos auxiliares
try {
    $puestos = $puestoApp->leer();
} catch (Exception $e) {
    $puestos = [];
    $mensaje = "Error al cargar los puestos: " . $e->getMessage();
}

$empleado = null;
if (($accion === 'actualizar' || $accion === 'ver') && $id) {
    $empleado = $app->leerUno($id);
}

require_once (__DIR__ . "/views/header.php");
?>

        <header class="admin-header">
            <h2>Gestión de Empleados</h2>
        </header>

        <div class="admin-main">
            <?php
            switch($accion) {
                case 'crear':
                    $quiereDirEmp = isset($_POST['incluir_direccion']) && (string) $_POST['incluir_direccion'] === '1';
                    $dirListaEmp = !$quiereDirEmp || (
                        isset($_POST['id_calle_FK']) && !empty(trim((string) $_POST['id_calle_FK'])) &&
                        isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior'])
                    );
                    $postEmpCrearBasico =
                        isset($_POST['nombre']) && !empty(trim($_POST['nombre'])) &&
                        isset($_POST['primer_apellido']) && !empty(trim($_POST['primer_apellido'])) &&
                        isset($_POST['correo']) && !empty(trim($_POST['correo'])) &&
                        isset($_POST['telefono']) && !empty(trim($_POST['telefono'])) &&
                        isset($_POST['contrasena']) && !empty(trim($_POST['contrasena'])) &&
                        isset($_POST['id_puesto_FK']) && !empty(trim($_POST['id_puesto_FK'])) &&
                        isset($_POST['salario']) && !empty(trim($_POST['salario'])) &&
                        isset($_POST['curp']) && !empty(trim($_POST['curp'])) &&
                        isset($_POST['rfc']) && !empty(trim($_POST['rfc'])) &&
                        $dirListaEmp;

                    if (!$postEmpCrearBasico) {
                        if (!empty($_POST)) {
                            $mensaje = 'Faltan campos obligatorios para crear el empleado. Si eliges registrar direccion, incluye calle valida y numero exterior.';
                        }
                        require_once (__DIR__ . "/views/empleado/formulario.php");
                    } elseif (
                        $quiereDirEmp && (
                            !is_numeric($_POST['num_exterior']) ||
                            (isset($_POST['num_interior']) && trim((string) $_POST['num_interior']) !== '' && !is_numeric($_POST['num_interior']))
                        )
                    ) {
                        if (!is_numeric($_POST['num_exterior'])) {
                            $mensaje = 'El numero exterior debe ser numerico.';
                        } else {
                            $mensaje = 'El numero interior debe ser numerico cuando se capture.';
                        }
                        require_once (__DIR__ . "/views/empleado/formulario.php");
                    } else {
                        $data = $_POST;

                        // Guardar contraseña sin encriptar para envío de correo
                        $contrasenaTemportal = $data['contrasena'];

                        // Encriptar contraseña del usuario
                        $data['contrasena'] = password_hash($data['contrasena'], PASSWORD_BCRYPT);

                        try {
                            $idGenerado = $app->crear($data);
                            if($idGenerado > 0) {
                                $empleadoCreado = $app->leerUno($idGenerado);

                                if ($empleadoCreado) {
                                    $resultMail = MailService::enviarCredencialesEmpleado(
                                        [
                                            'nombre' => $empleadoCreado['nombre'],
                                            'primer_apellido' => $empleadoCreado['primer_apellido'],
                                            'correo' => $empleadoCreado['correo'],
                                            'contrasena_temporal' => $contrasenaTemportal,
                                        ],
                                        MailService::appBaseUrl()
                                    );

                                    if ($resultMail['success']) {
                                        $mensaje = "Empleado creado correctamente. ✓ Correo de credenciales enviado a " . htmlspecialchars($resultMail['correo']);
                                    } else {
                                        $mensaje = "Empleado creado correctamente. ⚠ Advertencia: No se pudo enviar el correo de credenciales (" . $resultMail['message'] . ")";
                                    }
                                } else {
                                    $mensaje = "Empleado creado correctamente. (No se pudo obtener datos para enviar correo)";
                                }

                                $empleados = $app->leer($busqueda);
                                require_once (__DIR__ . "/views/empleado/index.php");
                            } else {
                                $mensaje = "No se pudo crear el empleado. Verifica los datos capturados.";
                                require_once (__DIR__ . "/views/empleado/formulario.php");
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al crear el empleado: ' . $e->getMessage();
                            require_once (__DIR__ . "/views/empleado/formulario.php");
                        }
                    }
                    break;

                case 'actualizar':
                    $empleadoDirPost = ($id && !empty($_POST)) ? $app->leerUno($id) : null;
                    $tieneDirEmp = is_array($empleadoDirPost) && !empty($empleadoDirPost['id_direccion_FK']);
                    $quiereDirAct = isset($_POST['incluir_direccion']) && (string) $_POST['incluir_direccion'] === '1';
                    $exigeDirEmp = $tieneDirEmp || $quiereDirAct;
                    $dirListaAct = !$exigeDirEmp || (
                        isset($_POST['id_calle_FK']) && !empty(trim((string) $_POST['id_calle_FK'])) &&
                        isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior'])
                    );

                    $postEmpActBasico =
                        $id &&
                        isset($_POST['nombre']) && !empty(trim($_POST['nombre'])) &&
                        isset($_POST['primer_apellido']) && !empty(trim($_POST['primer_apellido'])) &&
                        isset($_POST['correo']) && !empty(trim($_POST['correo'])) &&
                        isset($_POST['telefono']) && !empty(trim($_POST['telefono'])) &&
                        isset($_POST['id_puesto_FK']) && !empty(trim($_POST['id_puesto_FK'])) &&
                        isset($_POST['salario']) && !empty(trim($_POST['salario'])) &&
                        isset($_POST['curp']) && !empty(trim($_POST['curp'])) &&
                        isset($_POST['rfc']) && !empty(trim($_POST['rfc'])) &&
                        $dirListaAct;

                    if (!$postEmpActBasico) {
                        if (!empty($_POST)) {
                            $mensaje = 'Faltan campos obligatorios para actualizar el empleado.';
                        }
                        require_once (__DIR__ . "/views/empleado/formulario.php");
                    } elseif (
                        $exigeDirEmp && (
                            !is_numeric($_POST['num_exterior']) ||
                            (isset($_POST['num_interior']) && trim((string) $_POST['num_interior']) !== '' && !is_numeric($_POST['num_interior']))
                        )
                    ) {
                        if (!is_numeric($_POST['num_exterior'])) {
                            $mensaje = 'El numero exterior debe ser numerico.';
                        } else {
                            $mensaje = 'El numero interior debe ser numerico cuando se capture.';
                        }
                        require_once (__DIR__ . "/views/empleado/formulario.php");
                    } else {
                        $data = $_POST;
                        $prevEmp = is_array($empleado) ? $empleado : $app->leerUno($id);
                        $correoAnterior = is_array($prevEmp) ? trim((string) ($prevEmp['correo'] ?? '')) : '';
                        $correoNuevo = trim((string) ($data['correo'] ?? ''));
                        $correoCambio = $correoAnterior !== '' && strcasecmp($correoAnterior, $correoNuevo) !== 0;

                        $contrasenaPlano = null;
                        if (!empty(trim((string) ($data['contrasena'] ?? '')))) {
                            $contrasenaPlano = trim((string) $data['contrasena']);
                            $data['contrasena'] = password_hash($contrasenaPlano, PASSWORD_BCRYPT);
                        } else {
                            $data['contrasena'] = null;
                        }
                        try {
                            $actualizado = $app->actualizar($id, $data);
                            if($actualizado) {
                                $mensaje = "Empleado actualizado correctamente";
                                if ($correoCambio || $contrasenaPlano !== null) {
                                    $empMail = $app->leerUno($id);
                                    if (is_array($empMail)) {
                                        $mailUpd = MailService::enviarAccesoEmpleadoActualizado(
                                            [
                                                'nombre' => $empMail['nombre'],
                                                'primer_apellido' => $empMail['primer_apellido'],
                                                'correo' => $empMail['correo'],
                                            ],
                                            $contrasenaPlano,
                                            $correoCambio,
                                            MailService::appBaseUrl()
                                        );
                                        if ($mailUpd['success']) {
                                            $mensaje .= '. Correo enviado a ' . htmlspecialchars((string) $mailUpd['correo']);
                                        } else {
                                            $mensaje .= '. Advertencia correo: ' . $mailUpd['message'];
                                        }
                                    }
                                }
                                $empleados = $app->leer($busqueda);
                                require_once (__DIR__ . "/views/empleado/index.php");
                            } else {
                                $mensaje = "No se pudo actualizar el empleado.";
                                require_once (__DIR__ . "/views/empleado/formulario.php");
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al actualizar el empleado: ' . $e->getMessage();
                            require_once (__DIR__ . "/views/empleado/formulario.php");
                        }
                    }
                    break;
                
                case 'ver':
                    if($id) {
                        if($empleado) {
                            require_once (__DIR__ . "/views/empleado/detalle.php");
                        } else {
                            $mensaje = "Empleado no encontrado";
                            $empleados = $app->leer($busqueda);
                            require_once (__DIR__ . "/views/empleado/index.php");
                        }
                    } else {
                        $empleados = $app->leer($busqueda);
                        require_once (__DIR__ . "/views/empleado/index.php");
                    }
                    break;
                
                case 'borrar':
                    if($id) {
                        try {
                            $id_usuario_actual = $_SESSION['id_usuario'] ?? null;
                            $cantidad = $app->borrar($id, $id_usuario_actual);
                            if($cantidad) {
                                $mensaje = "Empleado dado de baja correctamente";
                            } else {
                                $mensaje = "No se pudo dar de baja al empleado";
                            }
                        } catch (Exception $e) {
                            $mensaje = 'Error al dar de baja al empleado: ' . $e->getMessage();
                        }
                    }
                    $empleados = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/empleado/index.php");
                    break;
                
                case 'leer':
                default:
                    $empleados = $app->leer($busqueda);
                    require_once (__DIR__ . "/views/empleado/index.php");
                    break;
            }
            ?>
        </div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
