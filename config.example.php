<?php
/**
 * Plantilla de configuración por entorno (desarrollo / producción).
 *
 * Checklist deploy (guía completa: deploy/DEPLOY.md):
 * - VPS: copia a config.php, composer install, importar dump o migraciones, Nginx + TLS.
 * - PC caja Windows: agentes print-agent / print-agent-etiquetas (no usa este archivo).
 * - config.php está en .gitignore y no debe subirse al repositorio.
 * - Composer: en el servidor ejecuta `composer install --no-dev --optimize-autoloader`.
 * - Correo: rellena las constantes JOYERIA_SMTP_* abajo o configura las mismas claves en la tabla configuracion_general del panel.
 * - No subas logs (*.log). Los PDF de contratos en uploads/contratos/ y los scripts admin/test_*.php y de depuración (debug_*, diagnostico_pdf, *probe*) están en .gitignore: no van al repo; en el servidor crea la carpeta uploads/contratos/ si hace falta.
 * - Scripts auxiliares: copia api_empleados.example.py a api_empleados.py (este último está ignorado por Git) y pon ahí PHPSESSID y URL solo para tu máquina.
 *
 * Uso:
 *   cp config.example.php config.php   (Linux/macOS)
 *   copy config.example.php config.php   (Windows)
 */
define('DBDRIVER', 'mysql');
define('DBHOST', 'localhost');
define('DBUSER', 'cambiar_usuario_bd');
define('DBPASSWORD', 'cambiar_contraseña_bd');
define('DBPORT', '3306');
define('DBNAME', 'joyeria');

/*
 * Correo — PHPMailer (admin/includes/MailService.php).
 * Usa 'ssl' para puerto 465 (SMTPS) o 'tls' para 587 (STARTTLS).
 */
define('JOYERIA_SMTP_HOST', 'smtp.gmail.com');
define('JOYERIA_SMTP_PORT', 465);
define('JOYERIA_SMTP_SECURE', 'ssl');
define('JOYERIA_SMTP_USERNAME', 'cambiar_usuario_smtp');
define('JOYERIA_SMTP_PASSWORD', 'cambiar_contraseña_aplicacion');
define('JOYERIA_SMTP_FROM_EMAIL', 'no-reply@ejemplo.com');
define('JOYERIA_SMTP_FROM_NAME', 'Platería El Ángel');
define('JOYERIA_SMTP_DEBUG', 0);

/** URL publica del sitio (sin /admin al final). Usada en enlaces de correos. */
define('JOYERIA_APP_URL', 'https://plateria-el-angel.shop');

/*
 * WhatsApp Cloud API de Meta (admin/includes/WhatsAppService.php).
 * El TOKEN va aqui (es secreto y puede superar los 255 chars de configuracion_general).
 * El resto de claves (phone_number_id, version, plantillas, lada, idioma) se ajustan
 * en el panel: Configuracion del sistema -> Mensajeria.
 * Las plantillas deben estar APROBADAS en Meta Business Manager.
 */
define('JOYERIA_WHATSAPP_TOKEN', 'cambiar_token_permanente_de_meta');
// Opcionales (tambien configurables desde el panel):
// define('JOYERIA_WHATSAPP_PHONE_NUMBER_ID', '000000000000000');
// define('JOYERIA_WHATSAPP_API_VERSION', 'v20.0');

/**
 * Zona horaria del negocio (fechas por defecto, cierre de caja, reportes).
 * En VPS suele venir en UTC; use America/Mexico_City para Mexico.
 */
define('JOYERIA_TIMEZONE', 'America/Mexico_City');

/**
 * Duracion de la sesion (admin y tienda), en segundos.
 * 2592000 = 30 dias. La cookie se renueva en cada visita (no caduca si usan el sistema).
 */
define('JOYERIA_SESSION_LIFETIME', 2592000);

/*
 * Facturacion CFDI 4.0 — Facturama PAC (admin/includes/FacturamaClient.php).
 * Usuario y contraseña de la cuenta Facturama (sandbox o produccion).
 * Los datos del emisor (RFC, regimen, CP, serie, folio) se configuran en el panel:
 * Configuracion del sistema -> Facturacion.
 */
define('JOYERIA_FACTURAMA_USUARIO', 'cambiar_usuario_facturama');
define('JOYERIA_FACTURAMA_PASSWORD', 'cambiar_contraseña_facturama');
/** true = sandbox (apisandbox.facturama.mx), false = produccion */
define('JOYERIA_FACTURAMA_SANDBOX', true);

/*
 * Cloudflare Turnstile — proteccion anti-bot en login/registro de tienda.
 * Dashboard: https://dash.cloudflare.com/ -> Turnstile (no requiere DNS activo en el dominio).
 * Claves de prueba Cloudflare (siempre pasan):
 *   Site:   1x00000000000000000000AA
 *   Secret: 1x0000000000000000000000000000000AA
 */
define('JOYERIA_TURNSTILE_ENABLED', false);
define('JOYERIA_TURNSTILE_SITE_KEY', 'cambiar_site_key_turnstile');
define('JOYERIA_TURNSTILE_SECRET_KEY', 'cambiar_secret_key_turnstile');
/*
 * PRODUCCION: pon esto en true junto con ENABLED=true y claves reales.
 * Con REQUERIDO=true, si Turnstile no esta correctamente configurado, las
 * peticiones de login/registro/recuperacion se RECHAZAN (no "fail-open").
 * Dejalo en false solo en desarrollo local.
 */
define('JOYERIA_TURNSTILE_REQUERIDO', false);
