<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/capital_inventario.php';
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

$model = new CapitalInventario();
$idTienda = isset($_GET['id_tienda']) ? max(0, (int) $_GET['id_tienda']) : 0;
$idFamilia = isset($_GET['id_familia']) ? max(0, (int) $_GET['id_familia']) : 0;

$tiendasActivas = $model->listarTiendasActivas();
$familiasActivas = $model->listarFamiliasActivas();
$tiendaNombreFiltro = $model->resolverNombreTienda($tiendasActivas, $idTienda);
$familiaNombreFiltro = $model->resolverNombreFamilia($familiasActivas, $idFamilia);

$filas = $model->listarDetalle($idTienda, $idFamilia);
$resumen = $model->obtenerResumen($filas);
$mostrarSubtotalesFamilia = $idFamilia <= 0;

$exportarPdf = isset($_GET['exportar_pdf']) && (string) $_GET['exportar_pdf'] === '1';

$queryPdf = http_build_query([
    'accion' => 'leer',
    'exportar_pdf' => '1',
    'id_tienda' => $idTienda,
    'id_familia' => $idFamilia,
]);

if ($exportarPdf) {
    $fechaReporte = date('Y-m-d H:i');
    $titulo = 'Capital en tienda (costo de inventario)';
    $fmtMoney = static function (float $monto): string {
        return '$' . number_format($monto, 2, '.', ',');
    };

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
        tr.subtotal td { background: #f3f4f6; font-weight: bold; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
    </style>';
    $html .= '<div class="header">';
    $html .= '<div class="brand">' . htmlspecialchars($titulo) . '</div>';
    $html .= '<div class="subtitle">Reporte generado el ' . htmlspecialchars($fechaReporte) . '</div>';
    $html .= '</div>';
    $html .= '<table class="meta"><tr>';
    $html .= '<td><strong>Tienda:</strong> ' . htmlspecialchars($tiendaNombreFiltro) . '<br>';
    $html .= '<strong>Familia:</strong> ' . htmlspecialchars($familiaNombreFiltro) . '</td>';
    $html .= '<td class="right"><strong>Piezas:</strong> ' . (int) $resumen['num_piezas'] . '<br>';
    $html .= '<strong>Familias:</strong> ' . (int) $resumen['num_familias'] . '</td>';
    $html .= '</tr></table>';
    $html .= '<table class="summary"><tr>';
    $html .= '<td><strong>' . htmlspecialchars($fmtMoney((float) $resumen['total_costo'])) . '</strong>Costo total en inventario</td>';
    $html .= '<td><strong>' . number_format((int) $resumen['total_unidades'], 0, '.', ',') . '</strong>Unidades disponibles</td>';
    $html .= '<td><strong>' . (int) $resumen['num_piezas'] . '</strong>Piezas de catalogo</td>';
    $html .= '<td><strong>' . (int) $resumen['num_familias'] . '</strong>Familias</td>';
    $html .= '</tr></table>';
    $html .= '<table class="items"><thead><tr>';
    $html .= '<th>Familia</th><th>Subfamilia</th><th class="right">ID</th><th>Descripcion</th><th>Metal</th><th>Tienda</th>';
    $html .= '<th class="right">Unidades</th><th class="right">Costo unit.</th><th class="right">Costo total</th>';
    $html .= '</tr></thead><tbody>';

    if ($filas === []) {
        $html .= '<tr><td colspan="9" class="muted">No hay inventario disponible para el filtro actual.</td></tr>';
    } else {
        $familiaAnterior = null;
        $subUnidades = 0;
        $subCosto = 0.0;
        $subNombre = '';

        $emitirSubtotal = static function () use (&$html, &$subUnidades, &$subCosto, &$subNombre, $fmtMoney, $mostrarSubtotalesFamilia): void {
            if (!$mostrarSubtotalesFamilia || $subNombre === '') {
                return;
            }
            $html .= '<tr class="subtotal">';
            $html .= '<td colspan="6">Subtotal ' . htmlspecialchars($subNombre) . '</td>';
            $html .= '<td class="right">' . number_format($subUnidades, 0, '.', ',') . '</td>';
            $html .= '<td></td>';
            $html .= '<td class="right">' . htmlspecialchars($fmtMoney($subCosto)) . '</td>';
            $html .= '</tr>';
        };

        foreach ($filas as $fila) {
            $idFamiliaFila = (int) ($fila['id_familia'] ?? 0);
            if ($mostrarSubtotalesFamilia && $familiaAnterior !== null && $idFamiliaFila !== $familiaAnterior) {
                $emitirSubtotal();
                $subUnidades = 0;
                $subCosto = 0.0;
                $subNombre = '';
            }

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($fila['nom_familia'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($fila['nom_sub_familia'] ?? '')) . '</td>';
            $html .= '<td class="right">' . (int) ($fila['id_pieza'] ?? 0) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($fila['desc_pieza'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($fila['nom_metal'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($fila['nom_tienda'] ?? '')) . '</td>';
            $html .= '<td class="right">' . (int) ($fila['unidades'] ?? 0) . '</td>';
            $html .= '<td class="right">' . htmlspecialchars($fmtMoney((float) ($fila['costo_unitario'] ?? 0))) . '</td>';
            $html .= '<td class="right">' . htmlspecialchars($fmtMoney((float) ($fila['costo_total'] ?? 0))) . '</td>';
            $html .= '</tr>';

            if ($mostrarSubtotalesFamilia) {
                $subUnidades += (int) ($fila['unidades'] ?? 0);
                $subCosto += (float) ($fila['costo_total'] ?? 0);
                $subNombre = (string) ($fila['nom_familia'] ?? '');
                $familiaAnterior = $idFamiliaFila;
            }
        }
        if ($mostrarSubtotalesFamilia && $subNombre !== '') {
            $emitirSubtotal();
        }
    }

    $html .= '</tbody></table>';

    joyeria_mpdf_descargar_html(
        $html,
        $titulo,
        'capital_inventario_' . date('Ymd_His') . '.pdf'
    );
}

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2><i class="bi bi-cash-stack"></i> Capital en tienda (costo de inventario)</h2>
</header>

<div class="admin-main">
    <?php require __DIR__ . '/views/capital_inventario/index.php'; ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
