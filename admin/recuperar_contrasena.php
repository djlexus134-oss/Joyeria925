<?php
/**
 * recuperar_contrasena.php
 * 
 * Página donde el usuario resetea su contraseña usando un token válido
 * Accesible solo con el token del correo de recuperación
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/joyeria_session.php';
joyeria_session_start();

require_once(__DIR__ . "/../sistema.class.php");
require_once(__DIR__ . "/includes/PasswordRecoveryService.php");

$mensaje = null;
$tipo_mensaje = 'info';
$tokenRaw = '';
if (isset($_POST['token']) && is_string($_POST['token'])) {
    $tokenRaw = trim($_POST['token']);
} elseif (isset($_GET['token']) && is_string($_GET['token'])) {
    $tokenRaw = trim($_GET['token']);
}
$token = $tokenRaw !== '' ? $tokenRaw : null;
$tokenValido = false;
$usuarioId = null;

if ($token !== null && $token !== '') {
    $recovery = new PasswordRecoveryService();
    $validacion = $recovery->validarToken($token);
    
    if ($validacion['valid']) {
        $tokenValido = true;
        $usuarioId = $validacion['user_id'];
    } else {
        $mensaje = $validacion['message'];
        $tipo_mensaje = 'error';
    }
} else {
    $mensaje = "Token no proporcionado o inválido";
    $tipo_mensaje = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    $accion = isset($_POST['accion']) ? htmlspecialchars($_POST['accion']) : null;
    
    if ($accion === 'resetear') {
        $nuevaContrasena = isset($_POST['nueva_contrasena']) ? $_POST['nueva_contrasena'] : '';
        $confirmarContrasena = isset($_POST['confirmar_contrasena']) ? $_POST['confirmar_contrasena'] : '';
        
        if (empty($nuevaContrasena)) {
            $mensaje = "Por favor ingresa una contraseña";
            $tipo_mensaje = 'error';
        } elseif (strlen($nuevaContrasena) < 8) {
            $mensaje = "La contraseña debe tener al menos 8 caracteres";
            $tipo_mensaje = 'error';
        } elseif ($nuevaContrasena !== $confirmarContrasena) {
            $mensaje = "Las contraseñas no coinciden";
            $tipo_mensaje = 'error';
        } else {
            $recovery = new PasswordRecoveryService();
            $resultado = $recovery->resetearContrasena($token, $nuevaContrasena);
            
            if ($resultado['success']) {
                $mensaje = "Contraseña actualizada exitosamente. Puedes iniciar sesión ahora.";
                $tipo_mensaje = 'success';
                $tokenValido = false;
            } else {
                $mensaje = $resultado['message'];
                $tipo_mensaje = 'error';
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña — Platería El Ángel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        body.auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1rem, 4vw, 3rem);
            background:
                radial-gradient(ellipse 70% 50% at 18% 25%, rgba(191, 161, 74, 0.18) 0%, transparent 55%),
                radial-gradient(ellipse 55% 45% at 88% 78%, rgba(255, 255, 255, 0.05) 0%, transparent 50%),
                linear-gradient(158deg, #12100e 0%, #29231f 45%, #3f3933 100%);
            color: var(--text);
        }

        .auth-card {
            position: relative;
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            border-radius: 18px;
            padding: clamp(2rem, 4vw, 2.75rem);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.45);
            animation: authSlideUp 0.5s ease-out;
        }

        @keyframes authSlideUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-brand {
            text-align: center;
            font-family: 'Jost', sans-serif;
            font-size: 0.7rem;
            letter-spacing: 0.32em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 1.5rem;
        }

        .auth-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(140deg, #d8b964 0%, #bfa14a 55%, #9c8038 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.1rem;
            box-shadow: 0 12px 28px rgba(191, 161, 74, 0.32);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .auth-header h1 {
            font-family: 'Jost', sans-serif;
            font-size: clamp(1.4rem, 2.4vw, 1.75rem);
            font-weight: 600;
            color: var(--heading);
            margin: 0 0 0.4rem;
            letter-spacing: -0.01em;
        }

        .auth-header p {
            font-family: 'Jost', sans-serif;
            font-size: 0.7rem;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--text);
            margin: 0;
            opacity: 0.75;
        }

        .auth-alert {
            border-radius: 10px;
            border: 1px solid transparent;
            padding: 0.85rem 1rem;
            font-size: 0.92rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            line-height: 1.45;
        }

        .auth-alert i {
            font-size: 1.05rem;
            line-height: 1.4;
        }

        .auth-alert--success {
            background: #f4f9ee;
            border-color: #d2e6b9;
            color: #3a5a1f;
        }

        .auth-alert--error {
            background: #fbf1f1;
            border-color: #ecc8c8;
            color: #8a2e2e;
        }

        .auth-alert--info {
            background: #f7f4ed;
            border-color: #e7dcc4;
            color: #6b5a2c;
        }

        .auth-requirements {
            background: #faf8f3;
            border-left: 3px solid var(--accent);
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            font-family: 'Jost', sans-serif;
            font-size: 0.82rem;
        }

        .auth-requirement {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: #9a8e6b;
            transition: color 0.2s ease;
        }

        .auth-requirement + .auth-requirement {
            margin-top: 0.4rem;
        }

        .auth-requirement i {
            font-size: 1rem;
            color: #c9c2b0;
            transition: color 0.2s ease;
        }

        .auth-requirement.met {
            color: #5e8a3b;
        }

        .auth-requirement.met i {
            color: #5e8a3b;
        }

        .auth-field {
            margin-bottom: 1.15rem;
        }

        .auth-label {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-family: 'Jost', sans-serif;
            font-size: 0.72rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--heading);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .auth-label i {
            color: var(--accent);
            font-size: 0.95rem;
        }

        .auth-input-wrap {
            position: relative;
        }

        .auth-input {
            width: 100%;
            padding: 0.75rem 3rem 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--heading);
            font-family: 'Arsenal', sans-serif;
            font-size: 0.98rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .auth-input::placeholder {
            color: #b8b3aa;
        }

        .auth-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(191, 161, 74, 0.18);
        }

        .auth-toggle {
            position: absolute;
            top: 50%;
            right: 0.55rem;
            transform: translateY(-50%);
            background: transparent;
            border: 0;
            color: var(--text);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .auth-toggle:hover,
        .auth-toggle:focus-visible {
            background: rgba(191, 161, 74, 0.12);
            color: var(--accent);
            outline: none;
        }

        .auth-help {
            display: block;
            font-size: 0.82rem;
            color: var(--text);
            margin-top: 0.45rem;
            opacity: 0.85;
        }

        .auth-submit {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.85rem 1.5rem;
            border-radius: 999px;
            border: 1px solid var(--heading);
            background: var(--heading);
            color: #fff;
            font-family: 'Jost', sans-serif;
            font-size: 0.82rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
        }

        .auth-submit:hover {
            background: var(--accent);
            border-color: var(--accent);
            transform: translateY(-1px);
        }

        .auth-submit:disabled {
            background: #d6d2c9;
            border-color: #d6d2c9;
            color: #fff;
            cursor: not-allowed;
            transform: none;
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .auth-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: 'Jost', sans-serif;
            font-size: 0.78rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--heading);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .auth-link:hover {
            color: var(--accent);
        }

        .auth-secondary {
            display: block;
            margin-top: 0.85rem;
            font-size: 0.85rem;
            color: var(--text);
        }

        .auth-secondary a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-secondary a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 1.75rem 1.35rem;
                border-radius: 14px;
            }
        }
    </style>
</head>
<body class="auth-page">
    <main class="auth-card" role="main">
        <div class="auth-brand">Platería El Ángel</div>

        <div class="auth-header">
            <div class="auth-icon" aria-hidden="true">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h1>Nueva Contraseña</h1>
            <p>Gestión Administrativa</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <?php
                $alertClass = 'auth-alert--info';
                $alertIcon = 'bi-info-circle';
                if ($tipo_mensaje === 'success') {
                    $alertClass = 'auth-alert--success';
                    $alertIcon = 'bi-check-circle';
                } elseif ($tipo_mensaje === 'error') {
                    $alertClass = 'auth-alert--error';
                    $alertIcon = 'bi-exclamation-circle';
                }
            ?>
            <div class="auth-alert <?php echo $alertClass; ?>" role="alert">
                <i class="bi <?php echo $alertIcon; ?>"></i>
                <span><?php echo htmlspecialchars($mensaje); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($tokenValido): ?>
            <div class="auth-requirements" aria-live="polite">
                <div class="auth-requirement" id="req-len">
                    <i class="bi bi-circle"></i>
                    <span>Mínimo 8 caracteres</span>
                </div>
                <div class="auth-requirement" id="req-match">
                    <i class="bi bi-circle"></i>
                    <span>Las contraseñas coinciden</span>
                </div>
            </div>

            <form method="POST" id="resetForm" novalidate>
                <input type="hidden" name="accion" value="resetear">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="auth-field">
                    <label class="auth-label" for="nueva_contrasena">
                        <i class="bi bi-key"></i> Nueva Contraseña
                    </label>
                    <div class="auth-input-wrap">
                        <input type="password" class="auth-input" id="nueva_contrasena" name="nueva_contrasena"
                               placeholder="Ingresa tu nueva contraseña" required autofocus autocomplete="new-password">
                        <button type="button" class="auth-toggle" id="togglePassword1" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                    <small class="auth-help">
                        Mínimo 8 caracteres. Usa mayúsculas, números y símbolos para mayor seguridad.
                    </small>
                </div>

                <div class="auth-field">
                    <label class="auth-label" for="confirmar_contrasena">
                        <i class="bi bi-lock"></i> Confirmar Contraseña
                    </label>
                    <div class="auth-input-wrap">
                        <input type="password" class="auth-input" id="confirmar_contrasena" name="confirmar_contrasena"
                               placeholder="Confirma tu contraseña" required autocomplete="new-password">
                        <button type="button" class="auth-toggle" id="togglePassword2" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="auth-submit">
                    <i class="bi bi-check2-circle"></i> Actualizar Contraseña
                </button>
            </form>

            <script>
                (function () {
                    function togglePassword(btnId, inputId) {
                        const btn = document.getElementById(btnId);
                        const input = document.getElementById(inputId);
                        if (!btn || !input) return;
                        btn.addEventListener('click', function () {
                            const icon = btn.querySelector('i');
                            const showing = input.type === 'text';
                            input.type = showing ? 'password' : 'text';
                            btn.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
                            if (icon) {
                                icon.classList.toggle('bi-eye', showing);
                                icon.classList.toggle('bi-eye-slash', !showing);
                            }
                        });
                    }

                    togglePassword('togglePassword1', 'nueva_contrasena');
                    togglePassword('togglePassword2', 'confirmar_contrasena');

                    const password = document.getElementById('nueva_contrasena');
                    const confirm = document.getElementById('confirmar_contrasena');
                    const reqLen = document.getElementById('req-len');
                    const reqMatch = document.getElementById('req-match');

                    function setMet(el, met) {
                        if (!el) return;
                        el.classList.toggle('met', met);
                        const icon = el.querySelector('i');
                        if (icon) {
                            icon.classList.toggle('bi-check-circle-fill', met);
                            icon.classList.toggle('bi-circle', !met);
                        }
                    }

                    function validate() {
                        setMet(reqLen, password.value.length >= 8);
                        setMet(reqMatch, password.value.length > 0 && password.value === confirm.value);
                    }

                    password.addEventListener('input', validate);
                    confirm.addEventListener('input', validate);
                })();
            </script>
        <?php endif; ?>

        <div class="auth-footer">
            <a class="auth-link" href="login.php">
                <i class="bi bi-box-arrow-in-right"></i> Ir al Login
            </a>
            <?php if (!$tokenValido && $tipo_mensaje === 'error'): ?>
                <small class="auth-secondary">
                    ¿Necesitas un nuevo enlace? <a href="solicitar_recuperacion.php">Solicita uno aquí</a>
                </small>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
