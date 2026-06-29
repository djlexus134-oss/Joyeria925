<?php

require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/apartado_gestion.php';
require_once __DIR__ . '/models/ventas.php';
require_once __DIR__ . '/models/configuracion_general.php';

$accion = isset($_GET['accion']) ? htmlspecialchars((string) $_GET['accion']) : 'leer';

$app = new ApartadoGestion();
$ventasApp = new Ventas();

$usuarioSesion = function_exists('auth_user') ? auth_user() : null;
$idUsuarioSesion = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
$idEmpleadoSesion = $idUsuarioSesion > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuarioSesion) : null;

$formasPago = $app->formasPagoAbono($app->getDb());
$idFormaPagoDefault = (new ConfiguracionGeneral())->resolverIdFormaPagoDefault();
$catalogoClientes = $ventasApp->obtenerCatalogos()['clientes'] ?? [];
$lista = $app->listarApartados(150, 'activo');
$idApartadoUrl = isset($_GET['id_apartado']) ? (int) $_GET['id_apartado'] : 0;

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2><i class="bi bi-journal-bookmark"></i> Apartados: consulta y abonos</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'leer':
        default:
            /** @var int $idApartadoUrl */
            require __DIR__ . '/views/apartados_consulta/index.php';
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
