<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/joyeria_branding.php';
require_once __DIR__ . '/../admin/includes/tienda_auth.php';
require_once __DIR__ . '/../admin/models/cliente.php';
require_once __DIR__ . '/../admin/models/sub_familia.php';
require_once __DIR__ . '/../sistema.class.php';

if (!tienda_is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

$tiendaUser = tienda_auth_user();
$idCliente = isset($tiendaUser['id_cliente']) ? (int) $tiendaUser['id_cliente'] : 0;
$idUsuario = isset($tiendaUser['id_usuario']) ? (int) $tiendaUser['id_usuario'] : 0;
if ($idCliente <= 0 || $idUsuario <= 0) {
    header('Location: logout.php');
    exit;
}

$clienteModel = new Cliente();
$cliente = $clienteModel->leerUno($idCliente);
if (!is_array($cliente) || empty($cliente)) {
    header('Location: logout.php');
    exit;
}

$flashOk = null;
$flashErr = null;

function joyeria_post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function joyeria_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf_token'];
}

function joyeria_csrf_check(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sent = isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : '';
    $ok = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $sent);
    return (bool) $ok;
}

function joyeria_usuario_hash_por_id(int $idUsuario): ?string
{
    $sistema = new Sistema();
    $stmt = $sistema->getDb()->prepare('SELECT contrasena FROM usuarios WHERE id_usuario = :id AND activo = 1 LIMIT 1');
    $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !isset($row['contrasena'])) {
        return null;
    }
    $hash = (string) $row['contrasena'];
    return $hash !== '' ? $hash : null;
}

function joyeria_cliente_payload_base(array $cliente): array
{
    return [
        'nombre' => (string) ($cliente['nombre'] ?? ''),
        'primer_apellido' => (string) ($cliente['primer_apellido'] ?? ''),
        'segundo_apellido' => isset($cliente['segundo_apellido']) ? (string) $cliente['segundo_apellido'] : null,
        'correo' => (string) ($cliente['correo'] ?? ''),
        'telefono' => (string) ($cliente['telefono'] ?? ''),
        'descuento_porcentaje' => $cliente['descuento_porcentaje'] ?? null,
        'omitir_actualizacion_direccion' => '1',
    ];
}

function joyeria_actualizar_sesion_cliente(array $clienteActualizado): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!defined('JOYERIA_TIENDA_SESSION_KEY')) {
        return;
    }
    if (!isset($_SESSION[JOYERIA_TIENDA_SESSION_KEY]) || !is_array($_SESSION[JOYERIA_TIENDA_SESSION_KEY])) {
        return;
    }

    $nombre = trim((string) ($clienteActualizado['nombre'] ?? ''));
    $pa = trim((string) ($clienteActualizado['primer_apellido'] ?? ''));
    $sa = trim((string) ($clienteActualizado['segundo_apellido'] ?? ''));
    $full = trim($nombre . ' ' . $pa . ' ' . $sa);

    $_SESSION[JOYERIA_TIENDA_SESSION_KEY]['nombre'] = $nombre;
    $_SESSION[JOYERIA_TIENDA_SESSION_KEY]['nombre_completo'] = $full !== '' ? $full : ($_SESSION[JOYERIA_TIENDA_SESSION_KEY]['correo'] ?? '');
    $_SESSION[JOYERIA_TIENDA_SESSION_KEY]['correo'] = (string) ($clienteActualizado['correo'] ?? ($_SESSION[JOYERIA_TIENDA_SESSION_KEY]['correo'] ?? ''));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!joyeria_csrf_check()) {
        $flashErr = 'Sesión inválida. Recarga la página e intenta de nuevo.';
    } else {
        $accion = joyeria_post('accion', '');
        try {
            if ($accion === 'perfil') {
                $payload = joyeria_cliente_payload_base($cliente);
                $payload['nombre'] = joyeria_post('nombre');
                $payload['primer_apellido'] = joyeria_post('primer_apellido');
                $payload['segundo_apellido'] = joyeria_post('segundo_apellido', '');
                if ($payload['segundo_apellido'] === '') {
                    $payload['segundo_apellido'] = null;
                }
                $payload['correo'] = joyeria_post('correo');
                $payload['telefono'] = joyeria_post('telefono');

                $clienteModel->actualizar($idCliente, $payload);
                $cliente = $clienteModel->leerUno($idCliente) ?: $cliente;
                joyeria_actualizar_sesion_cliente(is_array($cliente) ? $cliente : []);
                $flashOk = 'Tus datos se actualizaron correctamente.';
            } elseif ($accion === 'password') {
                $actual = joyeria_post('password_actual');
                $nueva = joyeria_post('password_nueva');
                $confirm = joyeria_post('password_confirm');
                if ($nueva === '' || $confirm === '') {
                    throw new InvalidArgumentException('La nueva contraseña y su confirmación son obligatorias.');
                }
                if ($nueva !== $confirm) {
                    throw new InvalidArgumentException('Las contraseñas no coinciden.');
                }
                if (mb_strlen($nueva) < 8) {
                    throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
                }

                $hash = joyeria_usuario_hash_por_id($idUsuario);
                if ($hash === null || !password_verify($actual, $hash)) {
                    throw new InvalidArgumentException('La contraseña actual no es correcta.');
                }

                $payload = joyeria_cliente_payload_base($cliente);
                $payload['contrasena'] = $nueva; // `Cliente::actualizar()` la hashea
                $clienteModel->actualizar($idCliente, $payload);
                $flashOk = 'Tu contraseña se actualizó correctamente.';
            } else {
                $flashErr = 'Acción no válida.';
            }
        } catch (Throwable $e) {
            $flashErr = $e->getMessage();
        }
    }
}

$nombreMostrar = isset($tiendaUser['nombre_completo']) ? trim((string) $tiendaUser['nombre_completo']) : '';
if ($nombreMostrar === '' && !empty($tiendaUser['correo'])) {
    $nombreMostrar = (string) $tiendaUser['correo'];
}

$csrf = joyeria_csrf_token();

$subfamiliaModel = new SubFamilia();
$subfamiliasActivas = $subfamiliaModel->leer();
$menuCatalogoFamilias = [];
foreach ($subfamiliasActivas as $subRow) {
    if (!is_array($subRow)) {
        continue;
    }
    $idFamRow = (int) ($subRow['id_familia_FK'] ?? 0);
    $idSubRow = (int) ($subRow['id_sub_familia'] ?? 0);
    $nomFamRow = preg_replace('/\s+/', ' ', trim((string) ($subRow['nom_familia'] ?? ''))) ?? '';
    $nomSubRow = preg_replace('/\s+/', ' ', trim((string) ($subRow['nom_sub_familia'] ?? ''))) ?? '';
    if ($idFamRow <= 0 || $idSubRow <= 0 || $nomFamRow === '' || $nomSubRow === '') {
        continue;
    }
    if (!isset($menuCatalogoFamilias[$idFamRow])) {
        $menuCatalogoFamilias[$idFamRow] = [
            'nombre' => $nomFamRow,
            'subfamilias' => [],
            '_sub_keys' => [],
        ];
    }
    $subKey = mb_strtolower(preg_replace('/\s+/', ' ', $nomSubRow) ?? $nomSubRow, 'UTF-8');
    if (isset($menuCatalogoFamilias[$idFamRow]['_sub_keys'][$subKey])) {
        continue;
    }
    $menuCatalogoFamilias[$idFamRow]['_sub_keys'][$subKey] = true;
    $menuCatalogoFamilias[$idFamRow]['subfamilias'][$idSubRow] = $nomSubRow;
}
uasort($menuCatalogoFamilias, static function (array $a, array $b): int {
    return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});
foreach ($menuCatalogoFamilias as $famKey => $famGroup) {
    if ($famGroup['subfamilias'] === []) {
        unset($menuCatalogoFamilias[$famKey]);
        continue;
    }
    asort($menuCatalogoFamilias[$famKey]['subfamilias'], SORT_NATURAL | SORT_FLAG_CASE);
    unset($menuCatalogoFamilias[$famKey]['_sub_keys']);
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Mi cuenta'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
</head>
<body>

    <div class="usuario-zona-bar border-bottom py-2 px-3 small d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: #fafafa;">
        <span class="text-muted">Hola, <strong class="text-dark"><?php echo htmlspecialchars($nombreMostrar, ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <span class="d-flex flex-wrap gap-3">
            <a href="logout.php" class="link-dark text-decoration-none">Cerrar sesión</a>
        </span>
    </div>

    <header class="header">
        <div class="logo">
            <h1><?php echo htmlspecialchars(joyeria_marca_nombre(), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(joyeria_marca_tagline(), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <nav class="nav">
            <ul>
                <li class="nav-item-dropdown">
                    <a href="../catalogo.php" class="nav-link-dropdown">Catálogo</a>
                    <?php if ($menuCatalogoFamilias !== []): ?>
                        <div class="nav-dropdown-panel" aria-label="Familias y subfamilias del catálogo">
                            <?php foreach ($menuCatalogoFamilias as $idFamMenu => $grupoMenu): ?>
                                <section class="nav-dropdown-family">
                                    <h3><?php echo htmlspecialchars((string) ($grupoMenu['nombre'] ?? 'Familia'), ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <ul>
                                        <?php foreach (($grupoMenu['subfamilias'] ?? []) as $idSubMenu => $nomSubMenu): ?>
                                            <li>
                                                <a href="../catalogo.php?fam=<?php echo (int) $idFamMenu; ?>&sub=<?php echo (int) $idSubMenu; ?>">
                                                    <?php echo htmlspecialchars((string) $nomSubMenu, ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
                <li><a href="compras.php">Compras</a></li>
            </ul>
        </nav>

        <div class="header-icons">
            <a href="index.php#buscadorPiezas" class="icon" aria-label="Buscar" title="Buscar">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="16.65" y1="16.65" x2="21" y2="21"></line>
                </svg>
            </a>
            <a href="cuenta.php" class="icon" aria-label="Mi cuenta" title="Mi cuenta">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 20c0-4 4-6 8-6s8 2 8 6"></path>
                </svg>
            </a>
            <a href="carrito.php" class="icon cart" aria-label="Carrito" title="Carrito">
                <svg viewBox="0 0 24 24">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.6 13h10.8l2-8H6"></path>
                </svg>
                <span class="cart-count d-none">0</span>
            </a>
        </div>
    </header>

    <main class="container py-4" style="max-width: 980px;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <p class="catalogo-page-kicker mb-1">Mi cuenta</p>
                <h2 class="catalogo-page-title mb-0">Perfil y seguridad</h2>
            </div>
            <a class="btn btn-outline-dark rounded-pill" href="index.php">Volver</a>
        </div>

        <?php if ($flashOk): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="h5 mb-3">Datos personales</h3>
                        <form method="post" autocomplete="on">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="perfil">

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Nombre</label>
                                    <input class="form-control" name="nombre" maxlength="50" required value="<?php echo htmlspecialchars((string) ($cliente['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Primer apellido</label>
                                    <input class="form-control" name="primer_apellido" maxlength="25" required value="<?php echo htmlspecialchars((string) ($cliente['primer_apellido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Segundo apellido (opcional)</label>
                                    <input class="form-control" name="segundo_apellido" maxlength="25" value="<?php echo htmlspecialchars((string) ($cliente['segundo_apellido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input class="form-control" name="telefono" maxlength="15" required value="<?php echo htmlspecialchars((string) ($cliente['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Correo</label>
                                    <input class="form-control" type="email" name="correo" maxlength="80" required value="<?php echo htmlspecialchars((string) ($cliente['correo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-dark rounded-pill px-4" type="submit">Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="h5 mb-3">Cambiar contraseña</h3>
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="password">

                            <div class="mb-3">
                                <label class="form-label">Contraseña actual</label>
                                <input class="form-control" type="password" name="password_actual" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nueva contraseña</label>
                                <input class="form-control" type="password" name="password_nueva" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmar nueva contraseña</label>
                                <input class="form-control" type="password" name="password_confirm" minlength="8" required>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button class="btn btn-outline-dark rounded-pill px-4" type="submit">Actualizar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/site-nav.js"></script>
    <script src="../js/tienda-carrito.js"></script>
</body>
</html>

