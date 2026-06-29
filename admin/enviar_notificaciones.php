<?php
require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2>Enviar notificaciones</h2>
</header>

<div class="admin-main">
    <?php require_once __DIR__ . '/views/enviar_notificaciones/index.php'; ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
