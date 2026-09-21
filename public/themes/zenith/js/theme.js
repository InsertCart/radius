/**
 * Zenith Theme JavaScript
 * Dark/light mode switcher, drawers, wishlist & interactions.
 */

(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // 1. Theme Mode (Dark / Light)
    // -------------------------------------------------------------------------
    function initThemeMode() {
        var stored = localStorage.getItem('zenith_theme');
        var html = document.documentElement;

        if (stored === 'dark') {
            html.classList.add('dark');
            html.classList.remove('light');
        } else if (stored === 'light') {
            html.classList.add('light');
            html.classList.remove('dark');
        }

        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var isDark = html.classList.contains('dark') || 
                    (!html.classList.contains('light') && window.matchMedia('(prefers-color-scheme: dark)').matches);

                if (isDark) {
                    html.classList.remove('dark');
                    html.classList.add('light');
                    localStorage.setItem('zenith_theme', 'light');
                } else {
                    html.classList.remove('light');
                    html.classList.add('dark');
                    localStorage.setItem('zenith_theme', 'dark');
                }
            });
        });
    }

    // -------------------------------------------------------------------------
    // 2. Off-canvas Drawers (Mobile Menu & Saved Items)
    // -------------------------------------------------------------------------
    function initDrawers() {
        var backdrop = document.querySelector('.zn-drawer-backdrop');

        function openDrawer(id) {
            var drawer = document.getElementById(id);
            if (!drawer) return;
            drawer.classList.add('is-open');
            if (backdrop) backdrop.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }

        function closeDrawers() {
            document.querySelectorAll('.zn-drawer.is-open').forEach(function (d) {
                d.classList.remove('is-open');
            });
            if (backdrop) backdrop.classList.remove('is-open');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-drawer-open]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var targetId = btn.getAttribute('data-drawer-open');
                openDrawer(targetId);
            });
        });

        document.querySelectorAll('[data-drawer-close]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                closeDrawers();
            });
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeDrawers);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDrawers();
        });
    }

    // -------------------------------------------------------------------------
    // 3. Saved Items (Wishlist) in localStorage
    // -------------------------------------------------------------------------
    function initWishlist() {
        var STORAGE_KEY = 'zenith_wishlist';

        function getWishlist() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
            } catch (e) {
                return [];
            }
        }

        function saveWishlist(items) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
            updateBadges();
            renderSavedDrawer();
        }

        function updateBadges() {
            var count = getWishlist().length;
            document.querySelectorAll('[data-saved-count]').forEach(function (el) {
                el.textContent = count;
                el.hidden = count === 0;
            });

            var ids = getWishlist().map(function (item) { return String(item.id); });
            document.querySelectorAll('[data-fav]').forEach(function (btn) {
                var id = String(btn.getAttribute('data-fav'));
                var isFav = ids.indexOf(id) !== -1;
                btn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
            });
        }

        function renderSavedDrawer() {
            var container = document.querySelector('[data-saved-items-container]');
            if (!container) return;

            var items = getWishlist();
            if (items.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px 10px; color: var(--zn-muted);">' +
                    '<p style="font-size: 15px; margin-bottom: 8px;">Your saved list is empty.</p>' +
                    '<p style="font-size: 13px;">Click the heart icon on any product to save it for later.</p>' +
                    '</div>';
                return;
            }

            var html = '<div style="display: flex; flex-direction: column; gap: 14px;">';
            items.forEach(function (item) {
                html += '<div style="display: flex; gap: 12px; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--zn-border);">' +
                    '<img src="' + (item.image || '') + '" alt="" style="width: 54px; height: 54px; object-fit: cover; border-radius: var(--zn-radius-sm);">' +
                    '<div style="flex: 1; min-width: 0;">' +
                    '<a href="' + item.url + '" style="font-size: 13.5px; font-weight: 600; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + item.name + '</a>' +
                    '<span style="font-size: 12.5px; color: var(--zn-muted); font-weight: 700;">' + item.price + '</span>' +
                    '</div>' +
                    '<button type="button" data-remove-fav="' + item.id + '" style="background: none; border: none; color: var(--zn-muted); cursor: pointer; padding: 4px;" aria-label="Remove item">&times;</button>' +
                    '</div>';
            });
            html += '</div>';
            container.innerHTML = html;

            container.querySelectorAll('[data-remove-fav]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var removeId = btn.getAttribute('data-remove-fav');
                    var updated = getWishlist().filter(function (i) { return String(i.id) !== String(removeId); });
                    saveWishlist(updated);
                });
            });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-fav]');
            if (!btn) return;
            e.preventDefault();

            var id = btn.getAttribute('data-fav');
            var name = btn.getAttribute('data-fav-name');
            var url = btn.getAttribute('data-fav-url');
            var image = btn.getAttribute('data-fav-image');
            var price = btn.getAttribute('data-fav-price');

            var items = getWishlist();
            var index = items.findIndex(function (i) { return String(i.id) === String(id); });

            if (index >= 0) {
                items.splice(index, 1);
            } else {
                items.push({ id: id, name: name, url: url, image: image, price: price });
            }

            saveWishlist(items);
        });

        updateBadges();
        renderSavedDrawer();
    }

    // -------------------------------------------------------------------------
    // 4. Accordions
    // -------------------------------------------------------------------------
    function initAccordions() {
        document.querySelectorAll('[data-accordion-btn]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var content = btn.nextElementSibling;
                var isOpen = content && !content.hidden;

                if (content) {
                    content.hidden = isOpen;
                    btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                    var icon = btn.querySelector('.zn-accordion__icon');
                    if (icon) {
                        icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                    }
                }
            });
        });
    }

    // -------------------------------------------------------------------------
    // 5. Product Gallery Thumbnail Switching
    // -------------------------------------------------------------------------
    function initGallery() {
        var mainImg = document.querySelector('[data-gallery-main]');
        if (!mainImg) return;

        document.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var src = thumb.getAttribute('data-src');
                if (src) {
                    mainImg.src = src;
                    document.querySelectorAll('[data-gallery-thumb]').forEach(function (t) {
                        t.classList.remove('active');
                    });
                    thumb.classList.add('active');
                }
            });
        });
    }

    // Initialize all modules when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initThemeMode();
            initDrawers();
            initWishlist();
            initAccordions();
            initGallery();
        });
    } else {
        initThemeMode();
        initDrawers();
        initWishlist();
        initAccordions();
        initGallery();
    }
})();
