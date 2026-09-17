{{-- Live search results.

     Printed once per page by @searchScripts. Dependency-free and inline, like
     the builder runtime: without it every search form still submits normally.

     Enhances every form carrying data-radius-search. A theme can change it at
     three depths:

       1. Look: set the --radius-search-* custom properties below, or style the
          .radius-search-* classes, in the theme's stylesheet.
       2. Markup: replace window.RadiusSearch.render(data, context), or listen
          for the cancelable `radius:search:results` event on the form and call
          event.preventDefault() after drawing into event.detail.context.panel.
       3. Everything: ship views/search/script.blade.php in the theme.

     Events, all dispatched on the form and bubbling:
       radius:search:results  detail: { data, context }   cancelable
       radius:search:select   detail: { url, element }
       radius:search:close    detail: { context } --}}
<style>
    .radius-search-panel {
        --_bg: var(--radius-search-bg, #fff);
        --_fg: var(--radius-search-fg, #0f172a);
        --_muted: var(--radius-search-muted, #64748b);
        --_border: var(--radius-search-border, #e2e8f0);
        --_active: var(--radius-search-active, #f1f5f9);
        --_accent: var(--radius-search-accent, var(--brand, #2563eb));
        position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 60;
        max-height: min(70vh, 480px); overflow-y: auto; overscroll-behavior: contain;
        background: var(--_bg); color: var(--_fg);
        border: 1px solid var(--_border); border-radius: var(--radius-search-radius, 12px);
        box-shadow: var(--radius-search-shadow, 0 12px 32px rgb(15 23 42 / 14%));
        padding: 6px; text-align: left; font-size: 14px; line-height: 1.4;
    }
    .radius-search-panel[hidden] { display: none; }
    .radius-search-group + .radius-search-group { margin-top: 6px; padding-top: 6px; border-top: 1px solid var(--_border); }
    .radius-search-heading {
        display: flex; justify-content: space-between; align-items: baseline; gap: 12px;
        padding: 6px 10px 4px; font-size: 11px; font-weight: 600; letter-spacing: .04em;
        text-transform: uppercase; color: var(--_muted);
    }
    .radius-search-item, .radius-search-all, .radius-search-footer {
        display: flex; align-items: center; gap: 10px; padding: 8px 10px;
        border-radius: 8px; color: inherit; text-decoration: none; cursor: pointer;
    }
    .radius-search-all { display: inline-flex; padding: 2px 6px; text-transform: none; letter-spacing: 0; font-weight: 500; color: var(--_accent); }
    .radius-search-item[aria-selected="true"], .radius-search-item:hover,
    .radius-search-all[aria-selected="true"], .radius-search-all:hover,
    .radius-search-footer[aria-selected="true"], .radius-search-footer:hover { background: var(--_active); }
    .radius-search-thumb { width: 40px; height: 40px; flex: none; border-radius: 6px; object-fit: cover; background: var(--_active); }
    .radius-search-body { min-width: 0; flex: 1; }
    .radius-search-title { display: block; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .radius-search-title mark { background: none; color: inherit; font-weight: 700; }
    .radius-search-excerpt { display: block; margin-top: 2px; font-size: 12px; color: var(--_muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .radius-search-meta { flex: none; font-size: 13px; color: var(--_muted); }
    .radius-search-empty { padding: 14px 10px; color: var(--_muted); }
    .radius-search-footer { justify-content: center; margin-top: 6px; border-top: 1px solid var(--_border); border-radius: 0 0 8px 8px; font-weight: 500; color: var(--_accent); }
    form.is-searching [name="q"] { cursor: progress; }
</style>
<script>
(function () {
    'use strict';

    var R = window.RadiusSearch = window.RadiusSearch || {};
    var defaults = @json($config);

    R.config = Object.assign({}, defaults, R.config || {});
    R.config.labels = Object.assign({}, defaults.labels, (R.config && R.config.labels) || {});

    if (R.booted) return;
    R.booted = true;

    var uid = 0;
    var cache = {};

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text != null) node.textContent = text;
        return node;
    }

    // Only site-relative or http(s) links are followed; anything else in a
    // result (a javascript: URL typed into an image field) is dropped.
    function safeUrl(url) {
        return typeof url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(url) ? url : null;
    }

    function label(key, replacements) {
        var text = String(R.config.labels[key] || '');
        Object.keys(replacements || {}).forEach(function (name) {
            text = text.split(':' + name).join(replacements[name]);
        });
        return text;
    }

    /** Appends `text` to `node` with the query's words wrapped in <mark>. */
    function highlight(node, text, query) {
        var words = query.split(/\s+/).filter(function (w) { return w.length > 1; })
            .map(function (w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });

        if (!words.length) { node.textContent = text; return node; }

        var parts = String(text).split(new RegExp('(' + words.join('|') + ')', 'ig'));
        parts.forEach(function (part, i) {
            node.appendChild(i % 2 ? el('mark', null, part) : document.createTextNode(part));
        });
        return node;
    }

    function option(tag, className, href) {
        var node = el(tag, className);
        node.setAttribute('role', 'option');
        node.setAttribute('href', href);
        return node;
    }

    /**
     * Draws results into context.panel. Replace it to change the markup:
     *     window.RadiusSearch.render = function (data, context) { ... };
     * Anything with role="option" and an href joins the keyboard navigation.
     */
    R.render = R.render || function (data, context) {
        var panel = context.panel;

        if (!data.groups || !data.groups.length) {
            panel.appendChild(el('div', 'radius-search-empty', label('noResults', { query: context.query })));
        }

        (data.groups || []).forEach(function (group) {
            var section = el('div', 'radius-search-group');
            section.setAttribute('role', 'group');
            section.setAttribute('aria-label', group.label);

            var heading = el('div', 'radius-search-heading');
            heading.appendChild(el('span', null, group.label));

            if (group.total > group.items.length && safeUrl(group.url)) {
                var all = option('a', 'radius-search-all', group.url);
                all.textContent = label('seeAll', { count: group.total });
                heading.appendChild(all);
            }

            section.appendChild(heading);

            group.items.forEach(function (item) {
                var url = safeUrl(item.url);
                if (!url) return;

                var link = option('a', 'radius-search-item', url);
                var image = safeUrl(item.image);

                if (image) {
                    var img = el('img', 'radius-search-thumb');
                    img.src = image;
                    img.alt = '';
                    img.loading = 'lazy';
                    link.appendChild(img);
                }

                var body = el('span', 'radius-search-body');
                body.appendChild(highlight(el('span', 'radius-search-title'), item.title, context.query));
                if (item.excerpt) body.appendChild(el('span', 'radius-search-excerpt', item.excerpt));
                link.appendChild(body);

                if (item.meta) link.appendChild(el('span', 'radius-search-meta', item.meta));

                section.appendChild(link);
            });

            panel.appendChild(section);
        });

        if (context.type === 'all' && safeUrl(data.url) && data.groups && data.groups.length) {
            var footer = option('a', 'radius-search-footer', data.url);
            footer.textContent = label('viewAll');
            panel.appendChild(footer);
        }
    };

    function enhance(form) {
        if (form.__radiusSearch || form.getAttribute('data-radius-instant') === 'off') return;

        var input = form.querySelector('input[name="q"]');
        if (!input) return;

        form.__radiusSearch = true;

        var type = form.getAttribute('data-radius-search') || 'all';
        var id = 'radius-search-' + (++uid);
        var panel = el('div', 'radius-search-panel');
        var timer = null, controller = null, active = -1, lastQuery = null;
        var context = { form: form, input: input, panel: panel, type: type, query: '', config: R.config };

        panel.id = id;
        panel.hidden = true;
        panel.setAttribute('role', 'listbox');

        if (getComputedStyle(form).position === 'static') form.style.position = 'relative';
        form.appendChild(panel);

        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', id);

        function options() {
            return panel.querySelectorAll('[role="option"]');
        }

        function setActive(index) {
            var items = options();
            active = index;

            Array.prototype.forEach.call(items, function (item, i) {
                item.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });

            if (index >= 0 && items[index]) {
                input.setAttribute('aria-activedescendant', items[index].id);
                items[index].scrollIntoView({ block: 'nearest' });
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function open() {
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function close() {
            if (panel.hidden) return;
            panel.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            setActive(-1);
            form.dispatchEvent(new CustomEvent('radius:search:close', { bubbles: true, detail: { context: context } }));
        }

        function show(data, query) {
            context.query = query;
            active = -1;
            panel.innerHTML = '';

            var event = new CustomEvent('radius:search:results', {
                bubbles: true, cancelable: true, detail: { data: data, context: context }
            });

            if (form.dispatchEvent(event)) R.render(data, context);

            Array.prototype.forEach.call(options(), function (item, i) {
                if (!item.id) item.id = id + '-option-' + i;
                item.setAttribute('aria-selected', 'false');
            });

            open();
        }

        function load(query) {
            var key = type + '|' + query;

            if (cache[key]) { show(cache[key], query); return; }
            if (controller) controller.abort();

            controller = window.AbortController ? new AbortController() : null;
            form.classList.add('is-searching');

            var endpoint = R.config.endpoint;
            var url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(query)
                + (type !== 'all' ? '&type=' + encodeURIComponent(type) : '');

            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Search failed with ' + response.status);
                    return response.json();
                })
                .then(function (data) {
                    cache[key] = data;
                    form.classList.remove('is-searching');
                    if (input.value.trim() === query) show(data, query);
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') return;
                    form.classList.remove('is-searching');
                    close();
                });
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();
            clearTimeout(timer);

            if (query.length < R.config.minChars) {
                lastQuery = null;
                if (controller) controller.abort();
                form.classList.remove('is-searching');
                close();
                return;
            }

            timer = setTimeout(function () {
                if (query === lastQuery) { open(); return; }
                lastQuery = query;
                load(query);
            }, R.config.delay);
        });

        input.addEventListener('focus', function () {
            if (panel.childNodes.length && input.value.trim() === lastQuery) open();
        });

        input.addEventListener('keydown', function (event) {
            var items = options();

            if (event.key === 'ArrowDown') {
                if (!items.length) return;
                event.preventDefault();
                open();
                setActive(active + 1 >= items.length ? 0 : active + 1);
            } else if (event.key === 'ArrowUp') {
                if (!items.length) return;
                event.preventDefault();
                setActive(active - 1 < 0 ? items.length - 1 : active - 1);
            } else if (event.key === 'Enter') {
                if (!panel.hidden && active >= 0 && items[active]) {
                    event.preventDefault();
                    items[active].click();
                }
            } else if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                close();
            }
        });

        panel.addEventListener('click', function (event) {
            var item = event.target.closest('[role="option"]');
            if (!item) return;
            form.dispatchEvent(new CustomEvent('radius:search:select', {
                bubbles: true, detail: { url: item.getAttribute('href'), element: item }
            }));
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) close();
        });
    }

    /** Enhances forms added after the page loaded, e.g. inside a drawer. */
    R.enhance = function (root) {
        (root || document).querySelectorAll('form[data-radius-search]').forEach(enhance);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { R.enhance(); });
    } else {
        R.enhance();
    }
})();
</script>
