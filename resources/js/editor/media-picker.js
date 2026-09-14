/**
 * The media picker the rich text editor opens when inserting an image.
 *
 * Choosing is a two-step flow: pick an image, then confirm its alt text before
 * it goes into the content. Alt text describes the image to screen readers and
 * search engines, and asking at the moment of insertion is the only time most
 * people will ever fill it in.
 *
 * Alt text typed here can be saved back to the library, so the next person who
 * uses the same image gets it for free.
 *
 * Lives outside the editor so the editor stays independent of the admin panel:
 * the editor fires `cms:pick-media`, and whatever page it is on answers.
 */

let modal = null;
let onPick = null;
let selected = null;

const cfg = () => ({
    browse: window.CMS?.mediaBrowseUrl,
    upload: window.CMS?.mediaUploadUrl,
    token: window.CMS?.csrfToken
        || document.querySelector('meta[name="csrf-token"]')?.content,
});

const escape = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

function build() {
    if (modal) return modal;

    modal = document.createElement('div');
    modal.className = 'rt-media';
    modal.hidden = true;
    modal.innerHTML = `
        <div class="rt-media__backdrop" data-close></div>
        <div class="rt-media__panel" role="dialog" aria-modal="true" aria-labelledby="rt-media-title">
            <header class="rt-media__head">
                <span id="rt-media-title">Choose an image</span>
                <span class="rt-media__head-actions">
                    <label class="rt-media__upload">
                        Upload
                        <input type="file" accept="image/*" multiple hidden data-upload>
                    </label>
                    <button type="button" class="rt-media__x" data-close aria-label="Close">&times;</button>
                </span>
            </header>
            <div class="rt-media__search">
                <input type="search" placeholder="Search by name or alt text" aria-label="Search images" data-search>
            </div>
            <div class="rt-media__notice" data-notice hidden></div>
            <div class="rt-media__body" data-grid></div>
            <footer class="rt-media__foot">
                <div class="rt-media__detail" data-detail hidden>
                    <img alt="" data-preview>
                    <label class="rt-media__alt">
                        <span>Alt text <small>Describe the image for people who cannot see it</small></span>
                        <input type="text" maxlength="255" data-alt placeholder="e.g. Two people shaking hands at a desk">
                    </label>
                    <label class="rt-media__remember">
                        <input type="checkbox" checked data-remember>
                        Save this alt text to the library
                    </label>
                </div>
                <div class="rt-media__actions">
                    <button type="button" class="rt-media__btn" data-close>Cancel</button>
                    <button type="button" class="rt-media__btn rt-media__btn--primary" data-insert disabled>Insert image</button>
                </div>
            </footer>
        </div>`;

    modal.querySelectorAll('[data-close]').forEach((element) => element.addEventListener('click', close));

    modal.querySelector('[data-insert]').addEventListener('click', insert);

    modal.querySelector('[data-alt]').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            insert();
        }
    });

    let searchTimer;
    modal.querySelector('[data-search]').addEventListener('input', (event) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(event.target.value), 250);
    });

    modal.querySelector('[data-upload]').addEventListener('change', (event) => {
        upload(event.target.files);
        event.target.value = '';
    });

    // Escape always closes, so the picker can never strand anyone.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) close();
    });

    document.body.appendChild(modal);
    return modal;
}

const $ = (selector) => modal.querySelector(selector);

function notice(message, tone = 'error') {
    const box = $('[data-notice]');

    if (!message) {
        box.hidden = true;
        return;
    }

    box.className = `rt-media__notice is-${tone}`;
    box.innerHTML = message;
    box.hidden = false;
}

async function load(query = '') {
    const grid = $('[data-grid]');
    grid.innerHTML = '<p class="rt-media__note">Loading…</p>';

    const { browse } = cfg();

    if (!browse) {
        grid.innerHTML = '<p class="rt-media__note">The media library is not available on this screen.</p>';
        return;
    }

    try {
        const url = new URL(browse, window.location.origin);
        url.searchParams.set('type', 'image');
        if (query) url.searchParams.set('q', query);

        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            throw new Error(response.status === 419 || response.status === 401
                ? 'Your session has expired. Reload the page and sign in again.'
                : `The server answered with an error (${response.status}).`);
        }

        const { data } = await response.json();
        render(data, query);
    } catch (error) {
        grid.innerHTML = `<p class="rt-media__note">The media library could not be loaded.<br><small>${escape(error.message)}</small></p>`;
    }
}

function render(items, query) {
    const grid = $('[data-grid]');
    grid.innerHTML = '';

    if (!items.length) {
        grid.innerHTML = `<p class="rt-media__note">${query
            ? 'No images match that search.'
            : 'No images yet. Use Upload to add one.'}</p>`;
        return;
    }

    items.forEach((item) => {
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.className = 'rt-media__cell';
        cell.title = item.name;
        cell.innerHTML = `<img src="${escape(item.thumb || item.url)}" alt="">`
            + (item.alt ? '' : '<span class="rt-media__badge" title="No alt text yet">no alt</span>');

        cell.addEventListener('click', () => choose(item, cell));
        cell.addEventListener('dblclick', () => {
            choose(item, cell);
            insert();
        });

        grid.appendChild(cell);
    });
}

function choose(item, cell) {
    selected = item;

    modal.querySelectorAll('.rt-media__cell').forEach((c) => c.classList.remove('is-selected'));
    cell?.classList.add('is-selected');

    $('[data-detail]').hidden = false;
    $('[data-preview]').src = item.thumb || item.url;
    $('[data-alt]').value = item.alt || '';
    $('[data-remember]').checked = !item.alt;
    $('[data-insert]').disabled = false;

    $('[data-alt]').focus();
}

async function insert() {
    if (!selected) return;

    const alt = $('[data-alt]').value.trim();
    const remember = $('[data-remember]').checked;

    // Written back so the next use of this image already has a description.
    if (remember && alt !== (selected.alt || '') && selected.id) {
        saveAlt(selected.id, alt);
    }

    const callback = onPick;
    const item = { ...selected, alt };

    close();
    callback?.(item);
}

async function saveAlt(id, alt) {
    const { browse, token } = cfg();

    try {
        // The update route sits beside browse: /admin/media/{id}.
        const url = browse.replace(/\/browse(\?.*)?$/, `/${id}`);

        await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                Accept: 'application/json',
            },
            body: JSON.stringify({ alt }),
        });
    } catch (error) {
        // Not worth interrupting the author over: the alt is in the content
        // either way; only the library copy failed to update.
    }
}

async function upload(files) {
    if (!files?.length) return;

    const { upload: url, token } = cfg();
    const body = new FormData();
    Array.from(files).forEach((file) => body.append('files[]', file));

    notice('Uploading…', 'info');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body,
        });

        const payload = await response.json().catch(() => ({}));

        // Validation failures come back as { errors: {...} }, safety
        // rejections as { rejected: [...] }. Both are shown as written.
        const problems = [
            ...(payload.rejected || []),
            ...Object.values(payload.errors || {}).flat(),
        ];

        if (problems.length) {
            notice(problems.map(escape).join('<br>'));
        } else if (!response.ok) {
            notice(`The upload failed (${response.status}).`);
        } else {
            notice('');
        }

        await load($('[data-search]').value);

        // Select the first new image straight away, ready for its alt text.
        const first = payload.data?.[0];

        if (first) {
            const cell = Array.from(modal.querySelectorAll('.rt-media__cell'))
                .find((c) => c.querySelector('img')?.getAttribute('src') === (first.thumb || first.url));
            choose(first, cell);
        }
    } catch (error) {
        notice('The upload failed. Check your connection and try again.');
    }
}

function close() {
    if (modal) modal.hidden = true;
    onPick = null;
    selected = null;
}

export function registerMediaPicker() {
    window.addEventListener('cms:pick-media', (event) => {
        onPick = event.detail?.onPick || null;
        selected = null;

        build().hidden = false;

        $('[data-detail]').hidden = true;
        $('[data-insert]').disabled = true;
        $('[data-search]').value = '';
        notice('');

        load();
    });
}
