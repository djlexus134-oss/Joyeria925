<?php
/**
 * MailService.php - Servicio centralizado para envío de correos
 * 
 * Encapsula la lógica de PHPMailer para toda la aplicación
 * Proporciona métodos específicos para casos de uso comunes:
 * - Envío de credenciales a nuevos empleados
 * - Recuperación de contraseña
 * - Notificaciones generales
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../sistema.class.php';

if (is_file(__DIR__ . '/../../config.php')) {
    require_once __DIR__ . '/../../config.php';
}

class MailService
{
    /**
     * Valores por defecto sin credenciales (versionables).
     * Orden de resolución: estos defaults → configuracion_general → constantes JOYERIA_SMTP_* en config.php.
     */
    private const SMTP_CONFIG = [
        'host'       => '',
        'port'       => 465,
        'secure'     => PHPMailer::ENCRYPTION_SMTPS,
        'username'   => '',
        'password'   => '',
        'from_email' => '',
        'from_name'  => 'Sistema Joyería',
        'debug'      => SMTP::DEBUG_OFF,
    ];

    /**
     * Instancia estática de PHPMailer
     */
    private static $mailer = null;
    private static $resolvedConfig = null;

    private const SMTP_CONFIG_KEYS = [
        'host' => ['smtp_host', 'mail_smtp_host', 'correo_smtp_host'],
        'port' => ['smtp_port', 'mail_smtp_port', 'correo_smtp_port'],
        'secure' => ['smtp_secure', 'mail_smtp_secure', 'correo_smtp_secure'],
        'username' => ['smtp_username', 'mail_smtp_username', 'correo_smtp_username', 'smtp_user'],
        'password' => ['smtp_password', 'mail_smtp_password', 'correo_smtp_password', 'smtp_pass'],
        'from_email' => ['smtp_from_email', 'mail_from_email', 'correo_from_email', 'smtp_from'],
        'from_name' => ['smtp_from_name', 'mail_from_name', 'correo_from_name'],
        'debug' => ['smtp_debug', 'mail_smtp_debug', 'correo_smtp_debug'],
    ];

    /**
     * Obtiene o crea la instancia de PHPMailer singleton
     * 
     * @return PHPMailer
     * @throws Exception Si falla la configuración
     */
    private static function resolveSmtpConfig(): array
    {
        if (is_array(self::$resolvedConfig)) {
            return self::$resolvedConfig;
        }

        $c = self::SMTP_CONFIG;
        $c = self::mergeSystemMailConfig($c);
        if (defined('JOYERIA_SMTP_HOST')) {
            $c['host'] = JOYERIA_SMTP_HOST;
        }
        if (defined('JOYERIA_SMTP_PORT')) {
            $c['port'] = (int) JOYERIA_SMTP_PORT;
        }
        if (defined('JOYERIA_SMTP_SECURE')) {
            $c['secure'] = JOYERIA_SMTP_SECURE;
        }
        if (defined('JOYERIA_SMTP_USERNAME')) {
            $c['username'] = JOYERIA_SMTP_USERNAME;
        }
        if (defined('JOYERIA_SMTP_PASSWORD')) {
            $c['password'] = JOYERIA_SMTP_PASSWORD;
        }
        if (defined('JOYERIA_SMTP_FROM_EMAIL')) {
            $c['from_email'] = JOYERIA_SMTP_FROM_EMAIL;
        }
        if (defined('JOYERIA_SMTP_FROM_NAME')) {
            $c['from_name'] = JOYERIA_SMTP_FROM_NAME;
        }
        if (defined('JOYERIA_SMTP_DEBUG')) {
            $c['debug'] = (int) JOYERIA_SMTP_DEBUG;
        }

        $appUrl = self::readAppUrlFromDb();
        if ($appUrl !== '') {
            $c['app_url'] = $appUrl;
        } elseif (defined('JOYERIA_APP_URL') && trim((string) JOYERIA_APP_URL) !== '') {
            $c['app_url'] = trim((string) JOYERIA_APP_URL);
        }

        self::$resolvedConfig = $c;
        return self::$resolvedConfig;
    }

    private static function readAppUrlFromDb(): string
    {
        try {
            $sys = new Sistema();
            $stmt = $sys->getDb()->prepare(
                "SELECT valor FROM configuracion_general WHERE clave = 'app_url' LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($row['valor']) ? trim((string) $row['valor']) : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * URL base HTTPS del sitio para enlaces en correos.
     */
    public static function appBaseUrl(): string
    {
        $cfg = self::resolveSmtpConfig();
        $url = trim((string) ($cfg['app_url'] ?? ''));
        if ($url === '' && defined('JOYERIA_APP_URL')) {
            $url = trim((string) JOYERIA_APP_URL);
        }
        return self::normalizeBaseUrl($url);
    }

    /**
     * @return string|null Mensaje de error si SMTP no está configurado; null si puede enviarse.
     */
    private static function smtpConfigurationError(): ?string
    {
        $cfg = self::resolveSmtpConfig();
        $missing = [];
        if (trim((string) ($cfg['host'] ?? '')) === '') {
            $missing[] = 'host SMTP';
        }
        if (trim((string) ($cfg['username'] ?? '')) === '') {
            $missing[] = 'usuario SMTP';
        }
        if (trim((string) ($cfg['password'] ?? '')) === '') {
            $missing[] = 'contraseña SMTP';
        }
        if (trim((string) ($cfg['from_email'] ?? '')) === '') {
            $missing[] = 'correo remitente (from)';
        }
        if ($missing !== []) {
            return 'Correo no configurado: falta ' . implode(', ', $missing)
                . '. Define JOYERIA_SMTP_* en config.php o los valores en configuracion_general.';
        }
        return null;
    }

    private static function mergeSystemMailConfig(array $baseConfig): array
    {
        try {
            $allKeys = [];
            foreach (self::SMTP_CONFIG_KEYS as $group) {
                foreach ($group as $key) {
                    $allKeys[] = $key;
                }
            }
            $allKeys = array_values(array_unique($allKeys));
            if (empty($allKeys)) {
                return $baseConfig;
            }

            $sys = new Sistema();
            $db = $sys->getDb();
            $placeholders = implode(',', array_fill(0, count($allKeys), '?'));
            $stmt = $db->prepare(
                "SELECT clave, valor FROM configuracion_general WHERE clave IN ($placeholders)"
            );
            foreach ($allKeys as $idx => $key) {
                $stmt->bindValue($idx + 1, $key, PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $rawMap = [];
            foreach ($rows as $row) {
                $k = trim((string) ($row['clave'] ?? ''));
                if ($k === '') {
                    continue;
                }
                $rawMap[$k] = trim((string) ($row['valor'] ?? ''));
            }

            foreach (self::SMTP_CONFIG_KEYS as $target => $aliases) {
                foreach ($aliases as $alias) {
                    if (!isset($rawMap[$alias]) || $rawMap[$alias] === '') {
                        continue;
                    }
                    $value = $rawMap[$alias];
                    if ($target === 'port') {
                        $baseConfig[$target] = (int) $value;
                    } elseif ($target === 'debug') {
                        $baseConfig[$target] = (int) $value;
                    } elseif ($target === 'secure') {
                        $baseConfig[$target] = self::normalizeSecureMode($value);
                    } else {
                        $baseConfig[$target] = $value;
                    }
                    break;
                }
            }

        } catch (Throwable $e) {
            error_log('MailService::mergeSystemMailConfig: ' . $e->getMessage());
        }

        return $baseConfig;
    }

    private static function normalizeSecureMode(string $value): string
    {
        $v = strtolower(trim($value));
        if ($v === 'tls' || $v === 'starttls') {
            return PHPMailer::ENCRYPTION_STARTTLS;
        }
        if ($v === 'ssl' || $v === 'smtps') {
            return PHPMailer::ENCRYPTION_SMTPS;
        }
        if ($v === 'none' || $v === 'off' || $v === '0') {
            return '';
        }
        return $value;
    }

    private static function normalizeBaseUrl(string $urlApp): string
    {
        $url = rtrim(trim($urlApp), '/');
        if ($url === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
            $scheme = $https ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host !== '') {
                $url = $scheme . '://' . $host;
            }
        }

        if (preg_match('#/admin$#i', $url)) {
            $url = preg_replace('#/admin$#i', '', $url) ?? $url;
        }

        return rtrim($url, '/');
    }

    private static function getMailer(): PHPMailer
    {
        if (self::$mailer === null) {
            $cfg = self::resolveSmtpConfig();
            self::$mailer = new PHPMailer(true);

            self::$mailer->isSMTP();
            self::$mailer->SMTPDebug = $cfg['debug'];
            self::$mailer->Host = $cfg['host'];
            self::$mailer->Port = $cfg['port'];
            self::$mailer->SMTPSecure = $cfg['secure'];
            self::$mailer->SMTPAuth = true;
            self::$mailer->Username = $cfg['username'];
            self::$mailer->Password = $cfg['password'];
            self::$mailer->setFrom($cfg['from_email'], $cfg['from_name']);
            self::$mailer->CharSet = PHPMailer::CHARSET_UTF8;
            self::$mailer->Timeout = 30;
            self::$mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];
        }

        return self::$mailer;
    }

    /**
     * Reinicia la instancia (tras cambiar config en caliente).
     */
    public static function resetMailer(): void
    {
        self::$mailer = null;
        self::$resolvedConfig = null;
    }

    /**
     * Envía correo de credenciales a nuevo empleado
     * 
     * Caso de uso: Al crear un empleado, se le envía un correo con:
     * - Contraseña temporal
     * - Instrucciones de acceso
     * - Datos de contacto
     * 
     * @param array $empleado Array con keys: correo, nombre, primer_apellido, contrasena_temporal
     * @param string $urlApp URL base de la aplicación
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public static function enviarCredencialesEmpleado(array $empleado, string $urlApp = ''): array
    {
        try {
            $cfgErr = self::smtpConfigurationError();
            if ($cfgErr !== null) {
                return ['success' => false, 'message' => $cfgErr];
            }

            $mailer = self::getMailer();
            
            // Limpiar recipients previos
            $mailer->clearAllRecipients();
            
            $nombreCompleto = trim($empleado['nombre'] . ' ' . $empleado['primer_apellido']);
            $correo = trim($empleado['correo']);
            $contrasena = $empleado['contrasena_temporal'] ?? 'N/A';
            $baseUrl = $urlApp !== '' ? self::normalizeBaseUrl($urlApp) : self::appBaseUrl();
            $urlLogin = ($baseUrl !== '' ? $baseUrl : '') . '/admin/login.php';
            
            $mailer->addAddress($correo, $nombreCompleto);
            $mailer->Subject = 'Platería El Ángel — Credenciales de acceso (empleado)';
            
            // HTML del correo
            $html = self::templateCredencialesEmpleado([
                'nombre' => $nombreCompleto,
                'correo' => $correo,
                'contrasena' => $contrasena,
                'url_login' => $urlLogin
            ]);
            
            $mailer->msgHTML($html);
            $mailer->AltBody = "Bienvenido $nombreCompleto. Tu correo: $correo | Contraseña: $contrasena | Accede en: $urlLogin";

            $result = $mailer->send();

            return [
                'success' => $result,
                'message' => 'Correo de credenciales enviado exitosamente',
                'correo' => $correo
            ];
            
        } catch (Exception $e) {
            error_log("Error en MailService::enviarCredencialesEmpleado: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Avisa al empleado cuando cambian correo y/o contraseña de acceso al panel admin.
     *
     * @param array $empleado nombre, primer_apellido, correo (nuevo)
     * @param string|null $contrasenaPlano Nueva contraseña en texto plano; null si no cambió
     * @param bool $correoCambio true si el correo de login cambió
     */
    public static function enviarAccesoEmpleadoActualizado(
        array $empleado,
        ?string $contrasenaPlano = null,
        bool $correoCambio = false,
        string $urlApp = ''
    ): array {
        try {
            $cfgErr = self::smtpConfigurationError();
            if ($cfgErr !== null) {
                return ['success' => false, 'message' => $cfgErr];
            }

            $correo = trim((string) ($empleado['correo'] ?? ''));
            if ($correo === '') {
                return ['success' => false, 'message' => 'Correo del empleado vacío.'];
            }

            $mailer = self::getMailer();
            $mailer->clearAllRecipients();

            $nombreCompleto = trim(($empleado['nombre'] ?? '') . ' ' . ($empleado['primer_apellido'] ?? ''));
            $baseUrl = $urlApp !== '' ? self::normalizeBaseUrl($urlApp) : self::appBaseUrl();
            $urlLogin = ($baseUrl !== '' ? $baseUrl : '') . '/admin/login.php';

            $mailer->addAddress($correo, $nombreCompleto);
            $mailer->Subject = 'Platería El Ángel — Datos de acceso actualizados';

            $html = self::templateAccesoEmpleadoActualizado([
                'nombre' => $nombreCompleto,
                'correo' => $correo,
                'contrasena' => $contrasenaPlano !== null && $contrasenaPlano !== '' ? $contrasenaPlano : null,
                'correo_cambio' => $correoCambio,
                'url_login' => $urlLogin,
            ]);
            $mailer->msgHTML($html);

            $alt = "Hola $nombreCompleto. Se actualizaron tus datos de acceso al panel administrativo.";
            if ($correoCambio) {
                $alt .= " Nuevo correo: $correo.";
            }
            if ($contrasenaPlano !== null && $contrasenaPlano !== '') {
                $alt .= " Nueva contraseña: $contrasenaPlano.";
            }
            $alt .= " Accede: $urlLogin";
            $mailer->AltBody = $alt;

            $result = $mailer->send();

            return [
                'success' => (bool) $result,
                'message' => 'Correo de actualización enviado',
                'correo' => $correo,
            ];
        } catch (Exception $e) {
            error_log('MailService::enviarAccesoEmpleadoActualizado: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Prueba SMTP (CLI o script deploy).
     */
    public static function enviarPrueba(string $correoDestino): array
    {
        $correoDestino = trim($correoDestino);
        if ($correoDestino === '' || !filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Correo destino invalido.'];
        }

        $cfg = self::resolveSmtpConfig();
        $html = '<p>Prueba SMTP desde Sistema Joyería.</p><p>Servidor: '
            . htmlspecialchars((string) $cfg['host'], ENT_QUOTES, 'UTF-8') . '</p>';

        return self::enviarNotificacion(
            $correoDestino,
            'Prueba SMTP — Platería El Ángel',
            $html,
            'Prueba SMTP OK'
        );
    }

    /**
     * Envía correo de bienvenida a un nuevo cliente de la tienda.
     *
     * @param array $cliente Array con keys: correo, nombre, primer_apellido
     * @param string $urlApp URL base de la aplicación
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function enviarBienvenidaCliente(array $cliente, string $urlApp = ''): array
    {
        try {
            $cfgErr = self::smtpConfigurationError();
            if ($cfgErr !== null) {
                return ['success' => false, 'message' => $cfgErr];
            }

            $mailer = self::getMailer();
            $mailer->clearAllRecipients();

            $nombreCompleto = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['primer_apellido'] ?? ''));
            $correo = trim((string) ($cliente['correo'] ?? ''));
            $baseUrl = self::normalizeBaseUrl($urlApp);
            $urlCuenta = ($baseUrl !== '' ? $baseUrl : '') . '/user/index.php';

            $mailer->addAddress($correo, $nombreCompleto);
            $mailer->Subject = 'Bienvenido a Platería El Ángel';

            $html = self::templateBienvenidaCliente([
                'nombre' => $nombreCompleto !== '' ? $nombreCompleto : 'Cliente',
                'correo' => $correo,
                'url_cuenta' => $urlCuenta,
            ]);

            $mailer->msgHTML($html);
            $mailer->AltBody = "Hola $nombreCompleto, bienvenido a Platería El Ángel. Tu cuenta fue creada con el correo $correo. Accede en: $urlCuenta";

            $result = $mailer->send();

            return [
                'success' => $result,
                'message' => 'Correo de bienvenida enviado exitosamente',
                'correo' => $correo,
            ];
        } catch (Exception $e) {
            error_log("Error en MailService::enviarBienvenidaCliente: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Envía enlace para confirmar correo de cuenta de tienda en línea.
     *
     * @param array $cliente nombre, primer_apellido, correo
     * @param string $urlVerificacion URL completa con token
     * @param int $expirationHours Horas de validez del enlace
     *
     * @return array{success: bool, message: string, correo?: string}
     */
    public static function enviarVerificacionCorreoTienda(
        array $cliente,
        string $urlVerificacion,
        int $expirationHours = 24
    ): array {
        try {
            $cfgErr = self::smtpConfigurationError();
            if ($cfgErr !== null) {
                return ['success' => false, 'message' => $cfgErr];
            }

            $mailer = self::getMailer();
            $mailer->clearAllRecipients();

            $nombreCompleto = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['primer_apellido'] ?? ''));
            $correo = trim((string) ($cliente['correo'] ?? ''));

            $mailer->addAddress($correo, $nombreCompleto !== '' ? $nombreCompleto : 'Cliente');
            $mailer->Subject = 'Confirma tu correo - Platería El Ángel';

            $html = self::templateVerificacionCorreoTienda([
                'nombre' => $nombreCompleto !== '' ? $nombreCompleto : 'Cliente',
                'url_verificacion' => $urlVerificacion,
                'expiration_hours' => $expirationHours,
            ]);

            $mailer->msgHTML($html);
            $mailer->AltBody = "Hola $nombreCompleto, confirma tu cuenta en Platería El Ángel: $urlVerificacion (valido por $expirationHours horas)";

            $result = $mailer->send();

            return [
                'success' => $result,
                'message' => 'Correo de verificacion enviado',
                'correo' => $correo,
            ];
        } catch (Exception $e) {
            error_log('MailService::enviarVerificacionCorreoTienda: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Envía correo con credenciales (correo + contraseña temporal) a un cliente nuevo.
     * Misma idea que enviarCredencialesEmpleado pero con enlace al área de cliente (tienda).
     *
     * @param array $cliente nombre, primer_apellido, correo, contrasena_temporal
     * @param bool $esActualizacion Si true, el mensaje indica actualización de acceso (correo o contraseña).
     *
     * @return array{success: bool, message: string, correo?: string}
     */
    public static function enviarCredencialesCliente(array $cliente, string $urlApp = '', bool $esActualizacion = false): array
    {
        try {
            $cfgErr = self::smtpConfigurationError();
            if ($cfgErr !== null) {
                return ['success' => false, 'message' => $cfgErr];
            }

            $mailer = self::getMailer();
            $mailer->clearAllRecipients();

            $nombreCompleto = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['primer_apellido'] ?? ''));
            $correo = trim((string) ($cliente['correo'] ?? ''));
            $contrasena = $cliente['contrasena_temporal'] ?? '';
            if ($correo === '') {
                return ['success' => false, 'message' => 'Correo del cliente vacio.'];
            }

            $baseUrl = self::normalizeBaseUrl($urlApp);
            $urlCuenta = ($baseUrl !== '' ? $baseUrl : '') . '/user/index.php';

            $mailer->addAddress($correo, $nombreCompleto !== '' ? $nombreCompleto : 'Cliente');
            $mailer->Subject = $esActualizacion
                ? 'Tu cuenta en la tienda — acceso actualizado'
                : 'Tu cuenta en la tienda — credenciales de acceso';

            $html = self::templateCredencialesCliente([
                'nombre' => $nombreCompleto !== '' ? $nombreCompleto : 'Cliente',
                'correo' => $correo,
                'contrasena' => (string) $contrasena,
                'url_cuenta' => $urlCuenta,
                'es_actualizacion' => $esActualizacion,
            ]);
            $mailer->msgHTML($html);
            $altIntro = $esActualizacion ? 'Tus datos de acceso fueron actualizados.' : 'Bienvenido.';
            $mailer->AltBody = "Hola $nombreCompleto. $altIntro Correo: $correo | Contrasena: $contrasena | Accede: $urlCuenta";

            $result = $mailer->send();

            return [
                'success' => (bool) $result,
                'message' => 'Correo con credenciales enviado',
                'correo' => $correo,
            ];
        } catch (Exception $e) {
            error_log('MailService::enviarCredencialesCliente: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Template HTML - Credenciales de cliente (tienda en línea)
     */
    private static function templateCredencialesCliente(array $datos): string
    {
        $nombre = htmlspecialchars((string) $datos['nombre'], ENT_QUOTES, 'UTF-8');
        $correo = htmlspecialchars((string) $datos['correo'], ENT_QUOTES, 'UTF-8');
        $contrasena = htmlspecialchars((string) $datos['contrasena'], ENT_QUOTES, 'UTF-8');
        $urlCuenta = htmlspecialchars((string) $datos['url_cuenta'], ENT_QUOTES, 'UTF-8');
        $year = date('Y');
        $esActualizacion = !empty($datos['es_actualizacion']);
        $tituloCabecera = $esActualizacion ? 'Acceso actualizado' : 'Bienvenido';
        $subtituloCabecera = $esActualizacion
            ? 'Se actualizaron tus datos para entrar a la tienda en línea.'
            : 'Estas son tus credenciales para entrar a la tienda en línea.';
        $introCuerpo = $esActualizacion
            ? 'Tu correo o contraseña de acceso fue actualizado. Usa los siguientes datos para iniciar sesión:'
            : 'Tu cuenta fue creada. Usa los siguientes datos para iniciar sesión:';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu cuenta</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f4f1; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; color:#5c5c5c;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f6f4f1; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="620" style="max-width:620px; width:100%; background-color:#ffffff; border:1px solid #e2e2e2; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(158deg, #12100e 0%, #29231f 46%, #3f3933 100%); padding:36px 42px; text-align:center;">
                            <p style="margin:0 0 8px 0; font-size:11px; letter-spacing:0.35em; color:rgba(255,255,255,0.62); text-transform:uppercase;">Cuenta de cliente</p>
                            <h1 style="margin:0; font-size:26px; font-weight:600; color:#ffffff;">{$tituloCabecera}</h1>
                            <p style="margin:12px 0 0 0; font-size:14px; color:rgba(255,255,255,0.78);">{$subtituloCabecera}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 46px 0 46px; font-size:15px; line-height:1.65;">
                            <p style="margin:0 0 12px 0;">Hola <strong style="color:#171717;">{$nombre}</strong>,</p>
                            <p style="margin:0;">{$introCuerpo}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 46px 0 46px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#faf8f3; border-left:3px solid #bfa14a; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:14px; line-height:1.7; color:#4a4034;">
                                        <strong style="display:block; margin-bottom:8px; color:#5e5238;">Correo</strong>
                                        <code style="font-size:13px;">{$correo}</code><br><br>
                                        <strong style="display:block; margin-bottom:8px; color:#5e5238;">Contraseña temporal</strong>
                                        <code style="font-size:13px;">{$contrasena}</code>
                                        <p style="margin:12px 0 0 0; font-size:12px; color:#7a6f54;">Por seguridad, te recomendamos cambiarla en cuanto entres.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:26px 46px 12px 46px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="border-radius:999px; background-color:#171717;">
                                        <a href="{$urlCuenta}" style="display:inline-block; padding:14px 36px; color:#ffffff; text-decoration:none; border-radius:999px; font-size:12px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase;">Ir a mi cuenta</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 46px 36px 46px; text-align:center; font-size:11px; color:#a39b8e;">
                            <p style="margin:0;">Correo automático. Si no solicitaste esta cuenta, ignora este mensaje.</p>
                            <p style="margin:10px 0 0 0;">&copy; {$year}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Envía enlace de recuperación de contraseña
     * 
     * @param array $usuario Array con keys: correo, nombre, primer_apellido
     * @param string $tokenRecuperacion Token único para validar la solicitud
     * @param string $urlApp URL base de la aplicación (sin barra final)
     * @param int $expirationMinutes Minutos de validez del link
     * @param string|null $fullRecoveryUrl Si se envía, se usa como enlace final (debe incluir el token codificado).
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function enviarRecuperacionContrasena(
        array $usuario,
        string $tokenRecuperacion,
        string $urlApp = '',
        int $expirationMinutes = 60,
        ?string $fullRecoveryUrl = null
    ): array {
        try {
            $cfgErr = self::smtpConfigurationError();
            if ($cfgErr !== null) {
                return ['success' => false, 'message' => $cfgErr];
            }

            $mailer = self::getMailer();
            $mailer->clearAllRecipients();

            $nombreCompleto = trim($usuario['nombre'] . ' ' . $usuario['primer_apellido']);
            $correo = trim($usuario['correo']);

            if ($fullRecoveryUrl !== null && $fullRecoveryUrl !== '') {
                $urlRecuperacion = $fullRecoveryUrl;
            } else {
                $baseUrl = self::normalizeBaseUrl($urlApp);
                $urlRecuperacion = $baseUrl . '/admin/recuperar_contrasena.php?token=' . urlencode($tokenRecuperacion);
            }
            
            $mailer->addAddress($correo, $nombreCompleto);
            $mailer->Subject = 'Recuperación de contraseña - Sistema Joyería';
            
            $html = self::templateRecuperacionContrasena([
                'nombre' => $nombreCompleto,
                'url_recuperacion' => $urlRecuperacion,
                'expiration_minutes' => $expirationMinutes
            ]);
            
            $mailer->msgHTML($html);
            $mailer->AltBody = "Hola $nombreCompleto, solicitude recuperación de contraseña. Accede en: $urlRecuperacion (válido por $expirationMinutes minutos)";

            $result = $mailer->send();

            return [
                'success' => $result,
                'message' => 'Enlace de recuperación enviado al correo',
                'correo' => $correo
            ];
            
        } catch (Exception $e) {
            error_log("Error en MailService::enviarRecuperacionContrasena: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Envía notificación genérica
     * 
     * @param string $correo Correo del destinatario
     * @param string $asunto Asunto del correo
     * @param string $htmlContent Contenido HTML del correo
     * @param string $altText Texto alternativo (plain text)
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public static function enviarNotificacion(string $correo, string $asunto, string $htmlContent, string $altText = ''): array
    {
        return self::enviarConAdjuntos($correo, $asunto, $htmlContent, $altText, []);
    }

    /**
     * @param array<int, array{bytes: string, filename: string, mime?: string}> $adjuntos
     * @return array{success: bool, message: string, ok?: bool, correo?: string}
     */
    public static function enviarConAdjuntos(
        string $correo,
        string $asunto,
        string $htmlContent,
        string $altText = '',
        array $adjuntos = []
    ): array {
        try {
            $cfgErr = self::smtpConfigurationError();
            if ($cfgErr !== null) {
                return ['success' => false, 'message' => $cfgErr, 'ok' => false];
            }

            $mailer = self::getMailer();
            $mailer->clearAllRecipients();
            $mailer->clearAttachments();

            $mailer->addAddress($correo);
            $mailer->Subject = $asunto;
            $mailer->msgHTML($htmlContent);
            if ($altText !== '') {
                $mailer->AltBody = $altText;
            }

            foreach ($adjuntos as $adj) {
                if (!is_array($adj)) {
                    continue;
                }
                $bytes = $adj['bytes'] ?? '';
                $filename = trim((string) ($adj['filename'] ?? 'adjunto'));
                $mime = trim((string) ($adj['mime'] ?? 'application/octet-stream'));
                if ($bytes === '' || $filename === '') {
                    continue;
                }
                $mailer->addStringAttachment($bytes, $filename, 'base64', $mime);
            }

            $result = $mailer->send();

            return [
                'success' => $result,
                'ok' => $result,
                'message' => 'Notificacion enviada',
                'correo' => $correo,
            ];
        } catch (Exception $e) {
            error_log('Error en MailService::enviarConAdjuntos: ' . $e->getMessage());
            return [
                'success' => false,
                'ok' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Template HTML - Credenciales de Empleado
     */
    private static function templateCredencialesEmpleado(array $datos): string
    {
        $nombre = htmlspecialchars($datos['nombre']);
        $correo = htmlspecialchars($datos['correo']);
        $contrasena = htmlspecialchars($datos['contrasena']);
        $urlLogin = htmlspecialchars($datos['url_login']);
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .header { background-color: #1a1a1a; color: #f4d03f; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .credentials { background-color: #fff; border-left: 4px solid #f4d03f; padding: 15px; margin: 15px 0; }
        .field { margin: 10px 0; }
        .label { font-weight: bold; color: #1a1a1a; }
        .button { display: inline-block; background-color: #1a1a1a; color: #f4d03f; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 15px 0; font-weight: bold; }
        .footer { text-align: center; font-size: 12px; color: #666; padding: 20px; border-top: 1px solid #ddd; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>¡Bienvenido a Sistema Joyería!</h1>
        </div>
        <div class="content">
            <p>Hola <strong>$nombre</strong>,</p>
            
            <p>Tu cuenta de empleado ha sido creada exitosamente. A continuación encontrarás tus credenciales de acceso:</p>
            
            <div class="credentials">
                <div class="field">
                    <span class="label">Correo de acceso:</span><br>
                    <code>$correo</code>
                </div>
                <div class="field">
                    <span class="label">Contraseña temporal:</span><br>
                    <code>$contrasena</code>
                </div>
                <div class="field">
                    <span class="label">⚠️ IMPORTANTE:</span><br>
                    Guarda esta contraseña en un lugar seguro. Por motivos de seguridad, recomendamos cambiarla en tu primer acceso.
                </div>
            </div>
            
            <p>Accede al sistema haciendo clic aquí:</p>
            <div style="text-align: center;">
                <a href="$urlLogin" class="button">Acceder al Sistema</a>
            </div>
            
            <p><strong>En caso de problemas:</strong> Contacta con el área de administrativos para solicitar ayuda con tus credenciales.</p>
        </div>
        <div class="footer">
            <p>Este es un correo automático. No responda a este mensaje.</p>
            <p>&copy; 2026 Sistema Joyería. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private static function templateAccesoEmpleadoActualizado(array $datos): string
    {
        $nombre = htmlspecialchars((string) $datos['nombre'], ENT_QUOTES, 'UTF-8');
        $correo = htmlspecialchars((string) $datos['correo'], ENT_QUOTES, 'UTF-8');
        $urlLogin = htmlspecialchars((string) $datos['url_login'], ENT_QUOTES, 'UTF-8');
        $correoCambio = !empty($datos['correo_cambio']);
        $contrasena = $datos['contrasena'] ?? null;
        $bloquePass = '';
        if ($contrasena !== null && $contrasena !== '') {
            $passEsc = htmlspecialchars((string) $contrasena, ENT_QUOTES, 'UTF-8');
            $bloquePass = <<<HTML
                <div style="margin-top:10px;">
                    <span style="font-weight:bold;">Nueva contraseña:</span><br>
                    <code>$passEsc</code>
                </div>
HTML;
        }
        $intro = 'Se actualizaron tus datos para entrar al panel administrativo.';
        if ($correoCambio && $bloquePass !== '') {
            $intro = 'Tu correo de acceso y tu contraseña fueron actualizados.';
        } elseif ($correoCambio) {
            $intro = 'Tu correo de acceso fue actualizado. Usa el nuevo correo para iniciar sesión.';
        } elseif ($bloquePass !== '') {
            $intro = 'Tu contraseña de acceso fue actualizada.';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Acceso actualizado</title></head>
<body style="font-family:Arial,sans-serif;color:#333;">
    <div style="max-width:600px;margin:0 auto;padding:20px;border:1px solid #ddd;border-radius:8px;">
        <div style="background:#1a1a1a;color:#f4d03f;padding:16px;text-align:center;border-radius:8px 8px 0 0;">
            <h2 style="margin:0;">Acceso actualizado</h2>
        </div>
        <div style="padding:20px;background:#f9f9f9;">
            <p>Hola <strong>$nombre</strong>,</p>
            <p>$intro</p>
            <div style="background:#fff;border-left:4px solid #f4d03f;padding:15px;margin:15px 0;">
                <div><span style="font-weight:bold;">Correo de acceso:</span><br><code>$correo</code></div>
                $bloquePass
            </div>
            <p style="text-align:center;"><a href="$urlLogin" style="display:inline-block;background:#1a1a1a;color:#f4d03f;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;">Ir al panel</a></p>
        </div>
        <p style="text-align:center;font-size:12px;color:#666;">Correo automático — Platería El Ángel</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template HTML - Recuperación de Contraseña
     *
     * Diseñado para coincidir con la identidad visual de Platería El Ángel:
     * paleta blanco + dorado (#bfa14a) + heading oscuro (#171717),
     * tipografía sans-serif fallback compatible con clientes de correo,
     * estructura basada en tablas para máxima compatibilidad (Outlook, Gmail).
     */
    private static function templateRecuperacionContrasena(array $datos): string
    {
        $nombre = htmlspecialchars($datos['nombre']);
        $urlRecuperacion = htmlspecialchars($datos['url_recuperacion']);
        $minutes = intval($datos['expiration_minutes']);
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña</title>
</head>
<body style="margin:0; padding:0; background-color:#efeae3; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color:#5c5c5c;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#efeae3; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 12px 32px rgba(20,18,16,0.12);">
                    <tr>
                        <td style="padding:36px 40px 8px 40px; text-align:center;">
                            <p style="margin:0; font-size:11px; letter-spacing:5px; color:#bfa14a; text-transform:uppercase; font-weight:600;">
                                Platería El Ángel
                            </p>
                            <p style="margin:6px 0 0 0; font-size:11px; letter-spacing:3px; color:#9c8038; text-transform:uppercase;">
                                Gestión Administrativa
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:18px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="background-color:#bfa14a; width:64px; height:64px; border-radius:50%; color:#ffffff; font-size:26px; font-weight:bold; line-height:64px;">
                                        &#128274;
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 48px 0 48px; text-align:center;">
                            <h1 style="margin:0; font-size:26px; font-weight:600; color:#171717; letter-spacing:-0.01em;">
                                Recuperación de Contraseña
                            </h1>
                            <p style="margin:8px 0 0 0; font-size:14px; color:#8b837a; letter-spacing:0.04em;">
                                Recibimos una solicitud de cambio en tu cuenta
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 48px 0 48px; font-size:15px; line-height:1.65; color:#5c5c5c;">
                            <p style="margin:0 0 14px 0;">
                                Hola <strong style="color:#171717;">$nombre</strong>,
                            </p>
                            <p style="margin:0;">
                                Hemos recibido una solicitud para restablecer tu contraseña. Para continuar, haz clic en el botón inferior.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:28px 48px 8px 48px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="border-radius:999px; background-color:#171717;">
                                        <a href="$urlRecuperacion"
                                           style="display:inline-block; padding:14px 38px; font-size:13px; font-weight:600; color:#ffffff; text-decoration:none; letter-spacing:0.18em; text-transform:uppercase; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; border-radius:999px;">
                                            Restablecer Contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 48px 0 48px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#faf8f3; border-left:3px solid #bfa14a; border-radius:8px;">
                                <tr>
                                    <td style="padding:14px 18px; font-size:13px; line-height:1.55; color:#7a6f54;">
                                        <strong style="color:#5e5238; letter-spacing:0.04em;">Aviso de seguridad</strong><br>
                                        Este enlace es válido por <strong>$minutes minutos</strong> y solo puede usarse una vez. Si tú no realizaste esta solicitud, ignora este correo y tu contraseña permanecerá sin cambios.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 48px 0 48px; font-size:13px; line-height:1.6; color:#8b837a;">
                            <p style="margin:0 0 6px 0; color:#171717; font-weight:600; font-size:12px; letter-spacing:0.16em; text-transform:uppercase;">
                                ¿El botón no funciona?
                            </p>
                            <p style="margin:0 0 8px 0;">
                                Copia y pega esta dirección en tu navegador:
                            </p>
                            <p style="margin:0; padding:10px 14px; background-color:#f6f4f1; border:1px solid #e2e2e2; border-radius:8px; font-family:Consolas, 'Courier New', monospace; font-size:12px; color:#5c5c5c; word-break:break-all;">
                                $urlRecuperacion
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 48px 0 48px;">
                            <hr style="border:none; border-top:1px solid #e2e2e2; margin:0;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 48px 36px 48px; text-align:center; font-size:11px; color:#a39b8e; line-height:1.6; letter-spacing:0.04em;">
                            <p style="margin:0 0 4px 0;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                            <p style="margin:0;">
                                &copy; $year Platería El Ángel &middot; Todos los derechos reservados
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:18px 0 0 0; font-size:11px; color:#a39b8e; letter-spacing:0.18em; text-transform:uppercase;">
                    Artesanía y elegancia en plata
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private static function templateVerificacionCorreoTienda(array $datos): string
    {
        $nombre = htmlspecialchars((string) $datos['nombre'], ENT_QUOTES, 'UTF-8');
        $urlVerificacion = htmlspecialchars((string) $datos['url_verificacion'], ENT_QUOTES, 'UTF-8');
        $hours = max(1, (int) ($datos['expiration_hours'] ?? 24));
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirma tu correo</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f4f1; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; color:#5c5c5c;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f6f4f1; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="620" style="max-width:620px; width:100%; background-color:#ffffff; border:1px solid #e2e2e2; border-radius:16px; overflow:hidden; box-shadow:0 18px 46px rgba(23,23,23,0.10);">
                    <tr>
                        <td style="background:linear-gradient(158deg, #12100e 0%, #29231f 46%, #3f3933 100%); padding:42px 42px 36px 42px; text-align:center;">
                            <p style="margin:0 0 10px 0; font-size:11px; letter-spacing:0.35em; color:rgba(255,255,255,0.62); text-transform:uppercase;">
                                Platería El Ángel
                            </p>
                            <h1 style="margin:0; font-size:26px; font-weight:600; color:#ffffff;">Confirma tu correo</h1>
                            <p style="margin:12px 0 0 0; font-size:14px; color:rgba(255,255,255,0.78);">Un paso mas para activar tu cuenta en la tienda en linea</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 46px 0 46px; font-size:15px; line-height:1.65;">
                            <p style="margin:0 0 12px 0;">Hola <strong style="color:#171717;">{$nombre}</strong>,</p>
                            <p style="margin:0;">Gracias por registrarte. Haz clic en el boton para confirmar que este correo es tuyo y poder iniciar sesion.</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:26px 46px 12px 46px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="border-radius:999px; background-color:#171717;">
                                        <a href="{$urlVerificacion}"
                                           style="display:inline-block; padding:14px 38px; font-size:13px; font-weight:600; color:#ffffff; text-decoration:none; letter-spacing:0.18em; text-transform:uppercase; border-radius:999px;">
                                            Confirmar correo
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 46px 0 46px; font-size:13px; line-height:1.6; color:#8b837a; text-align:center;">
                            <p style="margin:0;">El enlace es valido por {$hours} horas. Si no creaste esta cuenta, ignora este mensaje.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 46px 32px 46px; font-size:12px; line-height:1.5; color:#a39a90; text-align:center; border-top:1px solid #ece8e1;">
                            <p style="margin:0;">&copy; {$year} Platería El Ángel</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Template HTML - Bienvenida de Cliente
     *
     * Inspirado en el CSS público: fondo claro, textos sobrios, acento dorado,
     * encabezados elegantes y botones redondeados compatibles con correo.
     */
    private static function templateBienvenidaCliente(array $datos): string
    {
        $nombre = htmlspecialchars((string) $datos['nombre'], ENT_QUOTES, 'UTF-8');
        $correo = htmlspecialchars((string) $datos['correo'], ENT_QUOTES, 'UTF-8');
        $urlCuenta = htmlspecialchars((string) $datos['url_cuenta'], ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Platería El Ángel</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f4f1; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; color:#5c5c5c;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f6f4f1; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="620" style="max-width:620px; width:100%; background-color:#ffffff; border:1px solid #e2e2e2; border-radius:16px; overflow:hidden; box-shadow:0 18px 46px rgba(23,23,23,0.10);">
                    <tr>
                        <td style="background:linear-gradient(158deg, #12100e 0%, #29231f 46%, #3f3933 100%); padding:42px 42px 36px 42px; text-align:center;">
                            <p style="margin:0 0 10px 0; font-size:11px; letter-spacing:0.35em; color:rgba(255,255,255,0.62); text-transform:uppercase;">
                                Platería El Ángel
                            </p>
                            <h1 style="margin:0; font-size:30px; line-height:1.18; font-weight:600; color:#ffffff; letter-spacing:-0.02em;">
                                Bienvenido a nuestra joyería
                            </h1>
                            <p style="margin:14px 0 0 0; font-size:15px; line-height:1.6; color:rgba(255,255,255,0.78);">
                                Gracias por crear tu cuenta. Ahora puedes explorar piezas seleccionadas y gestionar tus compras desde tu perfil.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:34px 46px 0 46px; font-size:15px; line-height:1.7; color:#5c5c5c;">
                            <p style="margin:0 0 14px 0;">
                                Hola <strong style="color:#171717;">$nombre</strong>,
                            </p>
                            <p style="margin:0;">
                                Tu cuenta fue creada correctamente con el correo <strong style="color:#171717;">$correo</strong>. Hemos preparado tu espacio para que puedas revisar tus datos, pedidos y novedades de la tienda.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:30px 46px 12px 46px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="border-radius:999px; background-color:#171717;">
                                        <a href="$urlCuenta"
                                           style="display:inline-block; padding:14px 36px; color:#ffffff; text-decoration:none; border-radius:999px; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase;">
                                            Entrar a mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 46px 0 46px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#faf8f3; border-left:3px solid #bfa14a; border-radius:10px;">
                                <tr>
                                    <td style="padding:18px 20px; font-size:13px; line-height:1.6; color:#7a6f54;">
                                        <strong style="display:block; margin-bottom:5px; color:#5e5238; letter-spacing:0.12em; text-transform:uppercase; font-size:11px;">
                                            Tu acceso
                                        </strong>
                                        Usa el correo registrado y tu contraseña para iniciar sesión cuando quieras volver a la tienda.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 46px 0 46px; text-align:center;">
                            <p style="margin:0; font-size:13px; line-height:1.7; color:#8b837a;">
                                Si el botón no funciona, copia y pega esta dirección en tu navegador:
                            </p>
                            <p style="margin:10px 0 0 0; padding:10px 14px; background-color:#f6f4f1; border:1px solid #e2e2e2; border-radius:8px; font-family:Consolas, 'Courier New', monospace; font-size:12px; color:#5c5c5c; word-break:break-all;">
                                $urlCuenta
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 46px 0 46px;">
                            <hr style="border:none; border-top:1px solid #e2e2e2; margin:0;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 46px 38px 46px; text-align:center; font-size:11px; color:#a39b8e; line-height:1.6; letter-spacing:0.04em;">
                            <p style="margin:0 0 4px 0;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                            <p style="margin:0;">
                                &copy; $year Platería El Ángel &middot; Todos los derechos reservados
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:18px 0 0 0; font-size:11px; color:#a39b8e; letter-spacing:0.18em; text-transform:uppercase;">
                    Artesanía y elegancia en plata
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
