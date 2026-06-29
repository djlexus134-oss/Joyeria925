<?php

declare(strict_types=1);

/**
 * Procesa POST de alta/edicion de stock antes de enviar HTML (header.php).
 * Sin esto el redirect falla porque la salida ya empezo.
 */
function joyeria_piezas_stock_handle_post_before_output(
    PiezasStock $app,
    ?int $id,
    ?string $accion,
    ?int $idPiezaFiltro,
    string $tipoCodigoBarrasDefault
): void {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $accionInterna = match ($accion) {
        'stock_crear' => 'crear',
        'stock_actualizar' => 'actualizar',
        default => null,
    };
    if ($accionInterna === null) {
        return;
    }

    if ($accionInterna === 'crear') {
        if (!isset($_POST['estado']) || trim((string) $_POST['estado']) === '') {
            $_POST['estado'] = 'disponible';
        }
        if (!isset($_POST['tipo_codigo']) || trim((string) $_POST['tipo_codigo']) === '') {
            $_POST['tipo_codigo'] = $tipoCodigoBarrasDefault;
        }
        if (!isset($_POST['id_pieza_FK']) || (int) $_POST['id_pieza_FK'] <= 0
            || !isset($_POST['precio_venta']) || (float) $_POST['precio_venta'] <= 0) {
            return;
        }

        try {
            $idNuevo = $app->crear($_POST);
            if ($idNuevo > 0) {
                $idPieza = (int) $_POST['id_pieza_FK'];
                joyeria_redirect_stock_listado(
                    $idPieza > 0 ? $idPieza : $idPiezaFiltro,
                    'Pieza de stock creada correctamente'
                );
            }
        } catch (Exception $e) {
            $idPieza = (int) $_POST['id_pieza_FK'];
            auth_set_flash('Error al crear la pieza de stock: ' . $e->getMessage(), 'error');
            $url = 'pieza.php?accion=stock_crear';
            if ($idPieza > 0) {
                $url .= '&id_pieza=' . $idPieza;
            }
            header('Location: ' . $url, true, 302);
            exit;
        }

        return;
    }

    if ($id === null || $id <= 0) {
        return;
    }
    if (!isset($_POST['id_pieza_FK']) || (int) $_POST['id_pieza_FK'] <= 0
        || !isset($_POST['precio_venta']) || (float) $_POST['precio_venta'] <= 0
        || !isset($_POST['estado']) || trim((string) $_POST['estado']) === '') {
        return;
    }

    try {
        $app->actualizar($id, $_POST);
        $idPieza = (int) $_POST['id_pieza_FK'];
        if ($idPiezaFiltro === null && $idPieza > 0) {
            $idPiezaFiltro = $idPieza;
        }
        joyeria_redirect_stock_listado($idPiezaFiltro, 'Pieza de stock actualizada correctamente');
    } catch (Exception $e) {
        $idPieza = (int) $_POST['id_pieza_FK'];
        auth_set_flash('Error al actualizar la pieza de stock: ' . $e->getMessage(), 'error');
        $url = 'pieza.php?accion=stock_actualizar&id=' . (int) $id;
        if ($idPieza > 0) {
            $url .= '&id_pieza=' . $idPieza;
        }
        header('Location: ' . $url, true, 302);
        exit;
    }
}

/**
 * Redirige al listado de stock (GET) preservando filtro por pieza.
 */
function joyeria_redirect_stock_listado(?int $idPieza, ?string $mensaje, string $tipoFlash = 'success'): void
{
    if ($mensaje !== null && trim($mensaje) !== '') {
        auth_set_flash($mensaje, $tipoFlash);
    }
    $url = 'pieza.php?accion=stock';
    if ($idPieza !== null && $idPieza > 0) {
        $url .= '&id_pieza=' . $idPieza;
    }
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Despacha acciones de stock de piezas (listado y CRUD).
 * Usado desde pieza.php con acciones stock, stock_crear, etc.
 */
function joyeria_piezas_stock_dispatch(
    PiezasStock $app,
    ?int $id,
    ?string $accion,
    ?int $idPiezaFiltro,
    string $busqueda,
    string $tipoCodigoBarrasDefault,
    ?float $precioVentaHermano,
    array $catalogos,
    ?string &$mensaje
): void {
    $stockBaseScript = 'pieza.php';
    $stockAccionLeer = 'stock';
    $stockAccionCrear = 'stock_crear';
    $stockAccionActualizar = 'stock_actualizar';
    $stockAccionBorrar = 'stock_borrar';

    $accionInterna = match ($accion) {
        'stock_crear' => 'crear',
        'stock_actualizar' => 'actualizar',
        'stock_borrar' => 'borrar',
        'stock', null, '', 'leer' => 'leer',
        default => 'leer',
    };

    switch ($accionInterna) {
        case 'crear':
            $esPostCrear = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
            if (!isset($_POST['estado']) || trim((string) $_POST['estado']) === '') {
                $_POST['estado'] = 'disponible';
            }
            if (!isset($_POST['tipo_codigo']) || trim((string) $_POST['tipo_codigo']) === '') {
                $_POST['tipo_codigo'] = $tipoCodigoBarrasDefault;
            }
            $postValidoCrear = isset($_POST['id_pieza_FK']) && (int) $_POST['id_pieza_FK'] > 0
                && isset($_POST['precio_venta']) && (float) $_POST['precio_venta'] > 0;
            if ($esPostCrear && $postValidoCrear) {
                $mensaje = 'No se pudo crear la pieza de stock.';
            } elseif ($esPostCrear && !$postValidoCrear) {
                $mensaje = 'Todos los campos marcados con * son obligatorios.';
            }
            if (isset($_POST['id_pieza_FK']) && (int) $_POST['id_pieza_FK'] > 0) {
                $precioVentaHermano = $app->obtenerPrecioVentaHermano((int) $_POST['id_pieza_FK']);
                $idPiezaFiltro = (int) $_POST['id_pieza_FK'];
            }
            require __DIR__ . '/../views/piezas_stock/formulario.php';
            break;

        case 'actualizar':
            $esPostActualizar = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
            $postValidoActualizar = isset($_POST['id_pieza_FK']) && (int) $_POST['id_pieza_FK'] > 0
                && isset($_POST['precio_venta']) && (float) $_POST['precio_venta'] > 0
                && isset($_POST['estado']) && trim((string) $_POST['estado']) !== '';
            if ($esPostActualizar && $postValidoActualizar) {
                $mensaje = 'No se pudo actualizar la pieza de stock.';
            }
            $piezaStock = $app->leerUno($id);
            if ($idPiezaFiltro === null && is_array($piezaStock) && !empty($piezaStock['id_pieza_FK'])) {
                $idPiezaFiltro = (int) $piezaStock['id_pieza_FK'];
            }
            require __DIR__ . '/../views/piezas_stock/formulario.php';
            break;

        case 'borrar':
            if ($id) {
                if ($idPiezaFiltro === null) {
                    $stockRow = $app->leerUno($id);
                    if (is_array($stockRow) && !empty($stockRow['id_pieza_FK'])) {
                        $idPiezaFiltro = (int) $stockRow['id_pieza_FK'];
                    }
                }
                try {
                    $idUsuarioActual = $_SESSION['id_usuario'] ?? null;
                    $app->eliminar($id, $idUsuarioActual);
                    $mensaje = 'Pieza de stock dada de baja correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja la pieza de stock: ' . $e->getMessage();
                }
            }
            $piezasStock = $app->leer($idPiezaFiltro, $busqueda);
            require __DIR__ . '/../views/piezas_stock/index.php';
            break;

        case 'leer':
        default:
            $piezasStock = $app->leer($idPiezaFiltro, $busqueda);
            require __DIR__ . '/../views/piezas_stock/index.php';
            break;
    }
}
