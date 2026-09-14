/* ==========================================================================
   Storefront — theme runtime
   --------------------------------------------------------------------------
   Plain ES5-compatible DOM code with no dependencies. The CMS does not load
   Alpine on the public site, so every interaction here is wired by hand and
   degrades to working HTML when JavaScript is unavailable.
   ========================================================================== */

(function () {
    'use strict';

    var doc = document;

    function $(selector, scope) { return (scope || doc).querySelector(selector); }
    function $$(selector, scope) {
        return Array.prototype.slice.call((scope || doc).querySelectorAll(selector));
    }

    /* Mega menu ---------------------------------------------------------- */

    function initMegaMenu() {
        var items = $$('[data-mega]');
        if (!items.length) return;

        var closeTimer = null;

        function closeAll() {
            items.forEach(function (item) { item.classList.remove('is-open'); });
        }

        items.forEach(function (item) {
            var link = $('.sf-nav__link', item);

            item.addEventListener('mouseenter', function () {
                window.clearTimeout(closeTimer);
                closeAll();
                item.classList.add('is-open');
            });

            item.addEventListener('mouseleave', function () {
                // A short grace period stops the panel snapping shut when the
                // pointer crosses the gap between the link and the panel.
                closeTimer = window.setTimeout(closeAll, 120);
            });

            // Keyboard users get the panel on focus, and Escape closes it.
            if (link) {
                link.addEventListener('focus', function () {
                    closeAll();
                    item.classList.add('is-open');
                });
            }
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeAll();
        });

        doc.addEventListener('click', function (event) {
            if (!event.target.closest('[data-mega]')) closeAll();
        });
    }

    /* Drawers ------------------------------------------------------------ */

    var openDrawer = null;

    function closeDrawer() {
        if (!openDrawer) return;
        openDrawer.classList.remove('is-open');
        var scrim = $('[data-scrim]');
        if (scrim) scrim.classList.remove('is-open');
        doc.body.style.overflow = '';
        openDrawer = null;
    }

    function showDrawer(drawer) {
        if (!drawer) return;
        closeDrawer();
        drawer.classList.add('is-open');
        var scrim = $('[data-scrim]');
        if (scrim) scrim.classList.add('is-open');
        doc.body.style.overflow = 'hidden';
        openDrawer = drawer;
    }

    function initDrawers() {
        $$('[data-drawer-open]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                showDrawer($('#' + button.getAttribute('data-drawer-open')));
            });
        });

        $$('[data-drawer-close]').forEach(function (button) {
            button.addEventListener('click', closeDrawer);
        });

        var scrim = $('[data-scrim]');
        if (scrim) scrim.addEventListener('click', closeDrawer);

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeDrawer();
        });

        // Collapsible groups inside the mobile navigation.
        $$('[data-mnav-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var panel = button.nextElementSibling;
                var open = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', open ? 'false' : 'true');
                if (panel) panel.classList.toggle('is-open', !open);
            });
        });
    }

    /* Horizontal rails --------------------------------------------------- */

    function initRails() {
        $$('[data-rail]').forEach(function (rail) {
            var group = rail.closest('[data-rail-group]') || rail.parentNode;
            var prev = $('[data-rail-prev]', group);
            var next = $('[data-rail-next]', group);
            if (!prev && !next) return;

            function step() {
                // Scroll by whole cards so a row never stops mid-product.
                var card = rail.firstElementChild;
                var width = card ? card.getBoundingClientRect().width + 14 : 220;
                return Math.max(width, Math.floor(rail.clientWidth * 0.8 / width) * width);
            }

            function sync() {
                var max = rail.scrollWidth - rail.clientWidth - 2;
                if (prev) prev.disabled = rail.scrollLeft <= 2;
                if (next) next.disabled = rail.scrollLeft >= max;
            }

            if (prev) prev.addEventListener('click', function () { rail.scrollLeft -= step(); });
            if (next) next.addEventListener('click', function () { rail.scrollLeft += step(); });

            rail.addEventListener('scroll', sync, { passive: true });
            window.addEventListener('resize', sync);
            sync();
        });
    }

    /* Accordions --------------------------------------------------------- */

    function initAccordions() {
        $$('[data-acc-btn]').forEach(function (button) {
            button.addEventListener('click', function () {
                var panel = doc.getElementById(button.getAttribute('aria-controls'));
                var open = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', open ? 'false' : 'true');
                if (panel) panel.classList.toggle('is-open', !open);
            });
        });
    }

    /* Product gallery ---------------------------------------------------- */

    function initGallery() {
        var stage = $('[data-gallery-stage]');
        if (!stage) return;

        $$('[data-gallery-thumb]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                stage.src = thumb.getAttribute('data-full');
                stage.alt = thumb.getAttribute('data-alt') || stage.alt;
                $$('[data-gallery-thumb]').forEach(function (other) {
                    other.classList.toggle('is-active', other === thumb);
                });
            });
        });
    }

    /* Quantity steppers -------------------------------------------------- */

    function initQuantity() {
        $$('[data-qty]').forEach(function (widget) {
            var input = $('input', widget);
            if (!input) return;

            $$('[data-qty-step]', widget).forEach(function (button) {
                button.addEventListener('click', function () {
                    var by = parseInt(button.getAttribute('data-qty-step'), 10) || 1;
                    var min = parseInt(input.getAttribute('min'), 10);
                    var max = parseInt(input.getAttribute('max'), 10);
                    var next = (parseInt(input.value, 10) || 0) + by;

                    if (!isNaN(min) && next < min) next = min;
                    if (!isNaN(max) && next > max) next = max;

                    input.value = next;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        });
    }

    /* Buy now ------------------------------------------------------------ */

    function initBuyNow() {
        $$('[data-buy-now]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var form = button.closest('form');
                var checkout = button.getAttribute('data-buy-now');
                if (!form || !checkout || !window.fetch) return; // plain submit adds to the bag

                event.preventDefault();
                button.disabled = true;

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }).then(function () {
                    window.location.href = checkout;
                }).catch(function () {
                    button.disabled = false;
                    form.submit();
                });
            });
        });
    }

    /* Saved items -------------------------------------------------------- */
    /* Held in this browser only: the CMS has no wishlist table, so nothing is
       sent to the server and nothing leaks between visitors on a shared PC. */

    var STORE_KEY = 'sf.saved.v1';

    function readSaved() {
        try {
            var raw = window.localStorage.getItem(STORE_KEY);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function writeSaved(items) {
        try {
            window.localStorage.setItem(STORE_KEY, JSON.stringify(items.slice(0, 60)));
        } catch (error) {
            /* Private mode, or a full quota. The page still works. */
        }
    }

    function savedIndex(items, id) {
        for (var i = 0; i < items.length; i++) {
            if (String(items[i].id) === String(id)) return i;
        }
        return -1;
    }

    function paintSavedCount(items) {
        var badge = $('[data-saved-count]');
        if (!badge) return;
        badge.textContent = items.length;
        badge.hidden = items.length === 0;
    }

    function paintSavedButtons(items) {
        $$('[data-fav]').forEach(function (button) {
            var on = savedIndex(items, button.getAttribute('data-fav')) !== -1;
            button.classList.toggle('is-on', on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            button.setAttribute('aria-label', on ? 'Remove from saved items' : 'Save for later');
        });
    }

    function paintSavedList(items) {
        var list = $('[data-saved-list]');
        var empty = $('[data-saved-empty]');
        if (!list) return;

        list.textContent = '';

        if (empty) empty.hidden = items.length > 0;

        items.forEach(function (item) {
            var row = doc.createElement('li');
            row.className = 'sf-saved__item';

            if (item.image) {
                var img = doc.createElement('img');
                img.src = item.image;
                img.alt = '';
                img.loading = 'lazy';
                row.appendChild(img);
            }

            var body = doc.createElement('div');

            var link = doc.createElement('a');
            link.className = 'sf-saved__name';
            link.href = item.url || '#';
            link.textContent = item.name || 'Product';
            body.appendChild(link);

            if (item.price) {
                var price = doc.createElement('p');
                price.className = 'sf-saved__price';
                price.textContent = item.price;
                body.appendChild(price);
            }

            var drop = doc.createElement('button');
            drop.type = 'button';
            drop.className = 'sf-saved__drop';
            drop.textContent = 'Remove';
            drop.addEventListener('click', function () { toggleSaved(item.id, null); });
            body.appendChild(drop);

            row.appendChild(body);
            list.appendChild(row);
        });
    }

    function paintSaved(items) {
        paintSavedCount(items);
        paintSavedButtons(items);
        paintSavedList(items);
    }

    function toggleSaved(id, payload) {
        var items = readSaved();
        var at = savedIndex(items, id);

        if (at !== -1) {
            items.splice(at, 1);
        } else if (payload) {
            items.unshift(payload);
        }

        writeSaved(items);
        paintSaved(items);
    }

    function initSaved() {
        $$('[data-fav]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                toggleSaved(button.getAttribute('data-fav'), {
                    id: button.getAttribute('data-fav'),
                    name: button.getAttribute('data-fav-name'),
                    url: button.getAttribute('data-fav-url'),
                    image: button.getAttribute('data-fav-image'),
                    price: button.getAttribute('data-fav-price'),
                });
            });
        });

        paintSaved(readSaved());
    }

    /* Boot --------------------------------------------------------------- */

    function boot() {
        initMegaMenu();
        initDrawers();
        initRails();
        initAccordions();
        initGallery();
        initQuantity();
        initBuyNow();
        initSaved();
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
