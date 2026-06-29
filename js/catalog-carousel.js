/**
 * Carrusel horizontal: flechas (1 tarjeta por clic), bucle infinito sin salto (carril triplicado).
 * Marquee continuo (scroll suave px/s) sólo en filas de catálogo con bucle infinito: renglón 1 → flujo ~izq→der,
 * renglón 2 → der→izq, alternado. Carrusel "Familia" (familias-spotlight): spotlight limpio
 * (escala/opacidad, sin coverflow 3D), flechas/teclado/dots.
 * El marquee se detiene al usar flechas/teclado, tocar la pista o la rueda (en catálogo).
 * prefers-reduced-motion: sin marquee.
 */
(function () {
    var roots = document.querySelectorAll('[data-catalog-carousel]');
    if (!roots.length) {
        return;
    }

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var SCROLL_EDGE_EPS = 4;
    /** Velocidad visual del marquee (px/s); un poco menor en carruseles que van al revés se ve igual por simetría */
    var MARQUEE_PX_PER_SEC = 32;

    function scrollBehaviorStep() {
        return reduceMotion ? 'auto' : 'smooth';
    }

    /**
     * @param {HTMLElement} node
     * @returns {HTMLElement}
     */
    function cloneCardForLoop(node) {
        var c = /** @type {HTMLElement} */ (node.cloneNode(true));
        c.setAttribute('data-carousel-clone', '1');
        c.removeAttribute('id');
        c.setAttribute('aria-hidden', 'true');
        if (c.tagName === 'A') {
            c.setAttribute('tabindex', '-1');
        }
        var focusables = c.querySelectorAll(
            'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        focusables.forEach(function (f) {
            f.setAttribute('tabindex', '-1');
        });
        return c;
    }

    /**
     * @param {HTMLElement[]} nodes
     * @returns {DocumentFragment}
     */
    function cloneStrip(nodes) {
        var frag = document.createDocumentFragment();
        nodes.forEach(function (node) {
            frag.appendChild(cloneCardForLoop(node));
        });
        return frag;
    }

    /**
     * @param {HTMLElement} track
     * @returns {HTMLElement[]}
     */
    function originalsInTrack(track) {
        return Array.prototype.slice.call(
            track.querySelectorAll(':scope > .catalog-carousel-card:not([data-carousel-clone])')
        );
    }

    /**
     * @param {HTMLElement} track
     * @param {HTMLElement[]} mids
     * @returns {number}
     */
    function measureCycleWidth(track, mids) {
        if (!mids.length) {
            return 0;
        }
        if (mids.length === 1) {
            return mids[0].offsetWidth;
        }
        var first = mids[0];
        var last = mids[mids.length - 1];
        return last.offsetLeft + last.offsetWidth - first.offsetLeft;
    }

    /**
     * @param {Element} root
     * @param {boolean} isFamiliaSpotlight
     * @param {number} catalogRowIx índice 0-based entre filas de catálogo (no spotlight) para alternar dirección
     */
    function initCarousel(root, isFamiliaSpotlight, catalogRowIx) {
        var track = root.querySelector('.catalog-carousel-track');
        var btnPrev = root.querySelector('.catalog-carousel-prev');
        var btnNext = root.querySelector('.catalog-carousel-next');
        if (!track || !btnPrev || !btnNext) {
            return;
        }

        /** Marquee: fila 0 = flujo visual ~izq→der (decrementa scrollLeft); fila 1 = der→izq; y así sucesivo */
        var marqueeDir = catalogRowIx % 2 === 0 ? -1 : 1;
        var marqueeWantsRun = !isFamiliaSpotlight && !reduceMotion;
        var marqueeDisabledByUser = false;
        var marqueeRaf = null;
        var marqueeLastTs = 0;
        /** Evita normalizar en pleno scroll suave / arrastre (spotlight infinito). */
        var spotlightDeferLoopNormalize = false;
        var spotlightSmoothScrolling = false;

        var dotsWrap = null;
        if (isFamiliaSpotlight) {
            var stageEl = root.parentElement;
            if (stageEl && stageEl.classList.contains('familias-spotlight-stage')) {
                dotsWrap = stageEl.querySelector('.familias-spotlight-dots');
            }
        }

        var originals = Array.prototype.slice.call(
            track.querySelectorAll(':scope > .catalog-carousel-card')
        );

        var loopActive = originals.length >= 2;
        var cycleWidth = 0;
        /** offsetLeft del primer original (= ancho tira prepend). */
        var loopStripStart = 0;
        var normalizing = false;

        function refreshLoopMetrics() {
            if (!loopActive) {
                return;
            }
            var mids = originalsInTrack(track);
            if (mids.length < 2) {
                return;
            }
            loopStripStart = mids[0].offsetLeft;
            cycleWidth = measureCycleWidth(track, mids);
        }

        if (loopActive) {
            cycleWidth = measureCycleWidth(track, originals);
            var prependFrag = cloneStrip(originals);
            var appendFrag = cloneStrip(originals);
            track.insertBefore(prependFrag, track.firstChild);
            track.appendChild(appendFrag);
            track.classList.add('catalog-carousel-track--loop');

            originals = originalsInTrack(track);
            refreshLoopMetrics();
        }

        function stopMarqueeRaf() {
            if (marqueeRaf !== null) {
                cancelAnimationFrame(marqueeRaf);
                marqueeRaf = null;
            }
            marqueeLastTs = 0;
        }

        function marqueeFrame(ts) {
            marqueeRaf = null;
            if (
                !marqueeWantsRun ||
                marqueeDisabledByUser ||
                !loopActive ||
                reduceMotion ||
                (typeof document !== 'undefined' && document.hidden)
            ) {
                return;
            }

            var sec = marqueeLastTs ? (ts - marqueeLastTs) / 1000 : 0;
            marqueeLastTs = ts;
            if (sec > 0.12) {
                sec = 0.12;
            }
            var deltaPx = MARQUEE_PX_PER_SEC * sec * marqueeDir;
            if (deltaPx !== 0) {
                track.scrollLeft += deltaPx;
                normalizeLoopPosition();
            }

            marqueeRaf = window.requestAnimationFrame(marqueeFrame);
        }

        function startMarquee() {
            if (!marqueeWantsRun || marqueeDisabledByUser || !loopActive || reduceMotion) {
                return;
            }
            if (typeof document !== 'undefined' && document.hidden) {
                return;
            }
            stopMarqueeRaf();
            marqueeLastTs = 0;
            marqueeRaf = window.requestAnimationFrame(marqueeFrame);
        }

        function interruptMarqueeFromUser() {
            if (!marqueeWantsRun) {
                return;
            }
            marqueeDisabledByUser = true;
            stopMarqueeRaf();
        }

        function getSpotlightArrowStepPx() {
            var mids = originalsInTrack(track);
            if (mids.length >= 2) {
                /** Tarjetas con solape (margin-left negativo): el avance por flecha ≠ ancho+fila gap */
                var stride = mids[1].offsetLeft - mids[0].offsetLeft;
                if (stride > 1) {
                    return Math.round(stride);
                }
            }
            return 0;
        }

        /**
         * Tarjetas Familia (sin clones): índice cuyo centro está más cerca del centro del viewport de scroll.
         * @returns {number}
         */
        function spotlightNearestOriginalIndex() {
            var mids = originalsInTrack(track);
            if (!mids.length) {
                return 0;
            }
            /** Si ya hay una tarjeta marcada como centrada, úsala como fuente de verdad. */
            var centeredIx = -1;
            for (var ci = 0; ci < mids.length; ci++) {
                if (mids[ci].classList.contains('is-centered')) {
                    centeredIx = ci;
                    break;
                }
            }
            if (centeredIx >= 0) {
                return centeredIx;
            }
            var vw = track.clientWidth;
            var center = track.scrollLeft + vw * 0.5;
            var bestIdx = 0;
            var bestDist = Infinity;
            for (var si = 0; si < mids.length; si++) {
                var el = mids[si];
                var cx = el.offsetLeft + el.offsetWidth * 0.5;
                var dist = Math.abs(cx - center);
                if (dist < bestDist) {
                    bestDist = dist;
                    bestIdx = si;
                }
            }
            return bestIdx;
        }

        /** Índice en la tira central; si el centro visual es un clon, lo resuelve por aria-label. */
        function spotlightActiveOriginalIndex() {
            var mids = originalsInTrack(track);
            if (!mids.length) {
                return 0;
            }
            var centered = track.querySelector('.familias-spotlight-card.is-centered');
            if (centered) {
                var label = centered.getAttribute('aria-label');
                for (var i = 0; i < mids.length; i++) {
                    if (mids[i].getAttribute('aria-label') === label) {
                        return i;
                    }
                }
            }
            return spotlightNearestOriginalIndex();
        }

        /** Tarjetas en orden DOM: prepend clones + centro + append clones (solo en bucle). */
        function spotlightRowCardsOrdered() {
            return Array.prototype.slice.call(track.querySelectorAll(':scope > .catalog-carousel-card'));
        }

        /**
         * @param {HTMLElement | null | undefined} cardEl
         * @param {'auto' | 'smooth'} behavior
         */
        function spotlightScrollCardIntoViewCenter(cardEl, behavior) {
            if (!cardEl) {
                return false;
            }
            var vw = track.clientWidth;
            var raw = Math.round(cardEl.offsetLeft + cardEl.offsetWidth * 0.5 - vw * 0.5);
            if (
                loopActive &&
                isFamiliaSpotlight &&
                track.classList.contains('familias-spotlight-track')
            ) {
                if (behavior === 'smooth' && !reduceMotion) {
                    spotlightSmoothScrolling = true;
                    spotlightDeferLoopNormalize = true;
                }
                track.scrollTo({ left: Math.max(0, raw), behavior: behavior });
                return true;
            }
            var mx = maxScrollLeft();
            track.scrollTo({ left: Math.max(0, Math.min(mx, raw)), behavior: behavior });
            return true;
        }

        /**
         * Tramo central por índice (Familia una sola tira / arranque lineal): respeta clamp a maxScrollLeft.
         * @param {number} ix
         * @param {'auto' | 'smooth'} behavior
         */
        function spotlightScrollToOriginalIndex(ix, behavior) {
            var mids = originalsInTrack(track);
            if (!mids.length) {
                return false;
            }
            var clampIx = Math.max(0, Math.min(mids.length - 1, Math.floor(ix)));
            return spotlightScrollCardIntoViewCenter(mids[clampIx], behavior);
        }

        function getStep() {
            if (
                isFamiliaSpotlight &&
                track.classList.contains('familias-spotlight-track')
            ) {
                var spotlightStep = getSpotlightArrowStepPx();
                if (spotlightStep > 0) {
                    return spotlightStep;
                }
            }
            var card =
                track.querySelector('.catalog-carousel-card:not([data-carousel-clone])') ||
                track.querySelector('.catalog-carousel-card');
            if (!card) {
                return 0;
            }
            var gap = 0;
            var cs = window.getComputedStyle(track);
            var g = cs.columnGap || cs.gap;
            if (g) {
                var parsed = parseFloat(g);
                if (!isNaN(parsed)) {
                    gap = parsed;
                }
            }
            return Math.round(card.offsetWidth + gap);
        }

        /**
         * Alinea scrollLeft para centrar físicamente la tarjeta Familia más cercana al medio del viewport
         * (corrige estado “a medias” cuando no hay snap y hay solape entre cards).
         */
        function snapSpotlightTrackToNearestCardCenter(instantSnap) {
            if (!isFamiliaSpotlight) {
                return;
            }
            /**
             * Hay que considerar clones y originales: el scroll puede estar en la tira
             * clonada (scrollLeft ≪ cycleWidth); si sólo miramos originales, el centro del
             * viewport queda a la izquierda de todos ellos y el «más cercano» es siempre
             * la primera tarjeta del tramo central → efecto «vuelve al inicio».
             *
             * Con bucle: no hagas clamp contra maxScrollLeft (antes empujaba al borde
             * cuando el mejor match era una copia cercana al final).
             */
            var cardNodes = track.querySelectorAll(':scope > .familias-spotlight-card');
            if (!cardNodes.length) {
                return;
            }
            var vw = track.clientWidth;
            var sl = track.scrollLeft;
            var viewportCenterPx = sl + vw / 2;

            var best = null;
            var bestDx = Infinity;
            for (var i = 0; i < cardNodes.length; i++) {
                var c = cardNodes[i];
                var cx = c.offsetLeft + c.offsetWidth * 0.5;
                var d = Math.abs(cx - viewportCenterPx);
                if (d < bestDx) {
                    bestDx = d;
                    best = c;
                }
            }
            if (!best) {
                return;
            }
            var targetSl = Math.round(best.offsetLeft + best.offsetWidth * 0.5 - vw * 0.5);
            /** En modo bucle, no limitar aquí por maxScrollRight: igual normalizeLoopPosition reubica dentro del tramo medio. */
            if (!loopActive) {
                var mx = maxScrollLeft();
                targetSl = Math.max(0, Math.min(mx, targetSl));
            } else {
                targetSl = Math.max(0, targetSl);
            }
            if (Math.abs(targetSl - sl) <= SCROLL_EDGE_EPS) {
                return;
            }
            track.scrollTo({
                left: targetSl,
                behavior: instantSnap || reduceMotion ? 'auto' : scrollBehaviorStep(),
            });
        }

        function maxScrollLeft() {
            return Math.max(0, track.scrollWidth - track.clientWidth);
        }

        function normalizeLoopPosition() {
            if (!loopActive || cycleWidth <= 0 || normalizing) {
                return;
            }
            if (
                isFamiliaSpotlight &&
                (spotlightDeferLoopNormalize || spotlightSmoothScrolling)
            ) {
                return;
            }
            var sl = track.scrollLeft;
            if (sl < cycleWidth - SCROLL_EDGE_EPS) {
                normalizing = true;
                track.scrollLeft = sl + cycleWidth;
                normalizing = false;
            } else if (sl > 2 * cycleWidth - SCROLL_EDGE_EPS) {
                normalizing = true;
                track.scrollLeft = sl - cycleWidth;
                normalizing = false;
            }
        }

        /** Tras detenerse: si la tarjeta centrada es un clon, salta al equivalente en la tira central (sin animación). */
        function normalizeSpotlightLoopSilent() {
            if (!loopActive || !isFamiliaSpotlight || normalizing) {
                return;
            }
            var centered = track.querySelector('.familias-spotlight-card.is-centered');
            if (!centered || !centered.hasAttribute('data-carousel-clone')) {
                return;
            }
            var label = centered.getAttribute('aria-label');
            var mids = originalsInTrack(track);
            var twin = null;
            for (var ti = 0; ti < mids.length; ti++) {
                if (mids[ti].getAttribute('aria-label') === label) {
                    twin = mids[ti];
                    break;
                }
            }
            if (!twin) {
                return;
            }
            var vw = track.clientWidth;
            var targetSl = Math.round(twin.offsetLeft + twin.offsetWidth * 0.5 - vw * 0.5);
            if (Math.abs(targetSl - track.scrollLeft) <= SCROLL_EDGE_EPS) {
                return;
            }
            normalizing = true;
            track.scrollLeft = targetSl;
            normalizing = false;
        }

        function remeasureCycleAndClamp() {
            if (!loopActive) {
                return;
            }
            refreshLoopMetrics();
            if (cycleWidth <= 0) {
                return;
            }
            var sl = track.scrollLeft;
            var guard = 0;
            var minSl = loopStripStart - SCROLL_EDGE_EPS;
            var maxSl = loopStripStart + cycleWidth + cycleWidth - SCROLL_EDGE_EPS;
            while (sl < minSl && guard++ < 12) {
                sl += cycleWidth;
            }
            guard = 0;
            while (sl > maxSl && guard++ < 12) {
                sl -= cycleWidth;
            }
            track.scrollLeft = sl;
        }

        function updateButtons() {
            if (loopActive) {
                btnPrev.disabled = false;
                btnNext.disabled = false;
                return;
            }
            if (isFamiliaSpotlight && track.classList.contains('familias-spotlight-track')) {
                var mSpot = originalsInTrack(track);
                if (mSpot.length <= 1) {
                    btnPrev.disabled = true;
                    btnNext.disabled = true;
                    return;
                }
                var ixBtn = spotlightNearestOriginalIndex();
                btnPrev.disabled = ixBtn <= 0;
                btnNext.disabled = ixBtn >= mSpot.length - 1;
                return;
            }
            var maxLeft = maxScrollLeft();
            var cards = track.querySelectorAll('.catalog-carousel-card:not([data-carousel-clone])').length;
            var noWheel = cards <= 1 || maxLeft < SCROLL_EDGE_EPS;
            btnPrev.disabled = noWheel;
            btnNext.disabled = noWheel;
        }

        function updateCenteredCard() {
            if (!track.classList.contains('familias-spotlight-track')) {
                return;
            }
            var cards = track.querySelectorAll('.familias-spotlight-card');
            if (!cards.length) {
                return;
            }
            var trackRect = track.getBoundingClientRect();
            var centerX = trackRect.left + trackRect.width / 2;
            var nearest = null;
            var nearestDist = Infinity;

            cards.forEach(function (card) {
                var rect = card.getBoundingClientRect();
                var cardCenter = rect.left + rect.width / 2;
                var d = Math.abs(cardCenter - centerX);
                if (d < nearestDist) {
                    nearestDist = d;
                    nearest = card;
                }
            });

            cards.forEach(function (card) {
                var isCenter = card === nearest;
                card.classList.toggle('is-centered', isCenter);
                var scale = isCenter ? 1 : 0.92;
                var opacity = isCenter ? 1 : 0.55;
                card.style.setProperty('--cf-scale', scale.toFixed(3));
                card.style.setProperty('--cf-opacity', opacity.toFixed(3));
            });

            updateSpotlightDots();
        }

        function initSpotlightDots() {
            if (!dotsWrap) {
                return;
            }
            dotsWrap.innerHTML = '';
            var mids = originalsInTrack(track);
            if (mids.length <= 1) {
                dotsWrap.hidden = true;
                return;
            }
            dotsWrap.hidden = false;
            mids.forEach(function (card, ix) {
                var label = card.getAttribute('aria-label') || ('Familia ' + (ix + 1));
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'familias-spotlight-dot';
                btn.setAttribute('role', 'tab');
                btn.setAttribute('aria-label', label);
                btn.setAttribute('aria-selected', ix === 0 ? 'true' : 'false');
                if (ix === 0) {
                    btn.classList.add('is-active');
                }
                btn.addEventListener('click', function () {
                    var beh = scrollBehaviorStep();
                    if (!reduceMotion && beh === 'smooth' && loopActive) {
                        spotlightDeferLoopNormalize = true;
                        spotlightSmoothScrolling = true;
                    }
                    spotlightScrollToOriginalIndex(ix, beh);
                    if (reduceMotion) {
                        window.requestAnimationFrame(function () {
                            finishSpotlightScroll();
                        });
                    }
                });
                dotsWrap.appendChild(btn);
            });
        }

        function updateSpotlightDots() {
            if (!dotsWrap || dotsWrap.hidden) {
                return;
            }
            var ix = spotlightActiveOriginalIndex();
            var dots = dotsWrap.querySelectorAll('.familias-spotlight-dot');
            dots.forEach(function (dot, di) {
                var active = di === ix;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        var settleTimer = null;
        /**
         * @param {boolean} hardSnapSpotlight sólo true al terminar scroll (scrollend): centra tarjeta Familia.
         *  Si lo llamamos cada 110 ms durante scroll-smooth, el snap instantáneo mata la transición visual.
         */
        function settleAfterScroll(hardSnapSpotlight) {
            if (isFamiliaSpotlight && loopActive) {
                if (hardSnapSpotlight) {
                    normalizeSpotlightLoopSilent();
                }
                updateButtons();
                updateCenteredCard();
                return;
            }
            normalizeLoopPosition();
            if (isFamiliaSpotlight && hardSnapSpotlight) {
                snapSpotlightTrackToNearestCardCenter(true);
                normalizeLoopPosition();
            }
            updateButtons();
            updateCenteredCard();
        }
        function queueSettle(delayMs) {
            if (isFamiliaSpotlight && loopActive) {
                return;
            }
            clearTimeout(settleTimer);
            settleTimer = window.setTimeout(function () {
                settleAfterScroll(false);
            }, delayMs);
        }

        /** Navegadores sin scrollend: un snap cuando el scroll deja de moverse (~220 ms estable) */
        var spotlightIdleSnapTimer = null;
        function scheduleSpotlightIdleSnapHard() {
            if (!isFamiliaSpotlight || !loopActive) {
                return;
            }
            clearTimeout(spotlightIdleSnapTimer);
            spotlightIdleSnapTimer = window.setTimeout(function () {
                spotlightIdleSnapTimer = null;
                finishSpotlightScroll();
            }, 280);
        }

        function scrollByDir(dir) {
            var step = getStep();

            if (isFamiliaSpotlight && track.classList.contains('familias-spotlight-track')) {
                /** Una tarjeta por clic por índice; sin maxScroll contra el siguiente si avanzamos hacia clones (wrap). */
                if (!loopActive) {
                    var midsLin = originalsInTrack(track);
                    if (!midsLin.length) {
                        return;
                    }
                    var maxLf = maxScrollLeft();
                    if (maxLf < SCROLL_EDGE_EPS && midsLin.length > 1) {
                        return;
                    }
                var iFromLin = spotlightNearestOriginalIndex();
                var iToLin = Math.max(0, Math.min(midsLin.length - 1, iFromLin + dir));
                    spotlightScrollToOriginalIndex(iToLin, scrollBehaviorStep());
                    if (reduceMotion) {
                        window.requestAnimationFrame(function () {
                            settleAfterScroll(true);
                        });
                    }
                    return;
                }

                var midsFam = originalsInTrack(track);
                var nFam = midsFam.length;
                if (!nFam) {
                    return;
                }
                var iFromInf = spotlightActiveOriginalIndex();
                var iToInf = (iFromInf + dir + nFam) % nFam;
                var row = spotlightRowCardsOrdered();
                if (row.length !== nFam * 3) {
                    spotlightScrollToOriginalIndex(Math.max(0, Math.min(nFam - 1, iToInf)), scrollBehaviorStep());
                    if (!reduceMotion && scrollBehaviorStep() === 'smooth') {
                        spotlightDeferLoopNormalize = true;
                    }
                    if (reduceMotion) {
                        window.requestAnimationFrame(function () {
                            finishSpotlightScroll();
                        });
                    }
                    return;
                }
                var wrapFw = dir === 1 && iFromInf === nFam - 1;
                var wrapBk = dir === -1 && iFromInf === 0;
                /** Orden DOM: [0..n-1] clones prepend, [n..2n-1] centrales, [2n..3n-1] clones append */
                var targetCard = wrapFw
                    ? /** @type {HTMLElement} */ (row[2 * nFam])
                    : wrapBk
                      ? /** @type {HTMLElement} */ (row[nFam - 1])
                      : /** @type {HTMLElement} */ (midsFam[iToInf]);
                var behInf = scrollBehaviorStep();
                if (!reduceMotion && behInf === 'smooth') {
                    spotlightDeferLoopNormalize = true;
                    spotlightSmoothScrolling = true;
                }
                spotlightScrollCardIntoViewCenter(targetCard, behInf);
                if (reduceMotion) {
                    window.requestAnimationFrame(function () {
                        finishSpotlightScroll();
                    });
                }
                return;
            }

            if (step <= 0) {
                return;
            }
            if (!loopActive) {
                var maxLeft = maxScrollLeft();
                if (maxLeft < SCROLL_EDGE_EPS) {
                    return;
                }
            }
            var nextLeft = Math.round(track.scrollLeft + dir * step);
            track.scrollTo({ left: nextLeft, behavior: scrollBehaviorStep() });
            if (reduceMotion) {
                window.requestAnimationFrame(function () {
                    settleAfterScroll(true);
                });
            }
        }

        function setInitialLoopScroll() {
            if (!loopActive || cycleWidth <= 0) {
                return;
            }
            track.scrollLeft = loopStripStart > 0 ? loopStripStart : cycleWidth;
        }

        btnPrev.addEventListener('click', function () {
            if (!isFamiliaSpotlight) {
                interruptMarqueeFromUser();
            }
            scrollByDir(-1);
        });

        btnNext.addEventListener('click', function () {
            if (!isFamiliaSpotlight) {
                interruptMarqueeFromUser();
            }
            scrollByDir(1);
        });

        track.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                if (!isFamiliaSpotlight) {
                    interruptMarqueeFromUser();
                }
                scrollByDir(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                if (!isFamiliaSpotlight) {
                    interruptMarqueeFromUser();
                }
                scrollByDir(1);
            }
        });

        if (!isFamiliaSpotlight) {
            track.addEventListener('pointerdown', interruptMarqueeFromUser, { passive: true });
            track.addEventListener('wheel', interruptMarqueeFromUser, { passive: true });
        }

        function finishSpotlightScroll() {
            spotlightSmoothScrolling = false;
            spotlightDeferLoopNormalize = false;
            clearTimeout(settleTimer);
            clearTimeout(spotlightIdleSnapTimer);
            spotlightIdleSnapTimer = null;
            settleAfterScroll(true);
        }

        var scrollTicking = false;
        track.addEventListener(
            'scroll',
            function () {
                if (!scrollTicking) {
                    scrollTicking = true;
                    window.requestAnimationFrame(function () {
                        if (!isFamiliaSpotlight) {
                            normalizeLoopPosition();
                        }
                        updateButtons();
                        if (track.classList.contains('familias-spotlight-track')) {
                            updateCenteredCard();
                        }
                        scrollTicking = false;
                    });
                }
                if (!isFamiliaSpotlight) {
                    queueSettle(110);
                }
                if (isFamiliaSpotlight && loopActive && !spotlightSmoothScrolling) {
                    scheduleSpotlightIdleSnapHard();
                }
            },
            { passive: true }
        );

        try {
            track.addEventListener(
                'scrollend',
                function () {
                    if (isFamiliaSpotlight && loopActive) {
                        finishSpotlightScroll();
                        return;
                    }
                    clearTimeout(settleTimer);
                    clearTimeout(spotlightIdleSnapTimer);
                    spotlightIdleSnapTimer = null;
                    if (isFamiliaSpotlight) {
                        spotlightDeferLoopNormalize = false;
                    }
                    settleAfterScroll(true);
                },
                { passive: true }
            );
        } catch (_eScrollEnd) {
            /* Navegadores sin scrollend: idle timer abajo */
        }

        document.addEventListener('visibilitychange', function () {
            if (typeof document.hidden === 'undefined') {
                return;
            }
            if (document.hidden) {
                stopMarqueeRaf();
            } else if (marqueeWantsRun && !marqueeDisabledByUser && loopActive && !reduceMotion) {
                startMarquee();
            }
        });

        window.addEventListener('resize', function () {
            remeasureCycleAndClamp();
            updateButtons();
            updateCenteredCard();
        });

        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(function () {
                remeasureCycleAndClamp();
                updateButtons();
                updateCenteredCard();
            });
            ro.observe(track);
        }

        if (loopActive) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    remeasureCycleAndClamp();
                    setInitialLoopScroll();
                    if (isFamiliaSpotlight) {
                        initSpotlightDots();
                        spotlightScrollToOriginalIndex(0, 'auto');
                        normalizeSpotlightLoopSilent();
                    }
                    updateButtons();
                    updateCenteredCard();
                    startMarquee();
                });
            });
        } else {
            updateButtons();
            if (track.classList.contains('familias-spotlight-track')) {
                initSpotlightDots();
                spotlightScrollToOriginalIndex(0, 'auto');
            }
            updateCenteredCard();
        }
    }

    var catalogRowCounter = 0;
    roots.forEach(function (root) {
        var track = root.querySelector('.catalog-carousel-track');
        var spotlight = !!(track && track.classList.contains('familias-spotlight-track'));
        var rowIx = 0;
        if (!spotlight) {
            rowIx = catalogRowCounter++;
        }
        initCarousel(root, spotlight, rowIx);
    });
})();
