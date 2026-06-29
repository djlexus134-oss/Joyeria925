<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/KPIDashboardService.php';

$svc = new KPIDashboardService();
$section = isset($_GET['section']) ? trim((string) $_GET['section']) : 'summary';

try {
    api_require_method('GET');

    if (!auth_is_admin() && !auth_has_permission('PANEL_LEER')) {
        api_fail('No tienes permiso para consultar el panel de KPIs.', 403);
    }

    $from = isset($_GET['from']) ? (string) $_GET['from'] : null;
    $to = isset($_GET['to']) ? (string) $_GET['to'] : null;
    [$fromN, $toN] = $svc->normalizeRange($from, $to);

    $idEmpleado = isset($_GET['id_empleado']) ? (int) $_GET['id_empleado'] : 0;
    $idTienda = isset($_GET['id_tienda']) ? (int) $_GET['id_tienda'] : 0;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    if ($limit <= 0) {
        $limit = 10;
    }
    if ($limit > 50) {
        $limit = 50;
    }

    $filters = [
        'from' => $fromN,
        'to' => $toN,
    ];
    if ($idEmpleado > 0) {
        $filters['id_empleado'] = $idEmpleado;
    }
    if ($idTienda > 0) {
        // Nota: por ahora se expone para UI; no todas las consultas lo aplican (ventas no tiene id_tienda).
        $filters['id_tienda'] = $idTienda;
    }

    switch ($section) {
        case 'summary':
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => $svc->getSummaryCards($filters),
            ]);

        case 'trend':
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => $svc->getTrendDiario($filters),
            ]);

        case 'payments':
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => $svc->getFormasPago($filters),
            ]);

        case 'top_products':
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => $svc->getTopProductos($filters, $limit),
            ]);

        case 'ranking':
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => $svc->getRankingEmpleados($filters, $limit),
            ]);

        case 'alerts':
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => [
                    'devolucion_por_empleado' => $svc->getTasaDevolucionPorEmpleado($filters, $limit),
                ],
            ]);

        case 'filters':
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => $svc->getFilterOptions(),
            ]);

        case 'profit':
            $user = auth_user();
            $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
            if (!auth_has_admin_role_in_array($roles)) {
                api_fail('No tienes permiso para consultar ganancia neta.', 403);
            }
            api_ok([
                'range' => ['from' => $fromN, 'to' => $toN],
                'data' => $svc->getGananciaNeta($filters),
            ]);

        default:
            api_fail('Seccion no soportada.', 422, [
                'supported' => ['summary', 'trend', 'payments', 'top_products', 'ranking', 'alerts', 'filters', 'profit'],
            ]);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
