<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/joyeria_branding.php';
require_once __DIR__ . '/admin/includes/auth.php';
require_once __DIR__ . '/includes/turnstile_helpers.php';

$initialError = '';
$flash = auth_pull_flash();
if ($flash !== null && ($flash['type'] ?? '') === 'error') {
    $initialError = trim((string) ($flash['message'] ?? ''));
}

$turnstileEnabled = joyeria_turnstile_enabled();
$turnstileSiteKey = joyeria_turnstile_site_key();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Acceso'), ENT_QUOTES, 'UTF-8'); ?></title>
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

            <h2><i class="bi bi-shield-lock"></i> Iniciar sesión</h2>

            <div
                id="generalBanner"
                class="alert-message error"
                data-initial-error="<?php echo htmlspecialchars($initialError, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $initialError === '' ? 'hidden' : ''; ?>
            ><?php echo htmlspecialchars($initialError, ENT_QUOTES, 'UTF-8'); ?></div>

            <div class="general-pane is-active" id="pane-login">
                <form id="formGeneralLogin" class="admin-form admin-login-form" autocomplete="on">
                    <div class="form-group">
                        <label for="correo_login"><i class="bi bi-person-badge"></i> Correo o teléfono</label>
                        <input type="text" id="correo_login" name="correo" class="form-input" required autocomplete="username">
                        <small class="form-hint">También puedes usar tu número de teléfono registrado.</small>
                    </div>
                    <div class="form-group">
                        <label for="contrasena_login"><i class="bi bi-key"></i> Contraseña</label>
                        <input type="password" id="contrasena_login" name="contrasena" class="form-input" required autocomplete="current-password">
                    </div>
                    <div class="general-login-links">
                        <a href="#" data-pane-target="pane-forgot">¿Olvidaste tu contraseña?</a>
                        <a href="#" data-pane-target="pane-register">¿No tienes cuenta? Crear una</a>
                    </div>
                    <?php if ($turnstileEnabled): ?>
                        <div class="form-group joyeria-turnstile-wrap"></div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn-action-primary btn-login-submit">
                            <i class="bi bi-box-arrow-in-right"></i> Entrar
                        </button>
                    </div>
                </form>
            </div>

            <div class="general-pane" id="pane-register">
                <form id="formGeneralRegister" class="admin-form admin-login-form" autocomplete="on">
                    <div class="form-group">
                        <label for="nombre_reg"><i class="bi bi-person"></i> Nombre</label>
                        <input type="text" id="nombre_reg" name="nombre" class="form-input" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="ap_reg"><i class="bi bi-person-vcard"></i> Primer apellido</label>
                        <input type="text" id="ap_reg" name="primer_apellido" class="form-input" required maxlength="25">
                    </div>
                    <div class="form-group">
                        <label for="am_reg"><i class="bi bi-person-vcard"></i> Segundo apellido</label>
                        <input type="text" id="am_reg" name="segundo_apellido" class="form-input" maxlength="25">
                    </div>
                    <div class="form-group">
                        <label for="correo_reg"><i class="bi bi-envelope"></i> Correo</label>
                        <input type="email" id="correo_reg" name="correo" class="form-input" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="telefono_reg"><i class="bi bi-telephone"></i> Teléfono</label>
                        <input type="text" id="telefono_reg" name="telefono" class="form-input" required maxlength="15" autocomplete="tel">
                    </div>
                    <div class="form-group">
                        <label for="contrasena_reg"><i class="bi bi-key"></i> Contraseña</label>
                        <input type="password" id="contrasena_reg" name="contrasena" class="form-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="contrasena_confirm_reg"><i class="bi bi-shield-check"></i> Confirmar contraseña</label>
                        <input type="password" id="contrasena_confirm_reg" name="contrasena_confirm" class="form-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <?php if ($turnstileEnabled): ?>
                        <div class="form-group joyeria-turnstile-wrap"></div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn-action-primary btn-login-submit">
                            <i class="bi bi-person-plus"></i> Crear cuenta
                        </button>
                    </div>
                    <div class="general-login-links single">
                        <a href="#" data-pane-target="pane-login">Ya tengo cuenta</a>
                    </div>
                </form>
            </div>

            <div class="general-pane" id="pane-forgot">
                <form id="formGeneralForgot" class="admin-form admin-login-form" autocomplete="on">
                    <div class="form-group">
                        <label for="correo_forgot"><i class="bi bi-envelope-open"></i> Correo</label>
                        <input type="email" id="correo_forgot" name="correo" class="form-input" required autocomplete="email">
                    </div>
                    <?php if ($turnstileEnabled): ?>
                        <div class="form-group joyeria-turnstile-wrap"></div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn-action-primary btn-login-submit">
                            <i class="bi bi-send"></i> Enviar enlace de recuperación
                        </button>
                    </div>
                    <div class="general-login-links single">
                        <a href="#" data-pane-target="pane-login">Volver a iniciar sesión</a>
                    </div>
                </form>
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
    <script src="js/login-general.js"></script>
</body>
</html>
