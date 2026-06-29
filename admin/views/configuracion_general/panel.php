<?php
/**
 * @var array $valores
 * @var array $catalogos
 * @var array $opcionesBarcode
 * @var string $seccion
 * @var string|null $mensaje
 * @var string $tipoMensaje
 * @var string $vistaPrevia
 */
$configHubCss = __DIR__ . '/../../../css/config-hub.css';
?>
<link rel="stylesheet" href="../css/config-hub.css?v=<?php echo (int) @filemtime($configHubCss); ?>">

<div class="admin-modules config-hub">
    <?php if (!empty($mensaje)): ?>
        <div class="alert-message <?php echo htmlspecialchars($tipoMensaje ?? 'success'); ?>">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="config-hub-hero">
        <div>
            <h3>Centro de configuración</h3>
            <p>Ajusta valores por defecto del negocio, tickets de venta, etiquetas Argox y textos legales de contratos. Los cambios se aplican de inmediato en captura e impresión.</p>
        </div>
        <i class="bi bi-sliders2" style="font-size:2rem;color:#5b7384;opacity:0.85;"></i>
    </div>

    <nav class="config-hub-tabs" role="tablist" aria-label="Secciones de configuración">
        <button type="button" class="config-hub-tab<?php echo $seccion === 'negocio' ? ' is-active' : ''; ?>" data-config-tab="negocio" role="tab" aria-selected="<?php echo $seccion === 'negocio' ? 'true' : 'false'; ?>">
            <i class="bi bi-shop"></i> Negocio
        </button>
        <button type="button" class="config-hub-tab<?php echo $seccion === 'ticket' ? ' is-active' : ''; ?>" data-config-tab="ticket" role="tab">
            <i class="bi bi-receipt"></i> Ticket POS
        </button>
        <button type="button" class="config-hub-tab<?php echo $seccion === 'etiquetas' ? ' is-active' : ''; ?>" data-config-tab="etiquetas" role="tab">
            <i class="bi bi-tags"></i> Etiquetas
        </button>
        <button type="button" class="config-hub-tab<?php echo $seccion === 'contratos' ? ' is-active' : ''; ?>" data-config-tab="contratos" role="tab">
            <i class="bi bi-file-earmark-text"></i> Contratos
        </button>
        <button type="button" class="config-hub-tab<?php echo $seccion === 'mensajeria' ? ' is-active' : ''; ?>" data-config-tab="mensajeria" role="tab">
            <i class="bi bi-whatsapp"></i> Mensajeria
        </button>
        <button type="button" class="config-hub-tab<?php echo $seccion === 'facturacion' ? ' is-active' : ''; ?>" data-config-tab="facturacion" role="tab">
            <i class="bi bi-receipt-cutoff"></i> Facturacion
        </button>
    </nav>

    <form method="post" action="configuracion_general.php?accion=guardar" class="form-section" id="config-hub-form">
        <input type="hidden" name="seccion_activa" id="seccion_activa" value="<?php echo htmlspecialchars($seccion); ?>">

        <div class="config-hub-panel<?php echo $seccion === 'negocio' ? ' is-active' : ''; ?>" data-config-panel="negocio" role="tabpanel">
            <?php require __DIR__ . '/_seccion_negocio.php'; ?>
        </div>

        <div class="config-hub-panel<?php echo $seccion === 'ticket' ? ' is-active' : ''; ?>" data-config-panel="ticket" role="tabpanel">
            <?php require __DIR__ . '/_seccion_ticket.php'; ?>
        </div>

        <div class="config-hub-panel<?php echo $seccion === 'etiquetas' ? ' is-active' : ''; ?>" data-config-panel="etiquetas" role="tabpanel">
            <?php require __DIR__ . '/_seccion_etiquetas.php'; ?>
        </div>

        <div class="config-hub-panel<?php echo $seccion === 'contratos' ? ' is-active' : ''; ?>" data-config-panel="contratos" role="tabpanel">
            <?php require __DIR__ . '/_seccion_contratos.php'; ?>
        </div>

        <div class="config-hub-panel<?php echo $seccion === 'mensajeria' ? ' is-active' : ''; ?>" data-config-panel="mensajeria" role="tabpanel">
            <?php require __DIR__ . '/_seccion_mensajeria.php'; ?>
        </div>

        <div class="config-hub-panel<?php echo $seccion === 'facturacion' ? ' is-active' : ''; ?>" data-config-panel="facturacion" role="tabpanel">
            <?php require __DIR__ . '/_seccion_facturacion.php'; ?>
        </div>

        <div class="config-hub-savebar">
            <small><i class="bi bi-lightning-charge"></i> Un solo guardado actualiza todas las secciones visibles del formulario.</small>
            <button type="submit" class="btn-action-primary"><i class="bi bi-save"></i> Guardar configuración</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.querySelectorAll('[data-config-tab]');
    var panels = document.querySelectorAll('[data-config-panel]');
    var hidden = document.getElementById('seccion_activa');

    function activar(seccion) {
        for (var i = 0; i < tabs.length; i++) {
            var on = tabs[i].getAttribute('data-config-tab') === seccion;
            tabs[i].classList.toggle('is-active', on);
            tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
        }
        for (var j = 0; j < panels.length; j++) {
            panels[j].classList.toggle('is-active', panels[j].getAttribute('data-config-panel') === seccion);
        }
        if (hidden) {
            hidden.value = seccion;
        }
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('seccion', seccion);
            window.history.replaceState({}, '', url.toString());
        }
    }

    for (var t = 0; t < tabs.length; t++) {
        tabs[t].addEventListener('click', function () {
            activar(this.getAttribute('data-config-tab'));
        });
    }

    var hash = (window.location.search.match(/[?&]seccion=([a-z]+)/) || [])[1];
    if (hash && document.querySelector('[data-config-panel="' + hash + '"]')) {
        activar(hash);
    }

    var form = document.getElementById('config-hub-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var activePanel = document.querySelector('[data-config-panel].is-active');

            for (var p = 0; p < panels.length; p++) {
                if (panels[p] === activePanel) {
                    continue;
                }
                var hiddenFields = panels[p].querySelectorAll('input, select, textarea');
                for (var h = 0; h < hiddenFields.length; h++) {
                    var hf = hiddenFields[h];
                    if (hf.hasAttribute('required')) {
                        hf.removeAttribute('required');
                    }
                    if (hf.hasAttribute('min')) {
                        hf.removeAttribute('min');
                    }
                    if (hf.hasAttribute('max')) {
                        hf.removeAttribute('max');
                    }
                    if (hf.hasAttribute('step')) {
                        hf.removeAttribute('step');
                    }
                }
            }

            if (!activePanel) {
                return;
            }

            var activeFields = activePanel.querySelectorAll('input, select, textarea');
            for (var a = 0; a < activeFields.length; a++) {
                var field = activeFields[a];
                if (field.disabled || field.type === 'hidden') {
                    continue;
                }
                if (!field.checkValidity()) {
                    e.preventDefault();
                    field.reportValidity();
                    return;
                }
            }
        });
    }
});
</script>
