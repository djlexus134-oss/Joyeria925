(function () {
    var root = document.querySelector('[data-vitrina="1"]');
    if (!root) {
        return;
    }
    var tabs = root.querySelectorAll('[data-vitrina-tab]');
    var panes = root.querySelectorAll('[data-vitrina-pane]');
    var n = panes.length;

    if (!n) {
        return;
    }

    var index = 0;
    var timer;
    var intervalMs = 7800;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var useGsap = !reduceMotion && typeof window.gsap !== 'undefined';
    var hasTabs = tabs.length === n;
    var activeTl = null;

    function syncTabs(activeIdx) {
        if (!hasTabs) {
            return;
        }
        tabs.forEach(function (tab, j) {
            var on = j === activeIdx;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function syncAria(activeIdx) {
        panes.forEach(function (pane, j) {
            pane.setAttribute('aria-hidden', j === activeIdx ? 'false' : 'true');
        });
    }

    function showInstant(nextIdx) {
        index = (nextIdx + n) % n;
        panes.forEach(function (pane, j) {
            pane.classList.toggle('is-active', j === index);
        });
        syncAria(index);
        syncTabs(index);
    }

    function showAnimated(nextIdx) {
        var target = (nextIdx + n) % n;
        if (target === index) {
            return;
        }

        if (activeTl && typeof activeTl.progress === 'function') {
            activeTl.progress(1, false);
        }

        var outgoing = panes[index];
        var incoming = panes[target];
        index = target;

        syncTabs(index);
        syncAria(index);

        incoming.classList.add('is-active');
        outgoing.style.zIndex = '1';
        incoming.style.zIndex = '2';
        incoming.style.opacity = '1';

        window.gsap.killTweensOf([outgoing, incoming]);

        activeTl = window.gsap
            .timeline({
                defaults: { ease: 'power2.inOut' },
                onComplete: function () {
                    panes.forEach(function (pane, j) {
                        pane.classList.toggle('is-active', j === index);
                    });
                    outgoing.classList.remove('is-active');
                    outgoing.style.zIndex = '';
                    incoming.style.zIndex = '';
                    window.gsap.set(outgoing, { clearProps: 'opacity,transform' });
                    window.gsap.set(incoming, { clearProps: 'opacity,transform' });
                    activeTl = null;
                },
            })
            .to(outgoing, { opacity: 0, duration: 0.36 })
            .fromTo(
                incoming,
                { opacity: 0, y: 18 },
                { opacity: 1, y: 0, duration: 0.5, ease: 'power3.out' },
                '-=0.26'
            );
    }

    function show(nextIdx) {
        if (useGsap) {
            showAnimated(nextIdx);
        } else {
            showInstant(nextIdx);
        }
    }

    function next() {
        show(index + 1);
    }

    function prev() {
        show(index - 1);
    }

    function restartTimer() {
        clearInterval(timer);
        timer = window.setInterval(next, intervalMs);
    }

    if (hasTabs) {
        tabs.forEach(function (tab, j) {
            tab.addEventListener('click', function () {
                show(j);
                restartTimer();
            });
        });
    }

    restartTimer();

    root.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            next();
            restartTimer();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prev();
            restartTimer();
        }
    });
})();
