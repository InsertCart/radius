{{-- The front-end runtime for builder output.

     Deliberately small, dependency-free and inline: a visitor should not fetch
     a framework to expand an accordion. Everything degrades - the markup is
     already correct and readable without it, and this only adds behaviour. --}}
<script>
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Accordion ---------------------------------------------------------- */
    document.querySelectorAll('[data-cb-accordion]').forEach(function (root) {
        var single = root.hasAttribute('data-cb-single');

        root.addEventListener('click', function (event) {
            var title = event.target.closest('.cb-accordion__title');
            if (!title || !root.contains(title)) return;

            var panel = document.getElementById(title.getAttribute('aria-controls'));
            var open = title.getAttribute('aria-expanded') === 'true';

            if (single && !open) {
                root.querySelectorAll('.cb-accordion__title[aria-expanded="true"]').forEach(function (other) {
                    other.setAttribute('aria-expanded', 'false');
                    var otherPanel = document.getElementById(other.getAttribute('aria-controls'));
                    if (otherPanel) otherPanel.hidden = true;
                });
            }

            title.setAttribute('aria-expanded', String(!open));
            if (panel) panel.hidden = open;
        });
    });

    /* Tabs --------------------------------------------------------------- */
    document.querySelectorAll('[data-cb-tabs]').forEach(function (root) {
        var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));

        function activate(index) {
            tabs.forEach(function (tab, i) {
                var selected = i === index;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;

                var panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (panel) panel.hidden = !selected;
            });
        }

        root.addEventListener('click', function (event) {
            var tab = event.target.closest('[role="tab"]');
            if (tab) activate(tabs.indexOf(tab));
        });

        // Arrow keys move between tabs, which is what a screen reader user
        // and a keyboard user both expect from a tablist.
        root.addEventListener('keydown', function (event) {
            var current = tabs.indexOf(document.activeElement);
            if (current === -1) return;

            var next = null;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (current + 1) % tabs.length;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (current - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;

            if (next !== null) {
                event.preventDefault();
                tabs[next].focus();
                activate(next);
            }
        });
    });

    /* Mobile menu -------------------------------------------------------- */
    document.querySelectorAll('[data-cb-menu]').forEach(function (root) {
        var toggle = root.querySelector('.cb-menu__toggle');
        if (!toggle) return;

        toggle.addEventListener('click', function () {
            var open = root.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    });

    /* Counters and entrance animations ----------------------------------- */
    var observer = 'IntersectionObserver' in window
        ? new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;

                if (entry.target.hasAttribute('data-cb-counter')) {
                    countUp(entry.target);
                } else {
                    entry.target.classList.add('cb-in');
                }

                observer.unobserve(entry.target);
            });
        }, { threshold: 0.25 })
        : null;

    function countUp(element) {
        var target = element.querySelector('.cb-counter__value');
        if (!target) return;

        var start = parseFloat(element.dataset.start) || 0;
        var end = parseFloat(element.dataset.end) || 0;
        var duration = parseInt(element.dataset.duration, 10) || 1800;
        var separator = element.dataset.separator === '1';

        if (reduceMotion) return;

        var startedAt = null;

        function frame(now) {
            if (!startedAt) startedAt = now;

            var progress = Math.min((now - startedAt) / duration, 1);
            // Ease-out, so the number settles rather than stopping dead.
            var eased = 1 - Math.pow(1 - progress, 3);
            var value = Math.round(start + (end - start) * eased);

            target.textContent = separator ? value.toLocaleString() : value;

            if (progress < 1) requestAnimationFrame(frame);
        }

        requestAnimationFrame(frame);
    }

    if (observer) {
        document.querySelectorAll('[data-cb-counter]').forEach(function (el) { observer.observe(el); });
        document.querySelectorAll('[data-cb-animation]').forEach(function (el) { observer.observe(el); });
    } else {
        // Without IntersectionObserver, show everything rather than hiding it.
        document.querySelectorAll('[data-cb-animation]').forEach(function (el) { el.classList.add('cb-in'); });
    }

    /* Product images ----------------------------------------------------- */
    document.querySelectorAll('[data-cb-pimages]').forEach(function (root) {
        var stage = root.querySelector('[data-cb-pimages-stage]');
        if (!stage) return;

        root.addEventListener('click', function (event) {
            var thumb = event.target.closest('.cb-pimages__thumb');
            if (!thumb || !root.contains(thumb)) return;

            stage.src = thumb.getAttribute('data-full');
            stage.alt = thumb.getAttribute('data-alt') || stage.alt;

            root.querySelectorAll('.cb-pimages__thumb').forEach(function (other) {
                other.classList.toggle('is-active', other === thumb);
            });
        });
    });

    /* Quantity stepper --------------------------------------------------- */
    document.querySelectorAll('[data-cb-qty]').forEach(function (root) {
        var input = root.querySelector('input');
        if (!input) return;

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-step]');
            if (!button) return;

            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || 99;
            var next = (parseInt(input.value, 10) || 0) + (parseInt(button.getAttribute('data-step'), 10) || 0);

            input.value = Math.min(max, Math.max(min, next));
        });
    });

    /* Lightbox ----------------------------------------------------------- */
    document.querySelectorAll('[data-cb-lightbox]').forEach(function (root) {
        root.addEventListener('click', function (event) {
            var link = event.target.closest('[data-cb-lightbox-item]');
            if (!link) return;

            event.preventDefault();
            open(link.getAttribute('href'), link.querySelector('img'));
        });
    });

    function open(src, sourceImage) {
        var box = document.createElement('div');
        box.className = 'cb-lightbox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');

        var image = document.createElement('img');
        image.src = src;
        image.alt = sourceImage ? sourceImage.alt : '';

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'cb-lightbox__close';
        close.innerHTML = '&times;';
        close.setAttribute('aria-label', 'Close');

        function dismiss() {
            box.remove();
            document.removeEventListener('keydown', onKey);
            if (sourceImage) sourceImage.focus();
        }

        function onKey(event) {
            if (event.key === 'Escape') dismiss();
        }

        box.addEventListener('click', function (event) {
            if (event.target === box || event.target === close) dismiss();
        });

        document.addEventListener('keydown', onKey);

        box.append(image, close);
        document.body.appendChild(box);
        close.focus();
    }
}());
</script>
