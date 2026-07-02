<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/joyeria_branding.php';
require_once __DIR__ . '/admin/models/configuracion_general.php';
require_once __DIR__ . '/admin/includes/SpeiDepositoPayloadBuilder.php';

$montoRaw = isset($_GET['m']) ? (string) $_GET['m'] : '';
$monto = is_numeric($montoRaw) ? round((float) $montoRaw, 2) : 0.0;
$referencia = SpeiDepositoPayloadBuilder::normalizarReferenciaUrl((string) ($_GET['r'] ?? ''));

$configGeneral = new ConfiguracionGeneral();
$datosSpei = $configGeneral->leerDatosDepositoSpei();

$paginaValida = $monto > 0.02 && !empty($datosSpei['habilitado']);
$montoFmt = SpeiDepositoPayloadBuilder::formatearMonto($monto);
$clabe = (string) ($datosSpei['clabe'] ?? '');
$beneficiario = (string) ($datosSpei['beneficiario'] ?? '');
$banco = (string) ($datosSpei['banco'] ?? '');
$instrucciones = (string) ($datosSpei['instrucciones'] ?? '');

$textoCopiarTodo = SpeiDepositoPayloadBuilder::construirTexto($datosSpei, $monto, $referencia);
$hrefCatalogo = 'catalogo.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Transferencia SPEI'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="spei-deposito-body">
    <main class="spei-deposito-page">
        <header class="spei-deposito-header">
            <p class="spei-deposito-marca"><?php echo htmlspecialchars(joyeria_marca_nombre(), ENT_QUOTES, 'UTF-8'); ?></p>
            <h1><i class="bi bi-bank2"></i> Datos para transferencia</h1>
            <p class="spei-deposito-lead">Usa estos datos en tu app bancaria para completar el depósito.</p>
        </header>

        <?php if (!$paginaValida): ?>
            <div class="alert-message error spei-deposito-alert">
                <p>Enlace no válido o depósito por transferencia no disponible. Pide al cajero que genere un nuevo código QR.</p>
            </div>
        <?php else: ?>
            <section class="spei-deposito-card" aria-label="Datos bancarios">
                <?php if ($beneficiario !== ''): ?>
                    <div class="spei-deposito-row">
                        <span class="spei-deposito-label">Beneficiario</span>
                        <span class="spei-deposito-value" id="spei-val-beneficiario"><?php echo htmlspecialchars($beneficiario, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($banco !== ''): ?>
                    <div class="spei-deposito-row">
                        <span class="spei-deposito-label">Banco</span>
                        <span class="spei-deposito-value"><?php echo htmlspecialchars($banco, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($clabe !== ''): ?>
                    <div class="spei-deposito-row spei-deposito-row--clabe">
                        <span class="spei-deposito-label">CLABE</span>
                        <span class="spei-deposito-value spei-deposito-clabe" id="spei-val-clabe"><?php echo htmlspecialchars($clabe, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="spei-deposito-row spei-deposito-row--monto">
                    <span class="spei-deposito-label">Monto</span>
                    <span class="spei-deposito-value spei-deposito-monto" id="spei-val-monto"><?php echo htmlspecialchars($montoFmt, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="spei-deposito-row">
                    <span class="spei-deposito-label">Concepto</span>
                    <span class="spei-deposito-value" id="spei-val-concepto"><?php echo htmlspecialchars($referencia, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if ($instrucciones !== ''): ?>
                    <p class="spei-deposito-instrucciones"><?php echo htmlspecialchars($instrucciones, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </section>

            <div class="spei-deposito-actions">
                <?php if ($clabe !== ''): ?>
                    <button type="button" class="btn-action-secondary spei-btn-copiar" data-copy-target="spei-val-clabe">
                        <i class="bi bi-clipboard"></i> Copiar CLABE
                    </button>
                <?php endif; ?>
                <button type="button" class="btn-action-secondary spei-btn-copiar" data-copy-target="spei-val-concepto">
                    <i class="bi bi-clipboard"></i> Copiar concepto
                </button>
                <button type="button" class="btn-action-primary spei-btn-copiar" id="btn-spei-copiar-todo" data-copy-text="<?php echo htmlspecialchars($textoCopiarTodo, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="bi bi-clipboard-check"></i> Copiar todo
                </button>
            </div>
        <?php endif; ?>

        <aside class="spei-deposito-promo">
            <h2>Conoce nuestra página web</h2>
            <p>Explora el catálogo de plata ley .925 y promociones vigentes.</p>
            <a class="btn-action-primary spei-deposito-promo-link" href="<?php echo htmlspecialchars($hrefCatalogo, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="bi bi-shop"></i> Ver catálogo
            </a>
        </aside>
    </main>
    <script>
    (function () {
        function copiarTexto(texto, btn) {
            if (!texto) return;
            var done = function () {
                if (!btn) return;
                var prev = btn.innerHTML;
                btn.classList.add('is-copied');
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Copiado';
                setTimeout(function () {
                    btn.innerHTML = prev;
                    btn.classList.remove('is-copied');
                }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(done).catch(function () {
                    fallbackCopy(texto);
                    done();
                });
                return;
            }
            fallbackCopy(texto);
            done();
        }
        function fallbackCopy(texto) {
            var ta = document.createElement('textarea');
            ta.value = texto;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
        }
        document.querySelectorAll('.spei-btn-copiar').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var texto = btn.getAttribute('data-copy-text') || '';
                if (!texto) {
                    var id = btn.getAttribute('data-copy-target');
                    var el = id ? document.getElementById(id) : null;
                    texto = el ? (el.textContent || '').trim() : '';
                }
                copiarTexto(texto, btn);
            });
        });
    }());
    </script>
</body>
</html>
