#!/usr/bin/env php
<?php
/**
 * Diagnostico de login (solo CLI en servidor). No expone la contrasena.
 * Uso: php deploy/scripts/verify-user-login.php correo@ejemplo.com [contrasena]
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/config.php';
require_once $root . '/admin/includes/auth.php';
require_once $root . '/admin/includes/tienda_auth.php';

$correo = isset($argv[1]) ? trim((string) $argv[1]) : '';
$pass = $argv[2] ?? null;

if ($correo === '') {
    fwrite(STDERR, "Uso: php deploy/scripts/verify-user-login.php correo@ejemplo.com [contrasena]\n");
    exit(1);
}

$svc = auth_service();
$user = $svc->findUserByEmail($correo);

if ($user === null) {
    echo "Usuario NO encontrado por correo.\n";
    exit(1);
}

echo "id_usuario: " . $user['id_usuario'] . "\n";
echo "activo: " . $user['activo'] . "\n";
$hash = (string) $user['contrasena'];
echo "hash_prefix: " . substr($hash, 0, 7) . " (bcrypt debe ser \$2y\$...)\n";

$accesos = $svc->getRolesAndPermissions((int) $user['id_usuario']);
echo "roles: " . implode(', ', $accesos['roles']) . "\n";
echo "permisos_count: " . count($accesos['permissions']) . "\n";
echo "can_admin_panel: " . ($svc->canAccessAdminPanel((int) $user['id_usuario']) ? 'si' : 'no') . "\n";

$db = $svc->getDb();
$stmt = $db->prepare(
    "SELECT c.id_cliente FROM clientes c
     INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
     WHERE LOWER(TRIM(u.correo)) = :correo AND c.activo = 1 AND u.activo = 1 LIMIT 1"
);
$stmt->bindValue(':correo', mb_strtolower($correo), PDO::PARAM_STR);
$stmt->execute();
$cli = $stmt->fetch(PDO::FETCH_ASSOC);
echo "tambien_es_cliente_activo: " . ($cli ? 'si (id_cliente ' . $cli['id_cliente'] . ')' : 'no') . "\n";

if ($pass !== null) {
    $ok = password_verify($pass, $hash);
    echo "password_verify: " . ($ok ? 'OK' : 'FALLO') . "\n";
    if (!$ok && strlen($hash) === 32 && ctype_xdigit($hash)) {
        echo "nota: hash parece MD5 legacy, hay que restablecer contrasena.\n";
    }
}

exit(0);
