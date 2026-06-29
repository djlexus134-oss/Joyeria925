<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/tienda_auth.php';
require_once __DIR__ . '/../admin/models/venta_online.php';

if (!tienda_is_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$tiendaUser = tienda_auth_user();
$idCliente = (int) ($tiendaUser['id_cliente'] ?? 0);
$nombreMostrar = trim((string) ($tiendaUser['nombre_completo'] ?? $tiendaUser['nombre'] ?? ''));

$ventaOnline = new VentaOnline();
$compras = $ventaOnline->listarParaCliente($idCliente);

function joyeria_compras_label_entrega(string $estado): array
{
    switch ($estado) {
        case 'lista_recoger':
            return ['Lista para recoger', 'bg-success'];
        case 'entregada':
            return ['Entregada', 'bg-secondary'];
        case 'cancelada':
            return ['Cancelada', 'bg-danger'];
        default:
            return ['Pendiente de preparar', 'bg-warning text-dark'];
    }
}

function joyeria_compras_label_pago(string $estado): array
{
    switch ($estado) {
        case 'pagado':
            return ['Pagado', 'bg-success'];
        case 'rechazado':
            return ['Rechazado', 'bg-danger'];
        case 'reembolsado':
            return ['Reembolsado', 'bg-secondary'];
        default:
            return ['Pendiente', 'bg-warning text-dark'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis compras | Platería El Ángel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="usuario-zona-bar border-bottom py-2 px-3 small d-flex flex-wrap justify-content-between align-items-center gap-2" style="background:#fafafa;">
    <span class="text-muted">Hola, <strong class="text-dark"><?php echo htmlspecialchars($nombreMostrar, ENT_QUOTES, 'UTF-8'); ?></strong></span>
    <span class="d-flex flex-wrap gap-3">
        <a href="index.php" class="link-dark text-decoration-none">Catálogo</a>
        <a href="carrito.php" class="link-dark text-decoration-none">Carrito</a>
        <a href="logout.php" class="link-dark text-decoration-none">Cerrar sesión</a>
    </span>
</div>

<div class="container py-4" style="max-width:960px;">
    <h2 class="mb-4"><i class="bi bi-bag-check" aria-hidden="true"></i> Mis compras</h2>

    <div class="alert alert-info small" role="note">
        <i class="bi bi-shop" aria-hidden="true"></i>
        Tus piezas se recogen en la sucursal correspondiente. Trae identificación oficial y tu número de orden.
    </div>

    <?php if ($compras === []): ?>
        <div class="text-center py-5">
            <p class="text-muted lead">Aún no tienes compras en línea.</p>
            <a href="index.php#catalogo" class="btn btn-dark">Explorar catalogo</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th>Sucursal</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Pago</th>
                        <th>Entrega</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $c): ?>
                        <?php
                        [$ent, $entCls] = joyeria_compras_label_entrega((string) ($c['estado_entrega'] ?? ''));
                        [$pago, $pagoCls] = joyeria_compras_label_pago((string) ($c['estado_pago'] ?? ''));
                        ?>
                        <tr>
                            <td><strong>#<?php echo (int) $c['id_venta']; ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $c['fecha_venta'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($c['nom_tienda'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) ($c['items_count'] ?? 0); ?></td>
                            <td>$<?php echo number_format((float) ($c['total'] ?? 0), 2, '.', ','); ?></td>
                            <td><span class="badge <?php echo $pagoCls; ?>"><?php echo htmlspecialchars($pago, ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><span class="badge <?php echo $entCls; ?>"><?php echo htmlspecialchars($ent, ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
