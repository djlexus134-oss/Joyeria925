#!/usr/bin/env php
<?php
/**
 * Prueba envío SMTP. Uso en VPS:
 *   php /var/www/joyeria/deploy/scripts/test-mail.php tu@correo.com
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!is_file($root . '/config.php')) {
    fwrite(STDERR, "Falta config.php en $root\n");
    exit(1);
}

require_once $root . '/vendor/autoload.php';
require_once $root . '/config.php';
require_once $root . '/admin/includes/MailService.php';

$destino = $argv[1] ?? '';
if ($destino === '') {
    fwrite(STDERR, "Uso: php deploy/scripts/test-mail.php correo@destino.com\n");
    exit(1);
}

$result = MailService::enviarPrueba($destino);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($result['success'] ? 0 : 1);
