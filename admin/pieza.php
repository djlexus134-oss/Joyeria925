<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/pieza.php");
require_once (__DIR__ . "/models/configuracion_general.php");
require_once (__DIR__ . "/models/variantes.php");
require_once __DIR__ . '/includes/list_search.php';
require_once __DIR__ . '/includes/ImpresionEtiquetaHelper.php';
require_once __DIR__ . '/includes/upload_helpers.php';
require_once __DIR__ . '/includes/pieza_view_helpers.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');
$camposBusquedaPiezaPermitidos = ['global', 'id', 'descripcion', 'subfamilia', 'metal', 'proveedor', 'tienda'];
$campoBusquedaPieza = isset($_GET['campo']) ? joyeria_list_search_normalize((string) $_GET['campo']) : 'global';
if (!in_array($campoBusquedaPieza, $camposBusquedaPiezaPermitidos, true)) {
    $campoBusquedaPieza = 'global';
}

$app = new Pieza();
$appConfig = new ConfiguracionGeneral();
$stockIdsNuevos = [];
$idColaEtiquetasNueva = null;
$idPiezaRecienCreada = null;
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;
$catalogos = $app->obtenerCatalogos();
$configGlobal = $appConfig->leerPorClaves([
    'id_tienda_default',
    'markup_pct_default',
    'tipo_codigo_barras_default',
]);
$idTiendaDefault = isset($configGlobal['id_tienda_default']) ? (int) $configGlobal['id_tienda_default'] : null;
$markupPctDefault = isset($configGlobal['markup_pct_default']) ? (string) $configGlobal['markup_pct_default'] : '';
$tipoCodigoBarrasDefault = isset($configGlobal['tipo_codigo_barras_default']) ? (string) $configGlobal['tipo_codigo_barras_default'] : 'CODE128';

$catalogoVariantes = ['tipos' => []];
$variantesModel = new Variantes();
if ($variantesModel->tieneTablas()) {
    $catalogoVariantes = $variantesModel->listarCatalogoParaSelect();
}

$esAccionStock = ($accion === 'stock' || ($accion !== null && str_starts_with($accion, 'stock_')));
$tituloModulo = $esAccionStock ? 'Stock de piezas' : 'Gestion de Piezas';

$idPiezaFiltroStock = null;
if ($esAccionStock && isset($_GET['id_pieza']) && (int) $_GET['id_pieza'] > 0) {
    $idPiezaFiltroStock = (int) $_GET['id_pieza'];
}

// POST de stock debe redirigir antes de header.php (evita "headers already sent").
if ($esAccionStock && strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && in_array($accion, ['stock_crear', 'stock_actualizar'], true)) {
    require_once __DIR__ . '/includes/auth.php';
    $guardStockPost = auth_current_access_guard();
    if (!$guardStockPost['allowed']) {
        auth_set_flash((string) $guardStockPost['message'], 'error');
        if (!empty($guardStockPost['redirect'])) {
            header('Location: ' . $guardStockPost['redirect']);
            exit;
        }
        http_response_code(403);
        echo 'Acceso denegado.';
        exit;
    }
    $permisoStockPost = $accion === 'stock_crear' ? 'CREAR' : 'ACTUALIZAR';
    if (!auth_can_module_action('piezas_stock', $permisoStockPost)) {
        auth_set_flash('No tienes permiso para esta accion en stock de piezas.', 'error');
        header('Location: pieza.php?accion=stock' . ($idPiezaFiltroStock !== null ? '&id_pieza=' . $idPiezaFiltroStock : ''));
        exit;
    }
    require_once (__DIR__ . '/models/piezas_stock.php');
    require_once (__DIR__ . '/includes/piezas_stock_controller.php');
    $tipoCodigoStockPost = in_array($tipoCodigoBarrasDefault, ['EAN13', 'CODE128', 'QR'], true)
        ? $tipoCodigoBarrasDefault
        : 'CODE128';
    $appStockPost = new PiezasStock();
    joyeria_piezas_stock_handle_post_before_output(
        $appStockPost,
        $id,
        $accion,
        $idPiezaFiltroStock,
        $tipoCodigoStockPost
    );
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2><?php echo htmlspecialchars($tituloModulo, ENT_QUOTES, 'UTF-8'); ?></h2>
</header>

<div class="admin-main">
    <?php
    if ($esAccionStock) {
        require_once (__DIR__ . '/models/piezas_stock.php');
        require_once (__DIR__ . '/includes/piezas_stock_controller.php');

        $appStock = new PiezasStock();
        $idPiezaFiltro = (isset($_GET['id_pieza']) && (int) $_GET['id_pieza'] > 0) ? (int) $_GET['id_pieza'] : null;
        if (!in_array($tipoCodigoBarrasDefault, ['EAN13', 'CODE128', 'QR'], true)) {
            $tipoCodigoBarrasDefault = 'CODE128';
        }
        $precioVentaHermano = $idPiezaFiltro !== null ? $appStock->obtenerPrecioVentaHermano($idPiezaFiltro) : null;
        $catalogosStock = [];
        try {
            $catalogosStock = $appStock->obtenerCatalogos();
            if ($variantesModel->tieneTablas()) {
                $catalogosStock['variantes'] = $catalogoVariantes['tipos'];
            }
        } catch (Exception $e) {
            $mensaje = 'No se pudieron cargar los catalogos: ' . $e->getMessage();
        }

        joyeria_piezas_stock_dispatch(
            $appStock,
            $id,
            $accion,
            $idPiezaFiltro,
            $busqueda,
            $tipoCodigoBarrasDefault,
            $precioVentaHermano,
            $catalogosStock,
            $mensaje
        );
    } else {
    switch ($accion) {
        case 'crear':
            $esPost = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
            $datosEntrada = $_POST;
            if ($esPost) {
                if (!isset($datosEntrada['id_tienda_FK']) || trim((string) $datosEntrada['id_tienda_FK']) === '') {
                    if ($idTiendaDefault !== null && $idTiendaDefault > 0) {
                        $datosEntrada['id_tienda_FK'] = (string) $idTiendaDefault;
                    }
                }
                if (!isset($datosEntrada['aumento_pct']) || trim((string) $datosEntrada['aumento_pct']) === '') {
                    if ($markupPctDefault !== '') {
                        $datosEntrada['aumento_pct'] = $markupPctDefault;
                    }
                }
            }

            $costoDesdeGrilla = isset($_POST['costo_desde_grilla']) && trim((string) $_POST['costo_desde_grilla']) === '1';
            if ($costoDesdeGrilla) {
                $datosEntrada['costo_desde_grilla'] = '1';
            }

            $metodoCosto = isset($datosEntrada['metodo_costo']) ? trim((string) $datosEntrada['metodo_costo']) : 'directo';
            $costoOk = $costoDesdeGrilla
                || ($metodoCosto === 'por_gramo'
                    ? (isset($datosEntrada['peso_gr']) && trim((string) $datosEntrada['peso_gr']) !== ''
                       && isset($datosEntrada['precio_por_gramo']) && trim((string) $datosEntrada['precio_por_gramo']) !== '')
                    : (isset($datosEntrada['costo']) && trim((string) $datosEntrada['costo']) !== ''));

            if ($esPost &&
                isset($datosEntrada['desc_pieza']) && !empty(trim((string) $datosEntrada['desc_pieza'])) &&
                isset($datosEntrada['id_sub_familia_FK']) && !empty(trim((string) $datosEntrada['id_sub_familia_FK'])) &&
                isset($datosEntrada['id_metal_FK']) && !empty(trim((string) $datosEntrada['id_metal_FK'])) &&
                isset($datosEntrada['id_tienda_FK']) && !empty(trim((string) $datosEntrada['id_tienda_FK'])) &&
                $costoOk) {

                try {
                    $erroresSubida = joyeria_resumen_errores_imagenes_subida();
                    if ($erroresSubida !== []) {
                        throw new RuntimeException(implode(' ', $erroresSubida));
                    }
                    $archivoImagen = joyeria_extraer_archivo_principal_listo();

                    $opcionesStock = null;
                    if (($_POST['accion_guardar'] ?? '') === 'guardar_y_stock') {
                        $cantidadStock = isset($_POST['stock_cantidad']) ? (int) $_POST['stock_cantidad'] : 0;

                        $varianteModo = isset($_POST['stock_variante_modo']) ? trim((string) $_POST['stock_variante_modo']) : 'ninguna';
                        $eje1TipoId = isset($_POST['stock_eje1_tipo_id']) ? (int) $_POST['stock_eje1_tipo_id'] : 0;
                        $eje2TipoId = isset($_POST['stock_eje2_tipo_id']) ? (int) $_POST['stock_eje2_tipo_id'] : 0;
                        $matriz = [];

                        if ($varianteModo === 'ejes') {
                            $matrizRaw = isset($_POST['stock_matriz']) ? (string) $_POST['stock_matriz'] : '';
                            $decodedMatriz = json_decode($matrizRaw, true);
                            if (is_array($decodedMatriz)) {
                                $totalVariantes = 0;
                                foreach ($decodedMatriz as $item) {
                                    if (!is_array($item)) {
                                        continue;
                                    }
                                    $valor1Id = isset($item['valor1_id']) ? (int) $item['valor1_id'] : 0;
                                    $valor2Id = isset($item['valor2_id']) ? (int) $item['valor2_id'] : 0;
                                    $cant = isset($item['cantidad']) ? (int) $item['cantidad'] : 0;
                                    $precioCelda = isset($item['precio']) ? (float) $item['precio'] : 0.0;
                                    $metodoCelda = isset($item['metodo_celda']) ? trim((string) $item['metodo_celda']) : 'directo';
                                    $pesoGrCelda = isset($item['peso_gr']) ? (float) $item['peso_gr'] : null;
                                    $ppgCelda = isset($item['precio_por_gramo']) ? (float) $item['precio_por_gramo'] : null;
                                    if ($valor1Id <= 0 || $cant < 1) {
                                        continue;
                                    }
                                    $matriz[] = [
                                        'valor1_id' => $valor1Id,
                                        'valor2_id' => $valor2Id > 0 ? $valor2Id : null,
                                        'cantidad' => $cant,
                                        'precio' => $precioCelda > 0 ? $precioCelda : null,
                                        'metodo_celda' => $metodoCelda,
                                        'peso_gr' => $pesoGrCelda,
                                        'precio_por_gramo' => $ppgCelda,
                                        'precio_es_final' => !empty($item['precio_es_final']),
                                    ];
                                    $totalVariantes += $cant;
                                }
                                if ($matriz !== []) {
                                    $cantidadStock = $totalVariantes;
                                }
                            }
                            if ($matriz === [] || $eje1TipoId <= 0) {
                                $varianteModo = 'ninguna';
                            }
                        } else {
                            $varianteModo = 'ninguna';
                        }

                        if ($cantidadStock > 0) {
                            $opcionesStock = [
                                'cantidad' => $cantidadStock,
                                'tipo_codigo' => $_POST['stock_tipo_codigo'] ?? $tipoCodigoBarrasDefault,
                                'variante_modo' => $varianteModo,
                                'eje1_tipo_id' => $eje1TipoId > 0 ? $eje1TipoId : null,
                                'eje2_tipo_id' => $eje2TipoId > 0 ? $eje2TipoId : null,
                                'matriz' => $matriz,
                            ];
                        }
                    }

                    $idGenerado = $app->crear($datosEntrada, $archivoImagen, $opcionesStock);
                    if ($idGenerado > 0 && isset($_FILES['imagenes_adicionales'])) {
                        $app->agregarImagenes($idGenerado, $_FILES['imagenes_adicionales']);
                    }
                    if ($idGenerado > 0) {
                        $mensaje = 'Pieza creada correctamente';
                        $stockIdsNuevos = $app->ultimosStockIdsCreados;
                        $idColaEtiquetasNueva = null;
                        if ($opcionesStock !== null) {
                            $idPiezaRecienCreada = $idGenerado;
                            $mensaje .= ' y se generaron ' . (int) $opcionesStock['cantidad'] . ' unidad(es) de stock.';
                            if (!empty($_POST['stock_encolar_etiquetas']) && $stockIdsNuevos !== []) {
                                $idTiendaEncolar = isset($datosEntrada['id_tienda_FK']) ? (int) $datosEntrada['id_tienda_FK'] : null;
                                try {
                                    $idColaEtiquetasNueva = joyeria_encolar_etiquetas_stock($stockIdsNuevos, $idTiendaEncolar);
                                    $mensaje .= ' Etiquetas encoladas para impresion.';
                                } catch (Throwable $e) {
                                    error_log('encolar etiquetas post-stock: ' . $e->getMessage());
                                }
                            }
                        } else {
                            $stockIdsNuevos = [];
                        }
                        $piezas = $app->leer($busqueda, $campoBusquedaPieza);
                        require_once (__DIR__ . "/views/pieza/index.php");
                    } else {
                        $mensaje = 'No se pudo crear la pieza.';
                        $imagenesPieza = [];
                        require_once (__DIR__ . "/views/pieza/formulario.php");
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear la pieza: ' . $e->getMessage();
                    $imagenesPieza = [];
                    require_once (__DIR__ . "/views/pieza/formulario.php");
                }
            } else {
                if ($esPost && !empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios para crear la pieza.';
                }
                $imagenesPieza = [];
                require_once (__DIR__ . "/views/pieza/formulario.php");
            }
            break;

        case 'actualizar':
            $metodoCostoUpd = isset($_POST['metodo_costo']) ? trim((string) $_POST['metodo_costo']) : 'directo';
            $costoOkUpd = ($metodoCostoUpd === 'por_gramo')
                ? (isset($_POST['peso_gr']) && trim((string) $_POST['peso_gr']) !== ''
                   && isset($_POST['precio_por_gramo']) && trim((string) $_POST['precio_por_gramo']) !== '')
                : (isset($_POST['costo']) && trim((string) $_POST['costo']) !== '');

            if ($id &&
                isset($_POST['desc_pieza']) && !empty(trim($_POST['desc_pieza'])) &&
                isset($_POST['id_sub_familia_FK']) && !empty(trim($_POST['id_sub_familia_FK'])) &&
                isset($_POST['id_metal_FK']) && !empty(trim($_POST['id_metal_FK'])) &&
                isset($_POST['id_tienda_FK']) && !empty(trim($_POST['id_tienda_FK'])) &&
                $costoOkUpd) {

                try {
                    $erroresSubida = joyeria_resumen_errores_imagenes_subida();
                    if ($erroresSubida !== []) {
                        throw new RuntimeException(implode(' ', $erroresSubida));
                    }
                    $archivoImagen = joyeria_extraer_archivo_principal_listo();
                    $actualizado = $app->actualizar($id, $_POST, $archivoImagen);
                    $imagenesAgregadas = 0;
                    if (isset($_FILES['imagenes_adicionales'])) {
                        $imagenesAgregadas = $app->agregarImagenes($id, $_FILES['imagenes_adicionales']);
                    }
                    if ($actualizado) {
                        $mensaje = 'Pieza actualizada correctamente';
                    } elseif ($imagenesAgregadas > 0) {
                        $mensaje = 'Se agregaron ' . $imagenesAgregadas . ' imagen(es) a la pieza.';
                    } else {
                        $mensaje = 'No se realizaron cambios en la pieza';
                    }
                    $piezas = $app->leer($busqueda, $campoBusquedaPieza);
                    require_once (__DIR__ . "/views/pieza/index.php");
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la pieza: ' . $e->getMessage();
                    $pieza = $app->leerUno($id);
                    $imagenesPieza = $app->leerImagenes($id);
                    require_once (__DIR__ . "/views/pieza/formulario.php");
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios para actualizar la pieza.';
                }
                $pieza = $app->leerUno($id);
                $imagenesPieza = $app->leerImagenes($id);
                require_once (__DIR__ . "/views/pieza/formulario.php");
            }
            break;

        case 'establecer_principal_imagen':
            if ($id && isset($_GET['id_imagen']) && is_numeric($_GET['id_imagen'])) {
                try {
                    $app->establecerImagenPrincipal($id, (int) $_GET['id_imagen']);
                    $mensaje = 'Imagen principal actualizada correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al establecer imagen principal: ' . $e->getMessage();
                }
            }
            joyeria_pieza_cargar_vista_tras_galeria($app, $id, $mensaje);
            break;

        case 'eliminar_imagen':
            if ($id && isset($_GET['id_imagen']) && is_numeric($_GET['id_imagen'])) {
                try {
                    $app->eliminarImagen($id, (int) $_GET['id_imagen']);
                    $mensaje = 'Imagen eliminada correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar imagen: ' . $e->getMessage();
                }
            }
            joyeria_pieza_cargar_vista_tras_galeria($app, $id, $mensaje);
            break;

        case 'gestionar_foto':
            if (!$id) {
                $piezas = $app->leer($busqueda, $campoBusquedaPieza);
                require_once (__DIR__ . "/views/pieza/index.php");
                break;
            }
            $pieza = $app->leerUno($id);
            $imagenesPieza = $app->leerImagenes($id);
            require_once (__DIR__ . "/views/pieza/foto.php");
            break;

        case 'subir_foto':
            if ($id) {
                try {
                    $erroresSubida = joyeria_resumen_errores_imagenes_subida();
                    if ($erroresSubida !== []) {
                        throw new RuntimeException(implode(' ', $erroresSubida));
                    }

                    $archivoImagen = joyeria_extraer_archivo_principal_listo();
                    $reemplazoOk = false;
                    if ($archivoImagen !== null) {
                        $reemplazoOk = $app->reemplazarImagenPrincipal($id, $archivoImagen);
                    }
                    $agregadas = 0;
                    if (isset($_FILES['imagenes_adicionales'])) {
                        $agregadas = $app->agregarImagenes($id, $_FILES['imagenes_adicionales']);
                    }
                    if ($reemplazoOk && $agregadas > 0) {
                        $mensaje = 'Imagen principal reemplazada y ' . $agregadas . ' imagen(es) agregada(s).';
                    } elseif ($reemplazoOk) {
                        $mensaje = 'Imagen principal reemplazada correctamente.';
                    } elseif ($agregadas > 0) {
                        $mensaje = $agregadas . ' imagen(es) agregada(s) a la galeria.';
                    } else {
                        $msgPrincipal = joyeria_mensaje_error_archivo_subida(
                            $_FILES['imagen_principal'] ?? null,
                            'imagen principal'
                        );
                        if ($msgPrincipal !== null) {
                            $mensaje = $msgPrincipal;
                        } else {
                            $mensaje = 'No se recibio ninguna imagen para procesar. Toma una foto o selecciona un archivo antes de guardar.';
                        }
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la foto: ' . $e->getMessage();
                }
            }
            $pieza = $app->leerUno($id);
            $imagenesPieza = $app->leerImagenes($id);
            require_once (__DIR__ . "/views/pieza/foto.php");
            break;

        case 'borrar':
            if ($id) {
                try {
                    $id_usuario_actual = $_SESSION['id_usuario'] ?? null;
                    $cantidad = $app->borrar($id, $id_usuario_actual);
                    if ($cantidad) {
                        $mensaje = 'Pieza dada de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja la pieza';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja la pieza: ' . $e->getMessage();
                }
            }
            $piezas = $app->leer($busqueda, $campoBusquedaPieza);
            require_once (__DIR__ . "/views/pieza/index.php");
            break;

        case 'leer':
        default:
            $piezas = $app->leer($busqueda, $campoBusquedaPieza);
            require_once (__DIR__ . "/views/pieza/index.php");
            break;
    }
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
