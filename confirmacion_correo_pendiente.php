<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/joyeria_branding.php';
require_once __DIR__ . '/admin/includes/tienda_auth.php';
require_once __DIR__ . '/includes/turnstile_helpers.php';

$correoPendiente = tienda_get_verificacion_pendiente();
if ($correoPendiente === null) {
    header('Location: login.php');
    exit;
}

$correoEnmascarado = tienda_enmascarar_correo($correoPendiente);
$turnstileEnabled = joyeria_turnstile_enabled();
$turnstileSiteKey = joyeria_turnstile_site_key();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Confirma tu correo'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-login-body">
    <div class="admin-login-wrap">
        <section class="admin-login-card general-login-card">
            <div class="admin-login-brand">
                <h1><?php echo htmlspecialchars(joyeria_marca_nombre(), ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>

            <h2><i class="bi bi-envelope-check"></i> Revisa tu correo</h2>

            <div class="alert-message info">
                Tu correo de confirmación fue enviado a
                <strong><?php echo htmlspecialchars($correoEnmascarado, ENT_QUOTES, 'UTF-8'); ?></strong>.
                Revisa tu bandeja de entrada y la carpeta de spam.
            </div>

            <p class="text-muted small mb-0">
                Abre el enlace del correo para activar tu cuenta. Después podrás iniciar sesión.
            </p>

            <div
                id="resendVerificationSection"
                class="mt-4"
                hidden
                data-correo="<?php echo htmlspecialchars($correoPendiente, ENT_QUOTES, 'UTF-8'); ?>"
            >
                <p class="small mb-2">¿No lo recibiste?</p>
                <div id="resendBanner" class="alert-message info" hidden></div>
                <?php if ($turnstileEnabled): ?>
                    <div class="form-group joyeria-turnstile-wrap mb-3"></div>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="button" id="btnResendVerificationPending" class="btn-action-secondary btn-login-submit">
                        <i class="bi bi-send"></i> Reenviar correo de confirmación
                    </button>
                </div>
            </div>

            <div class="general-login-links single mt-4">
                <a href="login.php">Ir a iniciar sesión</a>
            </div>

            <div class="general-login-bottom">
                <a href="index.php"><i class="bi bi-arrow-left"></i> Volver al inicio</a>
            </div>
        </section>
    </div>

    <script>
        window.JOYERIA_TURNSTILE = {
            enabled: <?php echo $turnstileEnabled ? 'true' : 'false'; ?>,
            siteKey: <?php echo json_encode($turnstileSiteKey, JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    <script src="js/turnstile-form.js"></script>
    <?php if ($turnstileEnabled): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    <?php endif; ?>
    <script src="js/confirmacion-correo-pendiente.js"></script>
</body>
</html>
