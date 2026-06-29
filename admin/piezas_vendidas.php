<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/piezas_vendidas.php';
require_once __DIR__ . '/includes/joyeria_mpdf.php';

$guard = auth_current_access_guard();
if (!$guard['allowed']) {
    auth_set_flash((string) $guard['message'], 'error');
    if (!empty($guard['redirect'])) {
        header('Location: ' . $guard['redirect']);
        exit;
    }
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$accion = isset($_GET['accion']) ? mb_strtolower(trim((string) $_GET['accion'])) : 'leer';
if ($accion !== 'leer') {
    $accion = 'leer';
}

$model = new PiezasVendidas();
$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');
$dias = isset($_GET['dias']) ? (int) $_GET['dias'] : 90;
if ($dias < 1) {
    $dias = 90;
}
$stockMax = isset($_GET['stock_max']) ? (int) $_GET['stock_max'] : 2;
if ($stockMax < 0) {
    $stockMax = 2;
}
$idTienda = isset($_GET['id_tienda']) ? (int) $_GET['id_tienda'] : 0;
$idTiendaFiltro = $idTienda > 0 ? $idTienda : null;

$tiendasActivas = $model->listarTiendasActivas();
$tiendaNombreFiltro = 'Todas';
if ($idTiendaFiltro !== null) {
    foreach ($tiendasActivas as $tiendaRow) {
        if ((int) ($tiendaRow['id_tienda'] ?? 0) === $idTiendaFiltro) {
            $tiendaNombreFiltro = (string) ($tiendaRow['nom_tienda'] ?? '');
            break;
        }
    }
}

$piezas = $model->leer($busqueda, $dias, $stockMax, $idTiendaFiltro);
$totalPiezas = count($piezas);
$totalUnidadesStock = 0;
$totalVentasPeriodo = 0;
$totalSugeridoComprar = 0;
foreach ($piezas as $row) {
    $totalUnidadesStock += (int) ($row['stock_actual'] ?? 0);
    $totalVentasPeriodo += (int) ($row['ventas_periodo'] ?? 0);
    $totalSugeridoComprar += (int) ($row['sugerido_comprar'] ?? 0);
}

$exportarPdf = isset($_GET['exportar_pdf']) && (string) $_GET['exportar_pdf'] === '1';

$queryPdf = http_build_query([
    'accion' => 'leer',
    'exportar_pdf' => '1',
    'q' => $busqueda,
    'dias' => $dias,
    'stock_max' => $stockMax,
    'id_tienda' => $idTienda,
]);

if ($exportarPdf) {
    $fechaReporte = date('Y-m-d H:i');
    $titulo = 'Sugerencia de resurtido';
    $html = '<style>
        body { font-family: sans-serif; font-size: 11pt; color: #1f2937; }
        .header { border-bottom: 2px solid #1f2937; margin-bottom: 14px; padding-bottom: 10px; }
        .brand { font-size: 15pt; font-weight: bold; }
        .subtitle { color: #6b7280; font-size: 9pt; }
        .meta { width: 100%; margin: 10px 0 14px; border-collapse: collapse; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .summary { width: 100%; margin: 0 0 14px; border-collapse: collapse; }
        .summary td { border: 1px solid #d1d5db; padding: 8px 10px; }
        .summary strong { display: block; font-size: 13pt; margin-bottom: 2px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #111827; color: #fff; padding: 8px; font-size: 8pt; text-align: left; }
        table.items td { border-bottom: 1px solid #e5e7eb; padding: 8px; vertical-align: top; font-size: 8pt; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
    </style>';
    $html .= '<div class="header">';
    $html .= '<div class="brand">' . htmlspecialchars($titulo) . '</div>';
    $html .= '<div class="subtitle">Reporte generado el ' . htmlspecialchars($fechaReporte) . '</div>';
    $html .= '</div>';
    $html .= '<table class="meta"><tr>';
    $html .= '<td><strong>Busqueda:</strong> ' . htmlspecialchars($busqueda !== '' ? $busqueda : 'Sin filtro') . '<br>';
    $html .= '<strong>Periodo:</strong> ultimos ' . (int) $dias . ' dias<br>';
    $html .= '<strong>Stock maximo en lista:</strong> ' . (int) $stockMax . '</td>';
    $html .= '<td class="right"><strong>Tienda:</strong> ' . htmlspecialchars($tiendaNombreFiltro) . '</td>';
    $html .= '</tr></table>';
    $html .= '<table class="summary"><tr>';
    $html .= '<td><strong>' . (int) $totalPiezas . '</strong>Piezas sugeridas</td>';
    $html .= '<td><strong>' . number_format($totalUnidadesStock, 0, '.', ',') . '</strong>Stock disponible</td>';
    $html .= '<td><strong>' . number_format($totalVentasPeriodo, 0, '.', ',') . '</strong>Ventas en periodo</td>';
    $html .= '<td><strong>' . number_format($totalSugeridoComprar, 0, '.', ',') . '</strong>Unidades sugeridas a comprar</td>';
    $html .= '</tr></table>';
    $html .= '<table class="items"><thead><tr>';
    $html .= '<th class="right">ID</th><th>Descripcion</th><th>Subfamilia</th><th>Metal</th><th>Proveedor</th><th>Tienda</th>';
    $html .= '<th class="right">Ventas</th><th class="right">Stock</th><th class="right">Sugerido</th><th>Ultima venta</th>';
    $html .= '</tr></thead><tbody>';
    if ($piezas === []) {
        $html .= '<tr><td colspan="10" class="muted">No hay piezas que correspondan al filtro actual.</td></tr>';
    } else {
        foreach ($piezas as $pieza) {
            $ultimaVenta = $pieza['ultima_venta'] ?? null;
            $ultimaVentaTxt = '—';
            if ($ultimaVenta !== null && $ultimaVenta !== '') {
                $ts = strtotime((string) $ultimaVenta);
                $ultimaVentaTxt = $ts !== false ? date('Y-m-d', $ts) : (string) $ultimaVenta;
            }
            $html .= '<tr>';
            $html .= '<td class="right">' . (int) ($pieza['id_pieza'] ?? 0) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($pieza['desc_pieza'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($pieza['nom_sub_familia'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($pieza['nom_metal'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($pieza['razon_social'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($pieza['nom_tienda'] ?? '')) . '</td>';
            $html .= '<td class="right">' . (int) ($pieza['ventas_periodo'] ?? 0) . '</td>';
            $html .= '<td class="right">' . (int) ($pieza['stock_actual'] ?? 0) . '</td>';
            $html .= '<td class="right"><strong>' . (int) ($pieza['sugerido_comprar'] ?? 0) . '</strong></td>';
            $html .= '<td>' . htmlspecialchars($ultimaVentaTxt) . '</td>';
            $html .= '</tr>';
        }
    }
    $html .= '</tbody></table>';

    joyeria_mpdf_descargar_html(
        $html,
        $titulo,
        'sugerencia_resurtido_' . date('Ymd_His') . '.pdf'
    );
}

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2><i class="bi bi-cart-plus"></i> Sugerencia de resurtido</h2>
</header>

<div class="admin-main">
    <?php require __DIR__ . '/views/piezas_vendidas/index.php'; ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
