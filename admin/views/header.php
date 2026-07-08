<?php
require_once __DIR__ . '/../../includes/joyeria_branding.php';
require_once __DIR__ . '/../includes/auth.php';

$guard = auth_current_access_guard();
if (!$guard['allowed']) {
    auth_set_flash((string) $guard['message'], 'error');

    if (!empty($guard['redirect'])) {
        header('Location: ' . $guard['redirect']);
        exit;
    }

    $deniedModule = (string) ($guard['module'] ?? '');
    $fallbackHref = auth_access_denied_fallback_href();
    if ($deniedModule === 'index') {
        $deniedMessage = 'Tu rol no incluye acceso al panel de inicio. Pide que te asignen PANEL_LEER o entra a un módulo permitido.';
    } else {
        $deniedMessage = 'No tienes permisos para acceder a este módulo.';
    }

    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Acceso denegado</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/main.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../css/main.css'); ?>">
        <link rel="stylesheet" href="../css/admin.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../css/admin.css'); ?>">
    </head>
    <body class="admin-login-body">
        <div class="admin-login-wrap">
            <section class="admin-login-card">
                <h2>Acceso denegado</h2>
                <p><?php echo htmlspecialchars($deniedMessage); ?></p>
                <div class="form-actions">
                    <?php if ($fallbackHref !== null): ?>
                        <a href="<?php echo htmlspecialchars($fallbackHref); ?>" class="btn-action-primary">Ir a módulo permitido</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-action-danger">Cerrar sesión</a>
                </div>
            </section>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Validar CSRF en POST de formularios tradicionales
joyeria_admin_csrf_require_for_post();

$authUser = auth_user();
$authFlash = auth_pull_flash();
$authNavGroups = auth_visible_nav_groups();
$authCaps = auth_current_capabilities();
$authRoles = isset($authUser['roles']) && is_array($authUser['roles']) ? implode(', ', $authUser['roles']) : '';
$authCurrentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$authCsrfToken = joyeria_admin_csrf_token();
joyeria_session_release_write();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Administrador'), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($authCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <link rel="stylesheet" href="../css/main.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../css/main.css'); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../css/admin.css'); ?>">
    <link rel="stylesheet" href="css/joyeria-barcode-input.css?v=<?php echo (int) @filemtime(__DIR__ . '/../css/joyeria-barcode-input.css'); ?>">
    <script src="js/joyeria-csrf.js?v=<?php echo (int) @filemtime(__DIR__ . '/../js/joyeria-csrf.js'); ?>"></script>
    <script src="js/joyeria-api-fetch.js?v=<?php echo (int) @filemtime(__DIR__ . '/../js/joyeria-api-fetch.js'); ?>"></script>
    <script src="js/joyeria-barcode-input.js?v=<?php echo (int) @filemtime(__DIR__ . '/../js/joyeria-barcode-input.js'); ?>" defer></script>
    <script>
    (function () {
        function readCsrfToken() {
            var token = window.joyeriaCsrfToken ? window.joyeriaCsrfToken() : '';
            if (!token) {
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                token = csrfMeta ? String(csrfMeta.getAttribute('content') || '').trim() : '';
            }
            return token;
        }

        function isSameOriginRequest(input) {
            var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
            if (!url || url.indexOf('http') !== 0) {
                return true;
            }
            try {
                return new URL(url, window.location.href).origin === window.location.origin;
            } catch (e) {
                return true;
            }
        }

        var token = readCsrfToken();
        var origFetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            if (!init.credentials && isSameOriginRequest(input)) {
                init.credentials = 'same-origin';
            }
            if (isSameOriginRequest(input)) {
                if (window.joyeriaPrepareFetchCsrf) {
                    init = window.joyeriaPrepareFetchCsrf(init);
                } else if (token) {
                    var method = (init.method || 'GET').toUpperCase();
                    if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
                        var headers = new Headers(init.headers || undefined);
                        headers.set('X-CSRF-Token', token);
                        init.headers = headers;
                        if (init.body instanceof FormData && window.joyeriaAppendCsrfToFormData) {
                            window.joyeriaAppendCsrfToFormData(init.body);
                        }
                    }
                }
            }
            return origFetch.call(this, input, init);
        };

        function joyeriaAppendCsrfField(form) {
            if (!form || form.tagName !== 'FORM') return;
            if (form.querySelector('input[name="_csrf_token"]')) return;
            var field = document.createElement('input');
            field.type = 'hidden';
            field.name = '_csrf_token';
            field.value = token;
            form.appendChild(field);
        }

        window.joyeriaEnviarFormConCsrf = function (targetForm) {
            if (!targetForm || targetForm.tagName !== 'FORM') return;
            joyeriaAppendCsrfField(targetForm);
            if (typeof targetForm.requestSubmit === 'function') {
                targetForm.requestSubmit();
            } else {
                targetForm.submit();
            }
        };

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            var method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method !== 'POST') return;
            joyeriaAppendCsrfField(form);
        }, true);
    })();
    </script>
</head>

<body
    data-can-create="<?php echo $authCaps['canCreate'] ? '1' : '0'; ?>"
    data-can-update="<?php echo $authCaps['canUpdate'] ? '1' : '0'; ?>"
    data-can-delete="<?php echo $authCaps['canDelete'] ? '1' : '0'; ?>"
    data-can-photo="<?php echo $authCaps['canPhoto'] ? '1' : '0'; ?>"
>

    <div class="admin-layout">

        <aside class="admin-sidebar">
            <div class="admin-brand">
                <h1><?php echo htmlspecialchars(joyeria_marca_nombre(), ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Panel Administrativo</p>
            </div>

            <div class="admin-user-box">
                <strong><?php echo htmlspecialchars((string) ($authUser['nombre_completo'] ?? '')); ?></strong>
                <small><?php echo htmlspecialchars((string) ($authUser['correo'] ?? '')); ?></small>
                <?php if ($authRoles !== ''): ?>
                    <small>Rol(es): <?php echo htmlspecialchars($authRoles); ?></small>
                <?php endif; ?>
                <a href="logout.php" class="admin-logout-link"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
            </div>

            <nav class="admin-nav">
                <ul>
                    <?php foreach ($authNavGroups as $groupIndex => $group): ?>
                        <?php
                        $groupHasActive = false;
                        foreach ((array) ($group['items'] ?? []) as $groupItem) {
                            $groupItemScript = basename(explode('?', (string) ($groupItem['script'] ?? ''))[0]);
                            if ($groupItemScript === $authCurrentScript) {
                                $groupHasActive = true;
                                break;
                            }
                        }
                        $groupOpen = $groupHasActive || $groupIndex === 0;
                        ?>
                        <li class="nav-group">
                            <button type="button" class="nav-group-toggle<?php echo $groupOpen ? ' is-open' : ''; ?>" data-nav-toggle>
                                <span class="sidebar-icon"><i class="bi <?php echo htmlspecialchars((string) ($group['icon'] ?? 'bi-folder2-open')); ?>"></i></span>
                                <span class="nav-group-title"><?php echo htmlspecialchars((string) ($group['label'] ?? 'Grupo')); ?></span>
                                <span class="nav-group-caret"><i class="bi bi-chevron-down"></i></span>
                            </button>
                            <ul class="nav-group-items<?php echo $groupOpen ? ' is-open' : ''; ?>" data-nav-items>
                                <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
                                    <?php $itemScript = basename(explode('?', (string) ($item['script'] ?? ''))[0]); ?>
                                    <li>
                                        <a class="<?php echo $itemScript === $authCurrentScript ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars((string) $item['script']); ?>">
                                            <span class="sidebar-icon"><i class="bi <?php echo htmlspecialchars((string) $item['icon']); ?>"></i></span>
                                            <?php echo htmlspecialchars((string) $item['label']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="admin-sidebar-overlay" data-sidebar-close aria-hidden="true"></div>

        <main class="admin-content">
            <div class="admin-topbar">
                <div class="admin-topbar-left">
                    <button type="button" class="admin-burger" data-sidebar-toggle aria-label="Abrir menu">
                        <i class="bi bi-list" aria-hidden="true"></i>
                        <span>Menu</span>
                    </button>
                </div>
                <div class="admin-topbar-right">
                    <div class="admin-bell-wrap">
                        <button type="button" id="adminBellBtn" class="admin-bell-btn" aria-label="Notificaciones" title="Notificaciones">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <span id="adminBellBadge" class="admin-bell-badge" style="display:none;">0</span>
                        </button>
                        <div id="adminBellPopover" class="admin-bell-popover" role="dialog" aria-label="Notificaciones">
                            <div class="admin-bell-popover-header">
                                <span>Notificaciones</span>
                                <button type="button" id="adminBellMarkAll" class="btn-action-secondary">Marcar todas leidas</button>
                            </div>
                            <div id="adminBellList" class="admin-bell-list">
                                <div class="admin-bell-loading">Cargando...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            (function(){
                'use strict';

                /* === Drawer (sidebar hamburguesa) === */
                var layout = document.querySelector('.admin-layout');
                var burger = document.querySelector('[data-sidebar-toggle]');
                var overlay = document.querySelector('[data-sidebar-close]');
                function closeSidebar(){ if (layout) layout.classList.remove('sidebar-open'); }
                if (burger && layout) {
                    burger.addEventListener('click', function(e){
                        e.preventDefault();
                        layout.classList.toggle('sidebar-open');
                    });
                }
                if (overlay) overlay.addEventListener('click', closeSidebar);
                document.addEventListener('keydown', function(e){
                    if (e.key === 'Escape') closeSidebar();
                });
                document.querySelectorAll('.admin-nav a').forEach(function(a){
                    a.addEventListener('click', function(){
                        if (window.innerWidth <= 1100) closeSidebar();
                    });
                });

                /* === Mover la campana dentro del admin-header del modulo
                   para que quede en la misma linea visual que el H2 sin
                   recurrir a overlays con z-index. === */
                function moveBellIntoHeader() {
                    var hdr = document.querySelector('.admin-header');
                    var wrap = document.querySelector('.admin-bell-wrap');
                    if (!hdr || !wrap || wrap.parentElement === hdr) return;
                    hdr.appendChild(wrap);
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', moveBellIntoHeader);
                } else {
                    moveBellIntoHeader();
                }

                /* === Campana de notificaciones === */
                var btn = document.getElementById('adminBellBtn');
                var badge = document.getElementById('adminBellBadge');
                var pop = document.getElementById('adminBellPopover');
                var list = document.getElementById('adminBellList');
                var markAll = document.getElementById('adminBellMarkAll');
                if (!btn || !pop) return;

                function escHtml(s) {
                    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
                        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
                    });
                }

                function deepLink(it) {
                    var tipo = String(it.tipo || '');
                    var ref = parseInt(it.id_referencia || 0, 10);
                    if (ref > 0 && (tipo === 'venta_online_nueva' || tipo === 'venta_online_lista_recoger' || tipo === 'venta_online_entregada' || tipo === 'venta_online_stock_perdido')) {
                        return 'ventas_online.php?accion=ver&id=' + ref;
                    }
                    return null;
                }

                async function fetchData() {
                    try {
                        var res = await fetch('notificaciones_panel_api.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {'Content-Type':'application/json'},
                            body: JSON.stringify({action:'consultar'})
                        });
                        var data = await res.json();
                        if (!data || !data.ok) {
                            list.innerHTML = '<div class="admin-bell-empty">'
                                + escHtml((data && data.error) || 'No se pudieron cargar las notificaciones.')
                                + '</div>';
                            return;
                        }
                        if (data.no_leidas > 0) {
                            badge.textContent = data.no_leidas > 99 ? '99+' : String(data.no_leidas);
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                        if (data.items && data.items.length > 0) {
                            var html = '';
                            data.items.forEach(function(it){
                                var link = deepLink(it);
                                var msg = escHtml(it.mensaje || '');
                                var fecha = escHtml(it.fecha_envio || '');
                                var unread = parseInt(it.leida, 10) !== 1;
                                var cls = 'admin-bell-item' + (unread ? ' is-unread' : '');
                                var idN = parseInt(it.id_notificacion || 0, 10);
                                var inner = msg + '<span class="admin-bell-item-fecha">' + fecha + '</span>';
                                if (link) {
                                    html += '<a href="' + link + '" data-id-n="' + idN + '" class="' + cls + '">' + inner + '</a>';
                                } else {
                                    html += '<div data-id-n="' + idN + '" class="' + cls + '">' + inner + '</div>';
                                }
                            });
                            list.innerHTML = html;
                        } else {
                            list.innerHTML = '<div class="admin-bell-empty">Sin notificaciones.</div>';
                        }
                    } catch(e){
                        list.innerHTML = '<div class="admin-bell-empty">Error de red al cargar notificaciones.</div>';
                    }
                }

                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var open = pop.classList.contains('is-open');
                    pop.classList.toggle('is-open', !open);
                    if (!open) fetchData();
                });

                document.addEventListener('click', function(e){
                    if (!pop.classList.contains('is-open')) return;
                    if (e.target.closest('#adminBellPopover') || e.target.closest('#adminBellBtn')) return;
                    pop.classList.remove('is-open');
                });

                if (markAll) {
                    markAll.addEventListener('click', async function(e){
                        e.preventDefault();
                        e.stopPropagation();
                        try {
                            await fetch('notificaciones_panel_api.php', {
                                method:'POST',
                                credentials:'same-origin',
                                headers:{'Content-Type':'application/json'},
                                body: JSON.stringify({action:'marcar_todas'})
                            });
                            fetchData();
                        } catch(e){}
                    });
                }

                fetchData();
                setInterval(fetchData, 60000);
            })();
            </script>
            <?php if ($authFlash !== null && !empty($authFlash['message'])): ?>
                <div class="auth-flash-wrap">
                    <div class="alert-message <?php echo htmlspecialchars((string) ($authFlash['type'] ?? 'info')); ?>">
                        <p><i class="bi bi-shield-exclamation"></i> <?php echo htmlspecialchars((string) $authFlash['message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var canCreate = document.body.getAttribute('data-can-create') === '1';
                    var canUpdate = document.body.getAttribute('data-can-update') === '1';
                    var canDelete = document.body.getAttribute('data-can-delete') === '1';
                    var canPhoto = document.body.getAttribute('data-can-photo') === '1';

                    function hideBySelector(selector, allowed) {
                        if (allowed) {
                            return;
                        }
                        var nodes = document.querySelectorAll(selector);
                        for (var i = 0; i < nodes.length; i++) {
                            nodes[i].style.display = 'none';
                        }
                    }

                    hideBySelector('a[href*="accion=crear"], a[href*="accion=asignar"]', canCreate);
                    hideBySelector('a[href*="accion=actualizar"]', canUpdate);
                    hideBySelector('a[href*="accion=gestionar_foto"], a[href*="accion=subir_foto"], a[href*="accion=establecer_principal_imagen"], a[href*="accion=eliminar_imagen"]', canPhoto);
                    hideBySelector('a[href*="accion=borrar"], a[href*="accion=revocar"], a[href*="accion=desvincular"]', canDelete);

                    var navToggles = document.querySelectorAll('[data-nav-toggle]');
                    for (var j = 0; j < navToggles.length; j++) {
                        navToggles[j].addEventListener('click', function () {
                            var items = this.nextElementSibling;
                            if (!items) {
                                return;
                            }
                            var shouldOpen = !items.classList.contains('is-open');
                            this.classList.toggle('is-open', shouldOpen);
                            items.classList.toggle('is-open', shouldOpen);
                        });
                    }
                });
            </script>