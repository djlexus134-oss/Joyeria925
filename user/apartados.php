<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/tienda_auth.php';

if (!tienda_is_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$tiendaUser = tienda_auth_user();
$nombreMostrar = trim((string) ($tiendaUser['nombre_completo'] ?? $tiendaUser['nombre'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis apartados | Platería El Ángel</title>
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
        <a href="compras.php" class="link-dark text-decoration-none">Mis compras</a>
        <a href="logout.php" class="link-dark text-decoration-none">Cerrar sesión</a>
    </span>
</div>

<div class="container py-5" style="max-width:760px;">
    <h2 class="mb-3"><i class="bi bi-bookmark" aria-hidden="true"></i> Mis apartados</h2>
    <p class="text-muted">
        Si tienes apartados pendientes en la sucursal, te invitamos a consultarlos directamente en la tienda
        donde realizaste el apartado.
    </p>
    <p>
        Para tus <strong>compras en linea</strong> (entrega en tienda), consulta el apartado
        <a href="compras.php">Mis compras</a>.
    </p>
</div>
</body>
</html>
