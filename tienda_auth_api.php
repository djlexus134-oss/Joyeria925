<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/joyeria_json_guard.php';
joyeria_json_guard_begin();
require_once __DIR__ . '/admin/includes/tienda_auth.php';
require_once __DIR__ . '/admin/includes/PasswordRecoveryService.php';
require_once __DIR__ . '/admin/includes/TiendaEmailVerificationService.php';
require_once __DIR__ . '/admin/includes/MailService.php';
require_once __DIR__ . '/includes/turnstile_helpers.php';
require_once __DIR__ . '/includes/rate_limit_helpers.php';

header('Content-Type: application/json; charset=utf-8');

/** Ruta relativa a la raíz del sitio (mismo nivel que este script). */
const JOYERIA_REDIRECT_VISTA_CLIENTE = 'user/index.php';
const JOYERIA_REDIRECT_VISTA_ADMIN = 'admin/index.php';
const JOYERIA_REDIRECT_VERIFICACION_PENDIENTE = 'confirmacion_correo_pendiente.php';

/**
 * Raíz del sitio (mismo nivel que este script): https://host/ruta_sin_barra_final
 */
function joyeria_public_base_url(): string
{
    // Preferir la URL publica configurada: evita Host Header Injection en los
    // enlaces de verificacion/recuperacion que se envian por correo (un atacante
    // no puede falsificar la cabecera Host para robar el token).
    if (defined('JOYERIA_APP_URL') && trim((string) JOYERIA_APP_URL) !== '') {
        return rtrim((string) JOYERIA_APP_URL, '/');
    }

    // Fallback solo para desarrollo local (sin JOYERIA_APP_URL definida).
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
    $dir = $scriptName !== '' ? dirname($scriptName) : '';
    $path = '';
    if ($dir !== '' && $dir !== '.' && $dir !== '/') {
        $path = rtrim($dir, '/');
    }

    return $scheme . '://' . $host . $path;
}

$raw = file_get_contents('php://input');
$input = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$action = isset($input['action']) ? trim((string) $input['action']) : '';

function json_out(array $data): void
{
    joyeria_json_clean_buffer();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Persiste la sesion antes de responder JSON (login AJAX).
 */
function tienda_api_commit_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    joyeria_session_refresh_cookie(joyeria_session_lifetime_seconds());
    session_write_close();
}

function tienda_api_rechazar_turnstile(array $input): bool
{
    if (joyeria_turnstile_rechazar_si_invalido($input)) {
        return false;
    }

    json_out(['ok' => false, 'error' => joyeria_turnstile_error_mensaje()]);
    return true;
}

/**
 * Aplica rate-limiting a una accion sensible. Devuelve true (y responde 429)
 * si se supero el limite; en ese caso el caller debe hacer break.
 *
 * La clave combina la IP del cliente con un sufijo opcional (p.ej. el correo)
 * para frenar tanto fuerza bruta como bombardeo dirigido a un buzon.
 */
function tienda_api_rate_limit(string $accion, string $sufijoClave, int $maxIntentos, int $ventanaSegundos): bool
{
    $clave = joyeria_rate_limit_ip();
    $sufijo = mb_strtolower(trim($sufijoClave));
    if ($sufijo !== '') {
        $clave .= '|' . $sufijo;
    }

    $res = joyeria_rate_limit_check(
        tienda_auth_service()->getDb(),
        $accion,
        $clave,
        $maxIntentos,
        $ventanaSegundos
    );
    if ($res['permitido']) {
        return false;
    }

    http_response_code(429);
    json_out([
        'ok' => false,
        'rate_limited' => true,
        'error' => 'Demasiados intentos. Espera ' . (int) $res['reintentar_en']
            . ' segundos e intenta de nuevo.',
    ]);
    return true;
}

function tienda_api_enviar_verificacion(int $idUsuario): array
{
    $verification = new TiendaEmailVerificationService();
    $resultado = $verification->crearToken($idUsuario);
    if (
        !$resultado['success']
        || !isset($resultado['token'], $resultado['user_data'])
    ) {
        return [
            'success' => false,
            'message' => $resultado['message'] ?? 'No se pudo generar el enlace.',
        ];
    }

    $usuario = $resultado['user_data'];
    $token = (string) $resultado['token'];
    $base = joyeria_public_base_url();
    $urlVerificacion = $base . '/verificar_correo.php?token=' . urlencode($token);

    return MailService::enviarVerificacionCorreoTienda($usuario, $urlVerificacion, 24);
}

switch ($action) {
    case 'session':
        json_out([
            'ok' => true,
            'user' => tienda_auth_user(),
        ]);
        break;

    case 'logout':
        tienda_logout();
        json_out(['ok' => true, 'user' => null]);
        break;

    case 'login': {
            if (tienda_api_rate_limit('login', '', 12, 300)) {
                break;
            }
            if (tienda_api_rechazar_turnstile($input)) {
                break;
            }

            require_once __DIR__ . '/admin/includes/auth.php';
            require_once __DIR__ . '/includes/telefono_helpers.php';

            $identificador = isset($input['identificador']) ? trim((string) $input['identificador']) : '';
            if ($identificador === '' && isset($input['correo'])) {
                $identificador = trim((string) $input['correo']);
            }
            $contrasena = isset($input['contrasena']) ? (string) $input['contrasena'] : '';

            $errCliente = null;
            $errAdmin = null;
            $codeAdmin = null;
            $failureCode = null;

            // Empleados con rol/permisos deben entrar al admin aunque tambien existan como cliente.
            if (
                joyeria_identificador_es_correo($identificador)
                && auth_login($identificador, $contrasena, $errAdmin, $codeAdmin)
            ) {
                unset($_SESSION[JOYERIA_TIENDA_SESSION_KEY]);
                $redirectAdmin = auth_default_admin_redirect();
                tienda_api_commit_session();
                json_out([
                    'ok' => true,
                    'user' => null,
                    'redirect' => $redirectAdmin,
                    'account_type' => 'admin',
                ]);
            }

            if (tienda_login($identificador, $contrasena, $errCliente, $failureCode)) {
                auth_logout();
                tienda_api_commit_session();
                json_out([
                    'ok' => true,
                    'user' => tienda_auth_user(),
                    'redirect' => JOYERIA_REDIRECT_VISTA_CLIENTE,
                    'account_type' => 'cliente',
                ]);
                break;
            }

            if ($failureCode === 'EMAIL_NOT_VERIFIED') {
                $correoResend = '';
                if (joyeria_identificador_es_correo($identificador)) {
                    $correoResend = joyeria_cliente_correo_normalizado($identificador);
                } else {
                    $match = tienda_auth_service()->findClienteActivoPorTelefono($identificador);
                    if ($match !== null) {
                        $correoResend = joyeria_cliente_correo_normalizado((string) ($match['correo'] ?? ''));
                    }
                }
                if ($correoResend !== '') {
                    tienda_set_verificacion_pendiente($correoResend);
                }
                json_out([
                    'ok' => false,
                    'error' => $errCliente ?? 'Debes confirmar tu correo antes de iniciar sesion.',
                    'redirect' => JOYERIA_REDIRECT_VERIFICACION_PENDIENTE,
                ]);
                break;
            }

            json_out(auth_build_login_failure($identificador, $errAdmin, $errCliente));
            break;
    }

    case 'register': {
            if (tienda_api_rate_limit('register', '', 6, 600)) {
                break;
            }
            if (tienda_api_rechazar_turnstile($input)) {
                break;
            }

            try {
                $correo = tienda_validar_correo_registro(isset($input['correo']) ? (string) $input['correo'] : '');
                $c1 = tienda_validar_contrasena_registro(
                    isset($input['contrasena']) ? (string) $input['contrasena'] : '',
                    isset($input['contrasena_confirm']) ? (string) $input['contrasena_confirm'] : ''
                );
            } catch (InvalidArgumentException $e) {
                json_out(['ok' => false, 'error' => $e->getMessage()]);
                break;
            }

            $payload = [
                'nombre' => isset($input['nombre']) ? trim((string) $input['nombre']) : '',
                'primer_apellido' => isset($input['primer_apellido']) ? trim((string) $input['primer_apellido']) : '',
                'correo' => $correo,
                'telefono' => isset($input['telefono']) ? trim((string) $input['telefono']) : '',
                'contrasena' => $c1,
                'contrasena_confirm' => $c1,
            ];
            $s = isset($input['segundo_apellido']) ? trim((string) $input['segundo_apellido']) : '';
            if ($s !== '') {
                $payload['segundo_apellido'] = $s;
            }

            $err = null;
            $idCliente = tienda_register($payload, $err);
            if ($idCliente <= 0) {
                json_out(['ok' => false, 'error' => $err ?? 'No se pudo registrar.']);
                break;
            }

            $idUsuario = tienda_id_usuario_por_cliente($idCliente);
            $mailResult = [
                'success' => false,
                'message' => 'No intentado',
            ];
            if ($idUsuario > 0) {
                try {
                    $mailResult = tienda_api_enviar_verificacion($idUsuario);
                } catch (Throwable $e) {
                    $mailResult = [
                        'success' => false,
                        'message' => 'Excepcion: ' . $e->getMessage(),
                    ];
                }
            }

            $mensaje = ($mailResult['success'] ?? false)
                ? 'Cuenta creada. Revisa tu correo y confirma tu cuenta antes de iniciar sesion.'
                : 'Cuenta creada, pero no pudimos enviar el correo de confirmacion. Puedes solicitar un reenvio en la siguiente pantalla.';

            tienda_set_verificacion_pendiente($correo);

            json_out([
                'ok' => true,
                'registered' => true,
                'verification_required' => true,
                'user' => null,
                'message' => $mensaje,
                'mail_sent' => (bool) ($mailResult['success'] ?? false),
                'correo' => $correo,
                'redirect' => JOYERIA_REDIRECT_VERIFICACION_PENDIENTE,
            ]);
            break;
    }

    case 'resend_verification': {
            if (tienda_api_rate_limit('resend_verification', '', 10, 900)) {
                break;
            }
            if (tienda_api_rechazar_turnstile($input)) {
                break;
            }

            $correo = isset($input['correo']) ? mb_strtolower(trim((string) $input['correo'])) : '';
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                json_out(['ok' => false, 'error' => 'Introduce un correo valido.']);
                break;
            }
            if (tienda_api_rate_limit('resend_verification_correo', $correo, 4, 900)) {
                break;
            }

            $mensajeOk = 'Si el correo esta registrado y pendiente de confirmacion, recibiras un nuevo enlace. Revisa tambien spam.';

            try {
                $verification = new TiendaEmailVerificationService();
                $resultado = $verification->solicitarPorCorreo($correo);
                if (
                    ($resultado['success'] ?? false)
                    && !($resultado['generic'] ?? false)
                    && isset($resultado['token'], $resultado['user_data'])
                ) {
                    $usuario = $resultado['user_data'];
                    $token = (string) $resultado['token'];
                    $base = joyeria_public_base_url();
                    $urlVerificacion = $base . '/verificar_correo.php?token=' . urlencode($token);
                    MailService::enviarVerificacionCorreoTienda($usuario, $urlVerificacion, 24);
                }
            } catch (Throwable $e) {
                error_log('tienda_auth_api resend_verification: ' . $e->getMessage());
            }

            json_out(['ok' => true, 'message' => $mensajeOk]);
            break;
    }

    case 'forgot_password': {
            if (tienda_api_rate_limit('forgot_password', '', 10, 900)) {
                break;
            }
            if (tienda_api_rechazar_turnstile($input)) {
                break;
            }

            $correo = isset($input['correo']) ? strtolower(trim((string) $input['correo'])) : '';
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                json_out(['ok' => false, 'error' => 'Introduce un correo valido.']);
                break;
            }
            if (tienda_api_rate_limit('forgot_password_correo', $correo, 4, 900)) {
                break;
            }

            $mensajeOk = 'Si el correo esta registrado, recibiras instrucciones para recuperar tu contrasena. Revisa tambien spam.';

            try {
                $recovery = new PasswordRecoveryService();
                $resultado = $recovery->solicitarRecuperacion($correo);
                if (
                    !$resultado['success']
                    || !isset($resultado['token'], $resultado['user_data'])
                ) {
                    json_out(['ok' => true, 'message' => $mensajeOk]);
                    break;
                }

                $usuario = $resultado['user_data'];
                $token = (string) $resultado['token'];
                $base = joyeria_public_base_url();
                $fullRecoveryUrl = $base . '/admin/recuperar_contrasena.php?token=' . urlencode($token);

                $mailResult = MailService::enviarRecuperacionContrasena($usuario, $token, '', 60, $fullRecoveryUrl);
            } catch (Throwable $e) {
                error_log('tienda_auth_api forgot_password: ' . $e->getMessage());
            }

            json_out(['ok' => true, 'message' => $mensajeOk]);
            break;
    }

    default:
        json_out(['ok' => false, 'error' => 'Accion no reconocida.']);
}
