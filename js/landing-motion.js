/**
 * Motion premium en la landing (PHP SSR): hero, vitrina al scroll, rejillas de catálogo.
 * Requiere GSAP + ScrollTrigger (CDN en index.php). Respeta prefers-reduced-motion.
 */
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || typeof window.gsap === 'undefined' || typeof window.ScrollTrigger === 'undefined') {
        return;
    }

    window.gsap.registerPlugin(window.ScrollTrigger);

    var gsap = window.gsap;

    function heroIntro() {
        var hero = document.querySelector('.hero-graphic');
        if (!hero) {
            return;
        }

        gsap.set('.hero-content-graphic .hero-eyebrow, .hero-content-graphic h2, .hero-content-graphic .hero-sub, .hero-content-graphic .btn-primario', {
            opacity: 0,
            y: 28,
        });

        var tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
        tl.from('.hero-graphic-layer', {
            scale: 1.12,
            opacity: 0,
            duration: 1.15,
        })
            .from(
                '.hero-content-graphic .hero-eyebrow',
                { opacity: 0, y: 22, duration: 0.55 },
                '-=0.65'
            )
            .from(
                '.hero-content-graphic h2',
                { opacity: 0, y: 36, duration: 0.72 },
                '-=0.38'
            )
            .from(
                '.hero-content-graphic .hero-sub',
                { opacity: 0, y: 26, duration: 0.55 },
                '-=0.45'
            )
            .from(
                '.hero-content-graphic .btn-primario',
                { opacity: 0, y: 20, duration: 0.5 },
                '-=0.35'
            );
    }

    function vitrinaScroll() {
        var stage = document.querySelector('.vitrina-stage');
        if (!stage) {
            return;
        }

        gsap.from(stage, {
            scrollTrigger: {
                trigger: stage,
                start: 'top 85%',
                toggleActions: 'play none none none',
            },
            y: 48,
            opacity: 0,
            duration: 0.75,
            ease: 'power3.out',
        });
    }

    function catalogScroll() {
        var groups = gsap.utils.toArray('.catalog-group');
        if (!groups.length) {
            return;
        }

        groups.forEach(function (group) {
            gsap.from(group, {
                scrollTrigger: {
                    trigger: group,
                    start: 'top 86%',
                    toggleActions: 'play none none none',
                },
                y: 30,
                opacity: 0,
                duration: 0.6,
                ease: 'power2.out',
            });
        });
    }

    function sobreScroll() {
        var block = document.querySelector('.sobre-nosotros');
        if (block) {
            gsap.from(block.children, {
                scrollTrigger: {
                    trigger: block,
                    start: 'top 85%',
                    toggleActions: 'play none none none',
                },
                y: 30,
                opacity: 0,
                duration: 0.6,
                stagger: 0.12,
                ease: 'power2.out',
            });
        }

        gsap.utils.toArray('.catalog-promo-stripe').forEach(function (stripe) {
            gsap.from(stripe, {
                scrollTrigger: {
                    trigger: stripe,
                    start: 'top 85%',
                    toggleActions: 'play none none none',
                },
                y: 28,
                opacity: 0,
                duration: 0.62,
                ease: 'power2.out',
            });
        });
    }

    heroIntro();
    vitrinaScroll();
    catalogScroll();
    sobreScroll();

    window.addEventListener('load', function () {
        window.ScrollTrigger.refresh();
    });
})();
