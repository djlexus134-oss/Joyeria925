<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin/includes/joyeria_session.php';
joyeria_session_start();

require_once __DIR__ . '/sistema.class.php';
require_once __DIR__ . '/admin/includes/TiendaEmailVerificationService.php';
require_once __DIR__ . '/admin/includes/tienda_auth.php';

$mensaje = '';
$tipoMensaje = 'info';
$tokenRaw = '';
if (isset($_GET['token']) && is_string($_GET['token'])) {
    $tokenRaw = trim($_GET['token']);
}

$confirmado = false;

if ($tokenRaw !== '') {
    $verification = new TiendaEmailVerificationService();
    $resultado = $verification->confirmarCorreo($tokenRaw);
    if ($resultado['success']) {
        tienda_clear_verificacion_pendiente();
        $confirmado = true;
        $mensaje = $resultado['message'];
        $tipoMensaje = 'success';
    } else {
        $mensaje = $resultado['message'];
        $tipoMensaje = 'error';
    }
} else {
    $mensaje = 'Enlace invalido. Abre el enlace completo desde tu correo de confirmacion.';
    $tipoMensaje = 'error';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar correo | Platería El Ángel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-login-body">
    <div class="admin-login-wrap">
        <section class="admin-login-card general-login-card">
            <div class="admin-login-brand">
                <h1>Platería El Ángel</h1>
            </div>

            <h2><i class="bi bi-envelope-check"></i> Confirmación de correo</h2>

            <div class="alert-message <?php echo htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <?php if ($confirmado): ?>
                <div class="form-actions">
                    <a href="login.php" class="btn-action-primary btn-login-submit">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                    </a>
                </div>
            <?php else: ?>
                <div class="general-login-links single">
                    <a href="login.php">Volver al inicio de sesión</a>
                </div>
            <?php endif; ?>

            <div class="general-login-bottom">
                <a href="index.php"><i class="bi bi-arrow-left"></i> Volver al inicio</a>
            </div>
        </section>
    </div>
</body>
</html>
