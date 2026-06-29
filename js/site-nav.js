(function () {
    'use strict';

    var MQ = window.matchMedia('(max-width: 768px)');

    function isMobileNav() {
        return MQ.matches;
    }

    function init() {
        var header = document.querySelector('header.header');
        if (!header) {
            return;
        }

        var nav = header.querySelector('nav.nav');
        var icons = header.querySelector('.header-icons');
        if (!nav || !icons) {
            return;
        }

        if (!nav.id) {
            nav.id = 'site-primary-nav';
        }

        var toggle = header.querySelector('.nav-toggle');
        if (!toggle) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'nav-toggle';
            toggle.setAttribute('aria-label', 'Abrir menú de navegación');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-controls', nav.id);
            toggle.innerHTML = '<span class="nav-toggle-icon" aria-hidden="true"></span>';
            icons.appendChild(toggle);
        }

        var backdrop = header.querySelector('.nav-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'nav-backdrop';
            backdrop.setAttribute('aria-label', 'Cerrar menú');
            backdrop.setAttribute('tabindex', '-1');
            header.appendChild(backdrop);
        }

        function syncHeaderHeight() {
            header.style.setProperty('--site-header-height', header.offsetHeight + 'px');
        }

        function closeSubmenus() {
            header.querySelectorAll('.nav-item-dropdown.is-submenu-open').forEach(function (el) {
                el.classList.remove('is-submenu-open');
            });
        }

        function setOpen(open) {
            if (open) {
                syncHeaderHeight();
            }
            header.classList.toggle('is-nav-open', open);
            document.body.classList.toggle('site-nav-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
            if (!open) {
                backdrop.setAttribute('tabindex', '-1');
                closeSubmenus();
            } else {
                backdrop.removeAttribute('tabindex');
            }
        }

        function closeMenu() {
            setOpen(false);
        }

        toggle.addEventListener('click', function () {
            setOpen(!header.classList.contains('is-nav-open'));
        });

        backdrop.addEventListener('click', closeMenu);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        if (typeof MQ.addEventListener === 'function') {
            MQ.addEventListener('change', function () {
                if (!isMobileNav()) {
                    closeMenu();
                }
            });
        } else if (typeof MQ.addListener === 'function') {
            MQ.addListener(function () {
                if (!isMobileNav()) {
                    closeMenu();
                }
            });
        }

        window.addEventListener('resize', function () {
            if (header.classList.contains('is-nav-open')) {
                syncHeaderHeight();
            }
        });

        nav.addEventListener('click', function (event) {
            if (!isMobileNav()) {
                return;
            }

            var dropdownLink = event.target.closest('.nav-link-dropdown');
            if (dropdownLink) {
                var item = dropdownLink.closest('.nav-item-dropdown');
                var panel = item && item.querySelector('.nav-dropdown-panel');
                if (panel) {
                    var alreadyOpen = item.classList.contains('is-submenu-open');
                    if (alreadyOpen) {
                        closeMenu();
                        return;
                    }
                    event.preventDefault();
                    closeSubmenus();
                    item.classList.add('is-submenu-open');
                    return;
                }
            }

            var link = event.target.closest('a[href]');
            if (link && nav.contains(link)) {
                closeMenu();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
