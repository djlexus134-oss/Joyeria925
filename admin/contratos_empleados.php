<?php
require_once(__DIR__ . "/../sistema.class.php");
require_once(__DIR__ . "/models/ContratoEmpleado.php");
require_once __DIR__ . '/models/empleado.php';
require_once(__DIR__ . "/includes/MailService.php");
require_once(__DIR__ . "/includes/PDFGenerator.php");
require_once(__DIR__ . "/includes/auth.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new ContratoEmpleado();
$empleadoApp = new Empleado();

$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$idEmpleado = (isset($_GET['id_empleado'])) ? intval($_GET['id_empleado']) : null;
$mensaje = null;
$tipo_mensaje = 'info'; // 'success', 'error', 'info'

// Recuperar mensajes de sesión si existen (después de redirección)
if (isset($_SESSION['mensaje_temporal'])) {
    $mensaje = $_SESSION['mensaje_temporal'];
    $tipo_mensaje = isset($_SESSION['tipo_mensaje_temporal']) ? $_SESSION['tipo_mensaje_temporal'] : 'info';
    unset($_SESSION['mensaje_temporal']);
    unset($_SESSION['tipo_mensaje_temporal']);
}

// Obtener lista de empleados activos para el formulario
$empleados = [];
$contratos = [];  // ← AGREGADO: Inicializar arreglo de contratos
try {
    $empleados = $empleadoApp->leer();
} catch (Exception $e) {
    error_log("Error cargando empleados: " . $e->getMessage());
}

// CARGAR CONTRATOS INICIALMENTE ← AGREGADO: Cargar contratos para la vista
try {
    if ($accion === 'listar' || !$accion) {
        $contratos = $app->leer($busqueda);
    } elseif ($accion === 'ver' && $id) {
        $contrato = $app->leerUno($id);
        if ($contrato) {
            $contratos = [$contrato];
        }
    }
} catch (Exception $e) {
    error_log("Error cargando contratos: " . $e->getMessage());
    $mensaje = "Error al cargar contratos: " . $e->getMessage();
    $tipo_mensaje = 'error';
}

// Manejar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accionPost = (isset($_POST['accion'])) ? htmlspecialchars($_POST['accion']) : null;
    
    // CREAR NUEVO CONTRATO
    if ($accionPost === 'crear') {
        $idEmpleadoF = (isset($_POST['id_empleado_FK'])) ? intval($_POST['id_empleado_FK']) : null;
        $tipoContrato = (isset($_POST['tipo_contrato'])) ? htmlspecialchars($_POST['tipo_contrato']) : null;
        $fechaInicio = (isset($_POST['fecha_inicio'])) ? htmlspecialchars($_POST['fecha_inicio']) : null;
        $fechaFin = (isset($_POST['fecha_fin'])) ? htmlspecialchars($_POST['fecha_fin']) : null;
        $observaciones = (isset($_POST['observaciones'])) ? htmlspecialchars($_POST['observaciones']) : null;
        
        if (!$idEmpleadoF || !$tipoContrato || !$fechaInicio) {
            $mensaje = "Faltan datos requeridos";
            $tipo_mensaje = 'error';
        } else {
            $resultadoContrato = $app->crear([
                'id_empleado_FK' => $idEmpleadoF,
                'tipo_contrato' => $tipoContrato,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'observaciones' => $observaciones
            ]);
            
            if ($resultadoContrato['success']) {
                $newId = $resultadoContrato['id'];
                $mensaje = "Contrato creado exitosamente (ID: $newId). Generando PDF...";
                $tipo_mensaje = 'success';
                $pdfGenerado = false;
                
                // Obtener datos del contrato y empleado para generar PDF
                $contratoNuevo = $app->leerUno($newId);
                $empleadoData = $empleadoApp->leerUno($idEmpleadoF);
                
                error_log("=== CREAR CONTRATO ===");
                error_log("ID Contrato: $newId");
                error_log("ID Empleado: $idEmpleadoF");
                error_log("Contrato Data: " . json_encode($contratoNuevo));
                error_log("Empleado Data: " . json_encode($empleadoData));
                
                if ($contratoNuevo && $empleadoData) {
                    try {
                        $pdfGen = new PDFGenerator();
                        $resultPDF = $pdfGen->generarContratoEmpleado($empleadoData, $contratoNuevo);
                        
                        error_log("PDF Result: " . json_encode($resultPDF));
                        
                        if ($resultPDF['success']) {
                            // Actualizar ruta del PDF en BD
                            $updateResult = $app->actualizarRutaPDF($newId, $resultPDF['url']);
                            error_log("Update PDF Result: " . json_encode($updateResult));
                            
                            $mensaje = "✓ Contrato y PDF generados exitosamente";
                            $tipo_mensaje = 'success';
                            $pdfGenerado = true;
                        } else {
                            $mensaje = "⚠ Contrato creado PERO fallo en PDF: " . $resultPDF['message'];
                            $tipo_mensaje = 'warning';
                            error_log("ERROR en generación de PDF: " . $resultPDF['message']);
                        }
                    } catch (Exception $e) {
                        $mensaje = "⚠ Contrato creado PERO error en PDF: " . $e->getMessage();
                        $tipo_mensaje = 'warning';
                        error_log("EXCEPCIÓN en PDF: " . $e->getMessage());
                    }
                } else {
                    $mensaje = "⚠ Contrato creado PERO no se encontraron datos para generar PDF";
                    $tipo_mensaje = 'warning';
                    error_log("Datos incompletos - Contrato: " . ($contratoNuevo ? 'OK' : 'FALTA') . ", Empleado: " . ($empleadoData ? 'OK' : 'FALTA'));
                }
                
                // Solo redirigir pero mantener el mensaje en sesión
                $_SESSION['mensaje_temporal'] = $mensaje;
                $_SESSION['tipo_mensaje_temporal'] = $tipo_mensaje;
                header("Location: contratos_empleados.php?accion=listar");
                exit;
            } else {
                $mensaje = "Error: " . $resultadoContrato['message'];
                $tipo_mensaje = 'error';
            }
        }
    }
    
    // ACTUALIZAR CONTRATO
    elseif ($accionPost === 'actualizar') {
        $idContratoUpd = (isset($_POST['id_contrato'])) ? intval($_POST['id_contrato']) : null;
        
        if (!$idContratoUpd) {
            $mensaje = "ID de contrato no válido";
            $tipo_mensaje = 'error';
        } else {
            $datoActualizar = [];
            if (!empty($_POST['tipo_contrato'])) $datoActualizar['tipo_contrato'] = htmlspecialchars($_POST['tipo_contrato']);
            if (!empty($_POST['fecha_inicio'])) $datoActualizar['fecha_inicio'] = htmlspecialchars($_POST['fecha_inicio']);
            if (!empty($_POST['fecha_fin'])) $datoActualizar['fecha_fin'] = htmlspecialchars($_POST['fecha_fin']);
            if (isset($_POST['observaciones'])) $datoActualizar['observaciones'] = htmlspecialchars($_POST['observaciones']);
            
            if (empty($datoActualizar)) {
                $mensaje = "No hay datos para actualizar";
                $tipo_mensaje = 'error';
            } else {
                $resultActualizar = $app->actualizar($idContratoUpd, $datoActualizar);
                if ($resultActualizar['success']) {
                    $mensaje = "Contrato actualizado exitosamente";
                    $tipo_mensaje = 'success';
                    header("Location: contratos_empleados.php?accion=listar");
                    exit;
                } else {
                    $mensaje = "Error: " . $resultActualizar['message'];
                    $tipo_mensaje = 'error';
                }
            }
        }
    }
    
    // ELIMINAR CONTRATO (SOFT DELETE)
    elseif ($accionPost === 'eliminar') {
        $idContratoElim = (isset($_POST['id_contrato'])) ? intval($_POST['id_contrato']) : null;
        $usuarioActual = auth_user();
        
        if (!$idContratoElim || !$usuarioActual) {
            $mensaje = "No se puede realizar la eliminación";
            $tipo_mensaje = 'error';
        } else {
            $resultElim = $app->eliminar($idContratoElim, $usuarioActual['id_usuario']);
            if ($resultElim['success']) {
                $mensaje = "Contrato eliminado (marcado como inactivo)";
                $tipo_mensaje = 'success';
                header("Location: contratos_empleados.php?accion=listar");
                exit;
            } else {
                $mensaje = "Error: " . $resultElim['message'];
                $tipo_mensaje = 'error';
            }
        }
    }
    
    // REGENERAR PDF
    elseif ($accionPost === 'regenerar_pdf') {
        $idContratoRegen = (isset($_POST['id_contrato'])) ? intval($_POST['id_contrato']) : null;
        
        if (!$idContratoRegen) {
            $mensaje = "ID de contrato no válido";
            $tipo_mensaje = 'error';
        } else {
            $contratoData = $app->leerUno($idContratoRegen);
            if (!$contratoData) {
                $mensaje = "Contrato no encontrado";
                $tipo_mensaje = 'error';
            } else {
                $empleadoData = $empleadoApp->leerUno($contratoData['id_empleado_FK']);
                if (!$empleadoData) {
                    $mensaje = "Datos del empleado no encontrados";
                    $tipo_mensaje = 'error';
                } else {
                    try {
                        $pdfGen = new PDFGenerator();
                        $resultPDF = $pdfGen->generarContratoEmpleado($empleadoData, $contratoData);
                        
                        if ($resultPDF['success']) {
                            $app->actualizarRutaPDF($idContratoRegen, $resultPDF['url']);
                            $mensaje = "PDF regenerado exitosamente";
                            $tipo_mensaje = 'success';
                        } else {
                            $mensaje = "Error: " . $resultPDF['message'];
                            $tipo_mensaje = 'error';
                        }
                    } catch (Exception $e) {
                        $mensaje = "Error: " . $e->getMessage();
                        $tipo_mensaje = 'error';
                    }
                }
            }
        }
    }
}

// Lógica de visualización según acción
$contratos = [];
$contratoActual = null;

if ($accion === 'listar' || !$accion) {
    // Listar todos los contratos
    $contratos = $app->leer($busqueda);
} elseif ($accion === 'crear') {
    // Mostrar formulario de creación
} elseif ($accion === 'actualizar' && $id) {
    // Mostrar formulario de actualización
    $contratoActual = $app->leerUno($id);
    if (!$contratoActual) {
        $mensaje = "Contrato no encontrado";
        $tipo_mensaje = 'error';
        $accion = 'listar';
        $contratos = $app->leer($busqueda);
    }
} elseif ($accion === 'ver' && $id) {
    // Ver detalles del contrato
    $contratoActual = $app->leerUno($id);
    if (!$contratoActual) {
        $mensaje = "Contrato no encontrado";
        $tipo_mensaje = 'error';
        $accion = 'listar';
        $contratos = $app->leer($busqueda);
    }
} elseif ($accion === 'empleado' && $idEmpleado) {
    // Ver contratos de un empleado específico
    $contratoActual = $app->leerPorEmpleado($idEmpleado);
    if ($contratoActual === null) {
        $mensaje = "No hay contratos para este empleado";
        $tipo_mensaje = 'info';
        $accion = 'listar';
        $contratos = $app->leer($busqueda);
    }
}

// Incluir vista
require_once(__DIR__ . "/views/header.php");
require_once(__DIR__ . "/views/contratos_empleados/index.php");
require_once(__DIR__ . "/views/footer.php");
