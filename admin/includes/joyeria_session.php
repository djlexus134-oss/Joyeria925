<?php
/**
 * Inicio de sesion PHP con duracion larga (panel admin + tienda).
 * Configurable con JOYERIA_SESSION_LIFETIME en config.php (segundos).
 */
declare(strict_types=1);

function joyeria_session_lifetime_seconds(): int
{
    $default = 60 * 60 * 24 * 30; // 30 dias
    if (defined('JOYERIA_SESSION_LIFETIME')) {
        return max(3600, (int) JOYERIA_SESSION_LIFETIME);
    }

    return $default;
}

function joyeria_request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
}

/**
 * Inicia sesion (o renueva la cookie si ya esta activa).
 */
function joyeria_session_start(): void
{
    $lifetime = joyeria_session_lifetime_seconds();

    if (session_status() === PHP_SESSION_ACTIVE) {
        joyeria_session_refresh_cookie($lifetime);
        return;
    }

    if (session_status() === PHP_SESSION_DISABLED) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.cookie_lifetime', (string) $lifetime);

    $cookieParams = [
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => joyeria_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
            '',
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_start();
    joyeria_session_refresh_cookie($lifetime);
}

/**
 * Devuelve el token CSRF de la sesion admin, generandolo si no existe.
 * Solo valido cuando hay una sesion activa.
 */
function joyeria_admin_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['_admin_csrf_token'])) {
        $_SESSION['_admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_admin_csrf_token'];
}

/**
 * Verifica el token CSRF recibido contra el de sesion.
 * Acepta el token en la cabecera X-CSRF-Token o en el campo _csrf_token del body.
 */
function joyeria_admin_csrf_verify(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['_admin_csrf_token'])) {
        return false;
    }
    $expected = (string) $_SESSION['_admin_csrf_token'];

    $fromHeader = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($fromHeader !== '' && hash_equals($expected, $fromHeader)) {
        return true;
    }

    $fromPost = trim((string) ($_POST['_csrf_token'] ?? ''));
    if ($fromPost !== '' && hash_equals($expected, $fromPost)) {
        return true;
    }

    $fromJson = joyeria_admin_csrf_token_from_json_body();
    if ($fromJson !== '' && hash_equals($expected, $fromJson)) {
        return true;
    }

    return false;
}

/**
 * Body crudo de la peticion (cacheado; php://input solo se lee una vez).
 */
function joyeria_request_raw_body(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    $cached = $raw === false ? '' : $raw;

    return $cached;
}

/**
 * Token CSRF enviado en JSON (_csrf_token o csrf_token).
 */
function joyeria_admin_csrf_token_from_json_body(): string
{
    static $parsed = null;
    if ($parsed !== null) {
        return $parsed;
    }
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $parsed = '';
        return $parsed;
    }
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($ct, 'application/json') === false) {
        $parsed = '';
        return $parsed;
    }
    $raw = joyeria_request_raw_body();
    if ($raw === '') {
        $parsed = '';
        return $parsed;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $parsed = '';
        return $parsed;
    }
    if (isset($data['_csrf_token']) && trim((string) $data['_csrf_token']) !== '') {
        $parsed = trim((string) $data['_csrf_token']);
        return $parsed;
    }
    if (isset($data['csrf_token']) && trim((string) $data['csrf_token']) !== '') {
        $parsed = trim((string) $data['csrf_token']);
        return $parsed;
    }
    $parsed = '';

    return $parsed;
}

/**
 * Responde 403 con la pantalla estandar de token CSRF invalido (formularios admin).
 */
function joyeria_admin_csrf_send_denied_response(): void
{
    if (headers_sent()) {
        exit;
    }
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error de seguridad</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/main.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../css/main.css'); ?>">
        <link rel="stylesheet" href="../css/admin.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../css/admin.css'); ?>">
    </head>
    <body class="admin-login-body">
        <div class="admin-login-wrap">
            <section class="admin-login-card">
                <h2>Error de seguridad</h2>
                <p>Token de seguridad invalido. Por favor recarga la pagina e intenta de nuevo.</p>
                <div class="form-actions">
                    <a href="javascript:history.back()" class="btn-action-primary">Volver</a>
                </div>
            </section>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * En peticiones POST del panel, corta con 403 si falta o no coincide el token CSRF.
 */
function joyeria_admin_csrf_require_for_post(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }
    if (joyeria_admin_csrf_verify()) {
        return;
    }
    joyeria_admin_csrf_send_denied_response();
}

function joyeria_session_refresh_cookie(int $lifetime): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }

    $params = session_get_cookie_params();
    $expires = time() + $lifetime;
    $name = session_name();
    $id = session_id();
    if ($name === '' || $id === '') {
        return;
    }

    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $id, [
            'expires' => $expires,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    } else {
        setcookie(
            $name,
            $id,
            $expires,
            ($params['path'] ?: '/') . '; samesite=Lax',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }
}
