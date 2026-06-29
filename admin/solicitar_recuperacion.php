<?php
/**
 * solicitar_recuperacion.php
 * 
 * Formulario público para solicitar recuperación de contraseña
 * NO requiere autenticación
 */

session_start();

require_once(__DIR__ . "/../sistema.class.php");
require_once(__DIR__ . "/includes/PasswordRecoveryService.php");
require_once(__DIR__ . "/includes/MailService.php");

$mensaje = null;
$tipo_mensaje = 'info';
$paso = 1; // Paso 1: Ingresar correo | Paso 2: Confirmación

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? htmlspecialchars($_POST['accion']) : null;
    
    if ($accion === 'solicitar') {
        $correo = isset($_POST['correo']) ? strtolower(trim($_POST['correo'])) : null;
        
        if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "Por favor ingresa un correo válido";
            $tipo_mensaje = 'error';
        } else {
            $recovery = new PasswordRecoveryService();
            $resultado = $recovery->solicitarRecuperacion($correo);
            
            if ($resultado['success']) {
                $usuario = $resultado['user_data'];
                $token = $resultado['token'];
                $baseUrl = MailService::appBaseUrl();
                $fullRecoveryUrl = ($baseUrl !== '' ? $baseUrl : '') . '/admin/recuperar_contrasena.php?token=' . urlencode($token);

                $resultMail = MailService::enviarRecuperacionContrasena(
                    $usuario,
                    $token,
                    $baseUrl,
                    60,
                    $fullRecoveryUrl
                );
                
                if ($resultMail['success']) {
                    $mensaje = "✓ Se ha enviado un correo a " . htmlspecialchars($correo) . " con instrucciones para recuperar tu contraseña.";
                    $tipo_mensaje = 'success';
                    $paso = 2;
                } else {
                    $mensaje = "⚠ Solicitud procesada, pero hubo un error al enviar el correo: " . $resultMail['message'];
                    $tipo_mensaje = 'warning';
                }
            } else {
                // Mensaje genérico para seguridad (no revelar si el correo existe)
                $mensaje = "Si el correo existe en nuestro sistema, recibirás instrucciones de recuperación.";
                $tipo_mensaje = 'info';
                $paso = 2;
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Sistema Joyería</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #333 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .recovery-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .recovery-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .recovery-header h2 {
            color: #1a1a1a;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .recovery-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        .icon-circle {
            width: 60px;
            height: 60px;
            background-color: #f4d03f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 15px;
            color: #1a1a1a;
        }
        .form-group label {
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            padding: 12px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #f4d03f;
            box-shadow: 0 0 0 0.2rem rgba(244, 208, 63, 0.25);
        }
        .btn-recovery {
            background-color: #1a1a1a;
            color: #f4d03f;
            font-weight: bold;
            padding: 12px;
            border: none;
            border-radius: 5px;
            width: 100%;
            transition: background-color 0.3s;
        }
        .btn-recovery:hover {
            background-color: #000;
            color: #f4d03f;
            text-decoration: none;
        }
        .btn-recovery:disabled {
            background-color: #ccc;
            color: #999;
            cursor: not-allowed;
        }
        .alert {
            border-radius: 5px;
            border: none;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .login-link a {
            color: #f4d03f;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .step-info {
            background-color: #f9f9f9;
            border-left: 4px solid #f4d03f;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #666;
        }
        .clock-icon {
            color: #f4d03f;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="recovery-container">
        <div class="recovery-header">
            <div class="icon-circle">
                <i class="fas fa-key"></i>
            </div>
            <h2>Recuperar Contraseña</h2>
            <p>Sistema Joyería - Gestión Administrativa</p>
        </div>

        <!-- Mostrar mensajes -->
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" role="alert">
                <strong>
                    <?php 
                        if ($tipo_mensaje === 'success') echo '✓ ';
                        elseif ($tipo_mensaje === 'error') echo '✗ ';
                        elseif ($tipo_mensaje === 'warning') echo '⚠ ';
                    ?>
                </strong>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- PASO 1: Solicitar Recuperación -->
        <?php if ($paso === 1): ?>
            <div class="step-info">
                <i class="fas fa-info-circle"></i>
                Ingresa tu correo electrónico registrado. Te enviaremos un enlace seguro para resetear tu contraseña.
            </div>

            <form method="POST">
                <input type="hidden" name="accion" value="solicitar">
                
                <div class="form-group">
                    <label for="correo">
                        <i class="fas fa-envelope"></i> Correo Electrónico
                    </label>
                    <input type="email" class="form-control" id="correo" name="correo" 
                           placeholder="tu.correo@ejemplo.com" required autofocus>
                    <small class="form-text text-muted">
                        Asegúrate de escribir el correo correctamente
                    </small>
                </div>

                <button type="submit" class="btn btn-recovery">
                    <i class="fas fa-paper-plane"></i> Enviar Instrucciones
                </button>
            </form>
        <?php else: ?>
            <!-- PASO 2: Confirmación -->
            <div class="alert alert-success" role="alert">
                <h5 class="alert-heading">
                    <i class="fas fa-check-circle"></i> Correo Enviado
                </h5>
                <p>Revisa tu bandeja de entrada (y la carpeta de spam). Encontrarás un correo con un enlace seguro válido por 1 hora.</p>
            </div>

            <div class="step-info">
                <i class="fas fa-lightbulb"></i>
                <strong>Consejos útiles:</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>El enlace es único y se puede usar solo una vez</li>
                    <li>Expira en 1 hora por motivos de seguridad</li>
                    <li>Si no recibas el correo en 5 minutos, solicita uno nuevo</li>
                </ul>
            </div>

            <form method="POST" style="margin-top: 30px;">
                <input type="hidden" name="accion" value="solicitar">
                <button type="submit" name="nuevo_intento" value="1" class="btn btn-recovery">
                    <i class="fas fa-redo"></i> Solicitar Nuevo Enlace
                </button>
            </form>
        <?php endif; ?>

        <div class="login-link">
            <small>¿Recuerdas tu contraseña?</small><br>
            <a href="login.php">
                <i class="fas fa-sign-in-alt"></i> Volver al Login
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
