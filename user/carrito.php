<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/joyeria_branding.php';
require_once __DIR__ . '/../admin/includes/tienda_auth.php';
require_once __DIR__ . '/../admin/models/carrito.php';
require_once __DIR__ . '/../admin/models/apartado_gestion.php';
require_once __DIR__ . '/../admin/includes/DescuentoTiendaService.php';
require_once __DIR__ . '/../includes/joyeria_imagen_publica.php';
require_once __DIR__ . '/../includes/catalogo_variantes_publico.php';

if (!tienda_is_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$tiendaUser = tienda_auth_user();
$idCliente = (int) ($tiendaUser['id_cliente'] ?? 0);
$nombreMostrar = trim((string) ($tiendaUser['nombre_completo'] ?? $tiendaUser['nombre'] ?? ''));

$carrito = new Carrito();
$items = $carrito->listar($idCliente);
$resumen = $carrito->calcularResumen($items);
$saldoCredito = (float) (new ApartadoGestion())->totalCreditoDisponibleCliente($idCliente);
$totalCarrito = (float) ($resumen['total'] ?? 0);
$subtotalLista = (float) ($resumen['subtotal_lista'] ?? $totalCarrito);
$totalDescuentos = (float) ($resumen['total_descuentos'] ?? 0);

$svcMayoreo = new DescuentoTiendaService();
$cfgMayoreo = $svcMayoreo->obtenerConfigMayoreo();
$pctClienteFicha = (float) ($svcMayoreo->obtenerDescuentoCliente($idCliente) ?? 0);
$umbralMayoreo = (float) ($cfgMayoreo['umbral'] ?? 0);
$pctMayoreoCfg = (float) ($cfgMayoreo['porcentaje'] ?? 0);
$calificaMayoreoTicket = $umbralMayoreo > 0 && $subtotalLista + 0.0001 >= $umbralMayoreo;
$progresoMayoreoPct = $umbralMayoreo > 0
    ? min(100.0, round(($subtotalLista / $umbralMayoreo) * 100, 1))
    : 0.0;
$faltaMayoreo = max(0.0, $umbralMayoreo - $subtotalLista);

function joyeria_carrito_img(?string $url): string
{
    $u = trim((string) $url);
    if ($u === '') {
        return '';
    }
    if (preg_match('~^(https?:)?//~i', $u) === 1 || str_starts_with($u, '/')) {
        return $u;
    }
    return '../' . $u;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Carrito'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="usuario-zona-bar border-bottom py-2 px-3 small d-flex flex-wrap justify-content-between align-items-center gap-2" style="background:#fafafa;">
    <span class="text-muted">Hola, <strong class="text-dark"><?php echo htmlspecialchars($nombreMostrar, ENT_QUOTES, 'UTF-8'); ?></strong></span>
    <span class="d-flex flex-wrap gap-3">
        <a href="index.php" class="link-dark text-decoration-none">Catálogo</a>
        <a href="compras.php" class="link-dark text-decoration-none">Mis compras</a>
        <a href="logout.php" class="link-dark text-decoration-none">Cerrar sesión</a>
    </span>
</div>

<div class="container py-4" style="max-width:960px;">
    <h2 class="mb-3"><i class="bi bi-bag" aria-hidden="true"></i> Tu carrito</h2>

    <div class="alert alert-warning border-warning d-flex align-items-start gap-2" role="note">
        <i class="bi bi-shop fs-4" aria-hidden="true"></i>
        <div>
            <strong>Entrega exclusivamente en tienda.</strong>
            Al confirmar tu pago, cada pieza queda apartada en la sucursal correspondiente y la podrás recoger con
            una identificación oficial y tu número de orden. <strong>No realizamos envíos a domicilio.</strong>
        </div>
    </div>

    <?php if ($items === []): ?>
        <div class="text-center py-5">
            <p class="lead text-muted">Tu carrito está vacío.</p>
            <a href="index.php#catalogo" class="btn btn-dark">Explorar el catálogo</a>
        </div>
    <?php else: ?>

        <?php if ($resumen['multi_tienda']): ?>
            <div class="alert alert-info small" role="note">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                Tu carrito contiene piezas de <strong><?php echo (int) count($resumen['tiendas']); ?> sucursales</strong>.
                Cada pieza se recoge en la sucursal donde se encuentra.
            </div>
        <?php endif; ?>

        <?php if ($umbralMayoreo > 0 && $pctMayoreoCfg > 0): ?>
        <div class="card mb-3 carrito-mayoreo-card">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <strong><i class="bi bi-stars" aria-hidden="true"></i> Descuento mayoreo</strong>
                        <div class="small text-muted">
                            Compra joyas por al menos $<?php echo number_format($umbralMayoreo, 2, '.', ','); ?> MXN (precio lista)
                            y obtén <?php echo number_format($pctMayoreoCfg, 0); ?>% en joyas al pagar.
                        </div>
                    </div>
                    <?php if ($pctClienteFicha > 0): ?>
                        <span class="badge text-bg-secondary">Tu ficha: <?php echo number_format($pctClienteFicha, 0); ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="carrito-mayoreo-progress" role="progressbar"
                     aria-valuenow="<?php echo htmlspecialchars(number_format($progresoMayoreoPct, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                     aria-valuemin="0" aria-valuemax="100"
                     aria-label="Progreso hacia descuento mayoreo">
                    <div class="carrito-mayoreo-progress-bar" style="width: <?php echo min(100, max(0, $progresoMayoreoPct)); ?>%;"></div>
                </div>
                <div class="small mt-2 <?php echo $calificaMayoreoTicket ? 'text-success' : 'text-muted'; ?>">
                    <?php if ($calificaMayoreoTicket): ?>
                        ¡Este pedido califica! Al confirmar el pago se aplicará el mejor descuento (hasta <?php echo number_format($pctMayoreoCfg, 0); ?>%)
                        y quedará guardado en tu cuenta.
                    <?php elseif ($faltaMayoreo > 0): ?>
                        Te faltan $<?php echo number_format($faltaMayoreo, 2, '.', ','); ?> MXN en joyas (precio lista) para activar el <?php echo number_format($pctMayoreoCfg, 0); ?>% en esta compra.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($resumen['tiendas'] as $grupoTienda): ?>
            <div class="card mb-3">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <strong>
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        Sucursal: <?php echo htmlspecialchars((string) $grupoTienda['nom_tienda'], ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                    <span class="text-muted small"><?php echo (int) count($grupoTienda['items']); ?> pieza(s)</span>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($grupoTienda['items'] as $it): ?>
                        <?php
                        $img = joyeria_carrito_img(joyeria_resolver_url_imagen((string) ($it['url_imagen'] ?? '')));
                        $desc = htmlspecialchars((string) ($it['desc_pieza'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $codigo = htmlspecialchars((string) ($it['codigo_auxiliar'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $precio = (float) $it['precio_unitario_snapshot'];
                        $precioLista = isset($it['precio_lista_snapshot']) && $it['precio_lista_snapshot'] !== null && $it['precio_lista_snapshot'] !== ''
                            ? (float) $it['precio_lista_snapshot']
                            : $precio;
                        $tienePromoLinea = $precioLista > $precio;
                        $promoNombre = trim((string) ($it['promocion_nombre'] ?? ''));
                        $textoVariante = joyeria_texto_variante_stock($it);
                        ?>
                        <li class="list-group-item d-flex gap-3 align-items-center">
                            <?php if ($img !== ''): ?>
                                <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $desc; ?>"
                                     style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #eee;">
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?php echo $desc; ?></div>
                                <div class="small text-muted">
                                    <?php echo htmlspecialchars((string) ($it['nom_metal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($textoVariante !== ''): ?>
                                        · <span class="text-dark"><?php echo htmlspecialchars($textoVariante, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($codigo !== ''): ?> · Código: <code><?php echo $codigo; ?></code><?php endif; ?>
                                </div>
                                <?php if ($tienePromoLinea && $promoNombre !== ''): ?>
                                    <div class="small promo-descuento-label"><?php echo htmlspecialchars($promoNombre, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <?php if ($tienePromoLinea): ?>
                                    <div class="precio-lista-tachado small">$<?php echo number_format($precioLista, 2, '.', ','); ?></div>
                                <?php endif; ?>
                                <div class="fw-bold <?php echo $tienePromoLinea ? 'precio-promo' : ''; ?>">$<?php echo number_format($precio, 2, '.', ','); ?></div>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-eliminar-carrito"
                                        data-id-carrito-item="<?php echo (int) $it['id_carrito_item']; ?>"
                                        aria-label="Eliminar del carrito">
                                    <i class="bi bi-trash" aria-hidden="true"></i> Quitar
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <div class="card-body">
                <?php if ($totalDescuentos > 0): ?>
                <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                    <span>Subtotal (precio lista)</span>
                    <span>$<?php echo number_format($subtotalLista, 2, '.', ','); ?> MXN</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 small carrito-descuentos-aplicados">
                    <span>Descuentos aplicados</span>
                    <span>-$<?php echo number_format($totalDescuentos, 2, '.', ','); ?> MXN</span>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fs-5">Subtotal</span>
                    <strong class="fs-5">$<?php echo number_format($totalCarrito, 2, '.', ','); ?> MXN</strong>
                </div>

                <?php if ($saldoCredito > 0): ?>
                <div class="alert alert-success py-2 px-3 small mb-3" role="note">
                    <i class="bi bi-wallet2" aria-hidden="true"></i>
                    Tienes <strong>$<?php echo number_format($saldoCredito, 2, '.', ','); ?></strong> en crédito de tienda.
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="checkUsarCredito">
                    <label class="form-check-label" for="checkUsarCredito">
                        Usar mi crédito de tienda
                    </label>
                </div>
                <div id="resumenCredito" class="d-none mb-3 small">
                    <div class="d-flex justify-content-between text-success">
                        <span>Crédito aplicado</span>
                        <span id="montoCreditoAplicado">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fs-5">A pagar</span>
                        <strong class="fs-4" id="montoAPagar">$<?php echo number_format($totalCarrito, 2, '.', ','); ?> MXN</strong>
                    </div>
                </div>
                <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fs-5">Total</span>
                    <strong class="fs-4">$<?php echo number_format($totalCarrito, 2, '.', ','); ?> MXN</strong>
                </div>
                <?php endif; ?>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="checkEntregaTienda">
                    <label class="form-check-label" for="checkEntregaTienda">
                        Acepto que la entrega de mi compra es <strong>exclusivamente en tienda</strong> y que deberé
                        presentar una identificación oficial junto con mi número de orden.
                    </label>
                </div>

                <button type="button" class="btn btn-dark btn-lg w-100" id="btnIrAPagar" disabled>
                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                    Ir a pagar con Mercado Pago
                </button>
                <div class="small text-muted mt-2 text-center">
                    Procesamos tu pago de forma segura con Mercado Pago. Aceptamos tarjeta, OXXO y SPEI.
                </div>
                <div id="checkoutFeedback" class="alert alert-danger mt-3 d-none" role="alert"></div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/tienda-carrito.js"></script>
<script>
(function(){
    'use strict';
    var check = document.getElementById('checkEntregaTienda');
    var btn = document.getElementById('btnIrAPagar');
    var fb = document.getElementById('checkoutFeedback');
    var checkCredito = document.getElementById('checkUsarCredito');
    var resumenCredito = document.getElementById('resumenCredito');
    var montoCreditoAplicado = document.getElementById('montoCreditoAplicado');
    var montoAPagar = document.getElementById('montoAPagar');
    var saldoCredito = <?php echo json_encode($saldoCredito); ?>;
    var totalCarrito = <?php echo json_encode($totalCarrito); ?>;

    function fmt(n) {
        return '$' + Number(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function creditoAplicadoActual() {
        if (!checkCredito || !checkCredito.checked) return 0;
        return Math.min(saldoCredito, totalCarrito);
    }

    function actualizarResumenCredito() {
        var aplicado = creditoAplicadoActual();
        var resto = Math.max(0, totalCarrito - aplicado);
        if (resumenCredito) {
            resumenCredito.classList.toggle('d-none', aplicado <= 0);
        }
        if (montoCreditoAplicado) {
            montoCreditoAplicado.textContent = fmt(aplicado);
        }
        if (montoAPagar) {
            montoAPagar.textContent = fmt(resto) + ' MXN';
        }
        if (btn) {
            if (aplicado >= totalCarrito - 0.009) {
                btn.innerHTML = '<i class="bi bi-wallet2" aria-hidden="true"></i> Confirmar pedido (pagar con crédito)';
            } else {
                btn.innerHTML = '<i class="bi bi-credit-card" aria-hidden="true"></i> Ir a pagar con Mercado Pago';
            }
        }
    }

    if (checkCredito) {
        checkCredito.addEventListener('change', actualizarResumenCredito);
        actualizarResumenCredito();
    }

    if (check && btn) {
        check.addEventListener('change', function(){
            btn.disabled = !check.checked;
        });
    }

    document.querySelectorAll('.btn-eliminar-carrito').forEach(function(b){
        b.addEventListener('click', async function(){
            var id = parseInt(b.dataset.idCarritoItem || '0', 10);
            if (!id) return;
            b.disabled = true;
            try {
                var res = await fetch('../tienda_carrito_api.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({action:'eliminar', id_carrito_item:id})
                });
                var data = await res.json();
                if (data && data.ok) {
                    window.location.reload();
                } else {
                    alert((data && data.error) || 'No se pudo eliminar.');
                    b.disabled = false;
                }
            } catch(e){
                b.disabled = false;
                alert('Error de red.');
            }
        });
    });

    if (btn) {
        btn.addEventListener('click', async function(){
            if (!check || !check.checked) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Procesando...';
            if (fb) fb.classList.add('d-none');
            try {
                var payload = {action:'iniciar', aceptacion_entrega_tienda:true};
                var credito = creditoAplicadoActual();
                if (credito > 0) {
                    payload.credito_aplicado = credito;
                }
                var res = await fetch('../tienda_checkout_api.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify(payload)
                });
                var data = await res.json();
                if (data && data.ok && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }
                if (fb) {
                    fb.textContent = (data && data.error) || 'No se pudo iniciar el pago.';
                    fb.classList.remove('d-none');
                }
                btn.disabled = false;
                actualizarResumenCredito();
            } catch (e) {
                btn.disabled = false;
                actualizarResumenCredito();
                if (fb) {
                    fb.textContent = 'Error de red al iniciar el pago.';
                    fb.classList.remove('d-none');
                }
            }
        });
    }
})();
</script>
</body>
</html>
