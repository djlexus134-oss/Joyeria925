<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/venta_online.php';
require_once __DIR__ . '/includes/list_search.php';
require_once __DIR__ . '/includes/NotificacionService.php';

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
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mensaje = null;
$mensajeTipo = 'info';

$model = new VentaOnline();
$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');
$filtros = [
    'estado_entrega' => isset($_GET['ee']) ? (string) $_GET['ee'] : '',
    'estado_pago' => isset($_GET['ep']) ? (string) $_GET['ep'] : '',
    'id_tienda' => isset($_GET['id_tienda']) ? (int) $_GET['id_tienda'] : 0,
    'fecha_desde' => isset($_GET['fd']) ? trim((string) $_GET['fd']) : '',
    'fecha_hasta' => isset($_GET['fh']) ? trim((string) $_GET['fh']) : '',
];

if ($accion === 'marcar_lista' && $id > 0) {
    $r = $model->marcarListaParaRecoger($id);
    if ($r['ok']) {
        try {
            (new NotificacionService())->notificarListaParaRecoger($id);
        } catch (Throwable $e) {
            error_log('ventas_online marcar_lista notif: ' . $e->getMessage());
        }
        $mensaje = 'Venta marcada como lista para recoger. Se envio aviso al cliente.';
        $mensajeTipo = 'info';
    } else {
        $mensaje = $r['error'] ?? 'No se pudo actualizar.';
        $mensajeTipo = 'error';
    }
    $accion = 'ver';
}

if ($accion === 'marcar_entregada' && $id > 0) {
    $authU = auth_user();
    $idEmp = 0;
    if (is_array($authU) && isset($authU['id_usuario'])) {
        $sistema = new Sistema();
        $stmt = $sistema->getDb()->prepare('SELECT id_empleado FROM empleados WHERE id_usuario_FK = :id AND activo = 1 LIMIT 1');
        $stmt->bindValue(':id', (int) $authU['id_usuario'], PDO::PARAM_INT);
        $stmt->execute();
        $idEmp = (int) ($stmt->fetchColumn() ?: 0);
    }
    if ($idEmp <= 0) {
        $mensaje = 'No estas asociado a un empleado activo para registrar entrega.';
        $mensajeTipo = 'error';
    } else {
        $r = $model->marcarEntregada($id, $idEmp);
        if ($r['ok']) {
            $mensaje = 'Venta marcada como entregada.';
            $mensajeTipo = 'info';
        } else {
            $mensaje = $r['error'] ?? 'No se pudo actualizar.';
            $mensajeTipo = 'error';
        }
    }
    $accion = 'ver';
}

$tiendasActivas = $model->listarTiendasActivas();

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2>Ventas en linea</h2>
</header>

<div class="admin-main">
    <?php if ($mensaje !== null): ?>
        <div class="alert-message <?php echo htmlspecialchars($mensajeTipo); ?>">
            <p><?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php
    if ($accion === 'ver' && $id > 0) {
        $venta = $model->leerUno($id);
        if (!is_array($venta)) {
            echo '<div class="alert-message error"><p>Venta no encontrada.</p></div>';
        } else {
            require __DIR__ . '/views/ventas_online/detalle.php';
        }
    } else {
        $ventas = $model->listarParaAdmin($busqueda, $filtros);
        require __DIR__ . '/views/ventas_online/index.php';
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
