/**
 * The rich text editor used by the admin content forms and by the builder's
 * rich-text control.
 *
 * Written rather than pulled in, for three reasons: CKEditor 5 is GPL, which
 * is a licensing trap for anyone reselling this; the builder and the admin
 * forms should share one editor rather than two; and it needs to talk to the
 * CMS's own media library rather than a bolted-on upload endpoint.
 *
 * Formatting goes through document.execCommand. That API is marked deprecated
 * and is not coming back, but it is implemented consistently everywhere and
 * has no replacement that does not mean writing a full selection and
 * range-mutation engine. The alternative is considerably more code and more
 * bugs, so it is used deliberately, with the DOM read back afterwards as the
 * source of truth rather than trusting the command to have done the right
 * thing.
 *
 * Everything produced here is sanitised again on the server before storage.
 */

const BLOCKS = {
    p: 'Paragraph',
    h2: 'Heading 2',
    h3: 'Heading 3',
    h4: 'Heading 4',
    blockquote: 'Quote',
    pre: 'Code block',
};

export class RichTextEditor {
    constructor(textarea, options = {}) {
        this.textarea = textarea;
        this.options = options;
        this.onChange = options.onChange || null;

        this.build();
        this.bind();
        this.sync();
    }

    // Construction ---------------------------------------------------------

    build() {
        this.wrap = document.createElement('div');
        this.wrap.className = 'rt';

        this.toolbar = document.createElement('div');
        this.toolbar.className = 'rt__toolbar';
        this.toolbar.setAttribute('role', 'toolbar');
        this.toolbar.setAttribute('aria-label', 'Formatting');

        this.surface = document.createElement('div');
        this.surface.className = 'rt__surface prose-content';
        this.surface.contentEditable = 'true';
        this.surface.setAttribute('role', 'textbox');
        this.surface.setAttribute('aria-multiline', 'true');
        this.surface.setAttribute('aria-label', this.options.label || 'Content');
        this.surface.innerHTML = this.textarea.value || '';

        if (this.options.minHeight) {
            this.surface.style.minHeight = this.options.minHeight;
        }

        // The source view, for anyone who wants the markup directly.
        this.source = document.createElement('textarea');
        this.source.className = 'rt__source';
        this.source.hidden = true;
        this.source.spellcheck = false;

        this.status = document.createElement('div');
        this.status.className = 'rt__status';

        this.buildToolbar();

        this.wrap.append(this.toolbar, this.surface, this.source, this.status);

        // The original textarea stays in the DOM and keeps carrying the value,
        // so the form posts exactly as it did before and nothing else changes.
        this.textarea.hidden = true;
        this.textarea.parentNode.insertBefore(this.wrap, this.textarea.nextSibling);
    }

    buildToolbar() {
        const groups = [
            [
                this.select('block', BLOCKS, (value) => this.formatBlock(value)),
            ],
            [
                this.button('bold', 'Bold', 'B', () => this.exec('bold'), 'Ctrl+B'),
                this.button('italic', 'Italic', 'I', () => this.exec('italic'), 'Ctrl+I'),
                this.button('underline', 'Underline', 'U', () => this.exec('underline'), 'Ctrl+U'),
                this.button('strikeThrough', 'Strikethrough', 'S', () => this.exec('strikeThrough')),
            ],
            [
                this.button('insertUnorderedList', 'Bulleted list', 'list-ul', () => this.exec('insertUnorderedList')),
                this.button('insertOrderedList', 'Numbered list', 'list-ol', () => this.exec('insertOrderedList')),
                this.button('outdent', 'Outdent', 'outdent', () => this.exec('outdent')),
                this.button('indent', 'Indent', 'indent', () => this.exec('indent')),
            ],
            [
                this.button('justifyLeft', 'Align left', 'align-left', () => this.exec('justifyLeft')),
                this.button('justifyCenter', 'Align centre', 'align-center', () => this.exec('justifyCenter')),
                this.button('justifyRight', 'Align right', 'align-right', () => this.exec('justifyRight')),
                this.button('justifyFull', 'Justify', 'align-justify', () => this.exec('justifyFull')),
            ],
            [
                this.button('createLink', 'Insert link', 'link', () => this.insertLink(), 'Ctrl+K'),
                this.button('unlink', 'Remove link', 'unlink', () => this.exec('unlink')),
                this.button('image', 'Insert image', 'image', () => this.insertImage()),
                this.button('insertHorizontalRule', 'Divider', 'divider', () => this.exec('insertHorizontalRule')),
            ],
            [
                this.button('removeFormat', 'Clear formatting', 'eraser', () => this.clearFormatting()),
                this.button('undo', 'Undo', 'undo', () => this.exec('undo'), 'Ctrl+Z'),
                this.button('redo', 'Redo', 'redo', () => this.exec('redo'), 'Ctrl+Shift+Z'),
                this.button('source', 'Edit the HTML', 'code', () => this.toggleSource()),
            ],
        ];

        groups.forEach((group, index) => {
            if (index > 0) {
                const divider = document.createElement('span');
                divider.className = 'rt__divider';
                this.toolbar.appendChild(divider);
            }

            group.forEach((element) => this.toolbar.appendChild(element));
        });
    }

    button(command, label, glyph, action, shortcut) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rt__btn';
        button.dataset.command = command;
        button.title = shortcut ? `${label} (${shortcut})` : label;
        button.setAttribute('aria-label', label);
        button.innerHTML = this.glyph(glyph);

        // mousedown, not click: the surface must not lose its selection before
        // the command runs.
        button.addEventListener('mousedown', (event) => {
            event.preventDefault();
            action();
        });

        return button;
    }

    select(name, options, action) {
        const select = document.createElement('select');
        select.className = 'rt__select';
        select.dataset.control = name;
        select.setAttribute('aria-label', 'Text style');

        Object.entries(options).forEach(([value, label]) => {
            select.appendChild(new Option(label, value));
        });

        select.addEventListener('change', () => {
            action(select.value);
            this.surface.focus();
        });

        return select;
    }

    // Commands -------------------------------------------------------------

    exec(command, value = null) {
        this.surface.focus();

        try {
            document.execCommand(command, false, value);
        } catch (error) {
            // A command a browser refuses should not take the editor with it.
        }

        this.afterChange();
    }

    formatBlock(tag) {
        this.exec('formatBlock', `<${tag}>`);
    }

    /**
     * Clearing formatting has to drop the block wrapper too, or a heading
     * stays a heading with its bold removed, which is not what anyone means.
     */
    clearFormatting() {
        this.exec('removeFormat');
        this.exec('formatBlock', '<p>');
    }

    insertLink() {
        const selection = window.getSelection();
        const existing = this.closestTag('a');
        const current = existing ? existing.getAttribute('href') : '';

        const url = window.prompt('Link address', current || 'https://');

        if (url === null) return;

        if (url.trim() === '') {
            this.exec('unlink');
            return;
        }

        if (!this.isSafeUrl(url)) {
            this.notify('That link was not added: only http, https, mailto and tel addresses are allowed.', 'error');
            return;
        }

        // execCommand needs something selected; with a caret inside a link it
        // silently does nothing, so the link is re-selected first.
        if (existing && selection.isCollapsed) {
            const range = document.createRange();
            range.selectNodeContents(existing);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        if (selection.isCollapsed) {
            this.insertHtml(`<a href="${this.escape(url)}">${this.escape(url)}</a>`);
        } else {
            this.exec('createLink', url);
        }
    }

    insertImage() {
        const pick = (media) => {
            const url = media.url || media.path;

            // The filename is deliberately not used as a fallback: "IMG_2034"
            // describes nothing, and an honest empty alt is better than that.
            const alt = this.escape(media.alt || '');

            this.insertHtml(
                `<figure class="rt-figure align-center"><img src="${this.escape(url)}" alt="${alt}" loading="lazy"></figure><p><br></p>`
            );

            // If no description was given, open the image settings straight
            // away rather than hoping someone comes back for it later.
            if (!media.alt) {
                const images = this.surface.querySelectorAll(`img[src="${CSS.escape(url)}"]`);
                const inserted = images[images.length - 1];
                if (inserted) setTimeout(() => this.openImagePanel(inserted, true), 30);
            }
        };

        // Inside the builder the media library is already open to us; on a
        // plain admin form it is opened through the shared picker.
        if (typeof this.options.openMedia === 'function') {
            this.options.openMedia(pick);
            return;
        }

        window.dispatchEvent(new CustomEvent('cms:pick-media', { detail: { onPick: pick } }));
    }

    // Image settings ------------------------------------------------------

    /**
     * A small panel anchored to an image: alt text, alignment, caption and
     * removal. Alignment is a class on the surrounding <figure>, so it survives
     * sanitising and themes can restyle it.
     */
    openImagePanel(img, focusAlt = false) {
        this.closeImagePanel();

        const figure = this.ensureFigure(img);
        this.activeImage = img;
        img.classList.add('rt-selected');

        const panel = document.createElement('div');
        panel.className = 'rt-image-panel';
        panel.contentEditable = 'false';

        const current = ['align-left', 'align-center', 'align-right', 'align-wide']
            .find((name) => figure.classList.contains(name)) || 'align-center';
        const hasCaption = Boolean(figure.querySelector('figcaption'));

        panel.innerHTML = `
            <label class="rt-image-panel__alt">
                <span>Alt text</span>
                <input type="text" maxlength="255" value="${this.escape(img.getAttribute('alt') || '')}"
                       placeholder="Describe this image">
            </label>
            <div class="rt-image-panel__row">
                <span class="rt-image-panel__label">Align</span>
                ${[['align-left', 'Left', 'align-left'], ['align-center', 'Centre', 'align-center'],
                   ['align-right', 'Right', 'align-right'], ['align-wide', 'Full width', 'align-justify']]
                    .map(([cls, label, glyph]) => `<button type="button" data-align="${cls}" title="${label}"
                        class="rt__btn ${cls === current ? 'is-active' : ''}">${this.glyph(glyph)}</button>`).join('')}
            </div>
            <div class="rt-image-panel__row">
                <span class="rt-image-panel__label">Size</span>
                <label class="rt-image-panel__width">
                    <input type="number" min="40" step="10" data-width
                           value="${Math.round(img.getBoundingClientRect().width) || ''}"
                           aria-label="Image width in pixels">
                    <span>px</span>
                </label>
                ${[25, 50, 75, 100].map((percent) => `<button type="button" data-percent="${percent}"
                    class="rt-image-panel__size" title="Set to ${percent}% of the content width">${percent}%</button>`).join('')}
                <button type="button" data-original class="rt-image-panel__size" title="Use the file's own size">Original</button>
            </div>
            <p class="rt-image-panel__hint">
                Drag a corner of the image to resize it. The uploaded file is never changed.
            </p>
            <div class="rt-image-panel__row">
                <label class="rt-image-panel__check">
                    <input type="checkbox" data-caption ${hasCaption ? 'checked' : ''}> Show caption
                </label>
                <button type="button" class="rt-image-panel__remove" data-remove>Remove image</button>
                <button type="button" class="rt-image-panel__done" data-done>Done</button>
            </div>
            ${img.getAttribute('alt') ? '' : '<p class="rt-image-panel__hint">Images without alt text are invisible to screen readers and search engines.</p>'}`;

        const alt = panel.querySelector('input[type="text"]');

        alt.addEventListener('input', () => {
            img.setAttribute('alt', alt.value);
            this.afterChange();
        });

        alt.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === 'Escape') {
                event.preventDefault();
                this.closeImagePanel();
            }
        });

        panel.querySelectorAll('[data-align]').forEach((button) => {
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                figure.classList.remove('align-left', 'align-center', 'align-right', 'align-wide');
                figure.classList.add(button.dataset.align);
                panel.querySelectorAll('[data-align]').forEach((b) => b.classList.toggle('is-active', b === button));

                // Full width means full width: a size set earlier would other-
                // wise win over it and the button would appear to do nothing.
                if (button.dataset.align === 'align-wide') {
                    this.resetImageSize(img);
                }

                this.afterChange();
                this.positionImagePanel();
                this.syncSizeField();
            });
        });

        // Typing an exact width, for anyone who knows the number they want.
        const width = panel.querySelector('[data-width]');

        width.addEventListener('input', () => {
            const value = parseInt(width.value, 10);

            if (!Number.isNaN(value) && value >= 40) {
                const ratio = img.naturalWidth && img.naturalHeight
                    ? img.naturalHeight / img.naturalWidth
                    : 0;

                this.applyImageWidth(img, value, ratio ? Math.round(value * ratio) : null);
                this.afterChange();
                this.positionImagePanel();
            }
        });

        panel.querySelectorAll('[data-percent]').forEach((button) => {
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                this.applyImagePercent(img, Number(button.dataset.percent));
                this.afterChange();
                this.positionImagePanel();
                this.syncSizeField();
            });
        });

        panel.querySelector('[data-original]').addEventListener('mousedown', (event) => {
            event.preventDefault();
            this.resetImageSize(img);
            this.afterChange();
            this.positionImagePanel();
            this.syncSizeField();
        });

        panel.querySelector('[data-caption]').addEventListener('change', (event) => {
            let caption = figure.querySelector('figcaption');

            if (event.target.checked && !caption) {
                caption = document.createElement('figcaption');
                caption.textContent = img.getAttribute('alt') || 'Caption';
                figure.appendChild(caption);
            } else if (!event.target.checked && caption) {
                caption.remove();
            }

            this.afterChange();
        });

        panel.querySelector('[data-remove]').addEventListener('mousedown', (event) => {
            event.preventDefault();
            figure.remove();
            this.closeImagePanel();
            this.afterChange();
        });

        panel.querySelector('[data-done]').addEventListener('mousedown', (event) => {
            event.preventDefault();
            this.closeImagePanel();
        });

        this.wrap.appendChild(panel);
        this.imagePanel = panel;
        this.showResizer(img);
        this.positionImagePanel();

        // A freshly inserted image may not have loaded yet, so its box is the
        // wrong size until it does.
        if (!img.complete) {
            img.addEventListener('load', () => {
                this.positionImagePanel();
                this.syncSizeField();
            }, { once: true });
        }

        if (focusAlt) alt.focus();
    }

    positionImagePanel() {
        this.positionImagePanelOnly();
        this.positionResizer();
    }

    closeImagePanel() {
        this.imagePanel?.remove();
        this.imagePanel = null;
        this.hideResizer();
        this.activeImage?.classList.remove('rt-selected');
        this.activeImage = null;
        this.sync();
    }

    // Resizing -------------------------------------------------------------

    /**
     * Corner handles for dragging an image to a new display size.
     *
     * Only the markup changes: a width is written onto the <img>, and the
     * uploaded file is left exactly as it is. That means the same image can be
     * shown at different sizes in different places, and shrinking one here
     * never degrades the original.
     *
     * The handles are drawn in an overlay rather than inside the editable
     * area, so they can never end up in the saved content.
     */
    showResizer(img) {
        this.hideResizer();

        const resizer = document.createElement('div');
        resizer.className = 'rt-resizer';
        resizer.contentEditable = 'false';

        ['nw', 'ne', 'sw', 'se'].forEach((corner) => {
            const handle = document.createElement('span');
            handle.className = `rt-resizer__handle rt-resizer__handle--${corner}`;
            handle.dataset.corner = corner;
            handle.addEventListener('pointerdown', (event) => this.beginResize(event, img, corner));
            resizer.appendChild(handle);
        });

        const readout = document.createElement('span');
        readout.className = 'rt-resizer__readout';
        resizer.appendChild(readout);

        this.wrap.appendChild(resizer);
        this.resizer = resizer;
        this.readout = readout;

        this.positionResizer();
    }

    positionResizer() {
        if (!this.resizer || !this.activeImage) return;

        const wrap = this.wrap.getBoundingClientRect();
        const image = this.activeImage.getBoundingClientRect();

        Object.assign(this.resizer.style, {
            top: `${image.top - wrap.top}px`,
            left: `${image.left - wrap.left}px`,
            width: `${image.width}px`,
            height: `${image.height}px`,
        });

        this.readout.textContent = `${Math.round(image.width)} × ${Math.round(image.height)}`;
    }

    hideResizer() {
        this.resizer?.remove();
        this.resizer = null;
        this.readout = null;
    }

    beginResize(event, img, corner) {
        event.preventDefault();
        event.stopPropagation();

        const handle = event.currentTarget;
        handle.setPointerCapture(event.pointerId);

        const rect = img.getBoundingClientRect();
        const startX = event.clientX;
        const startWidth = rect.width;

        // The aspect ratio comes from the file itself, so dragging never
        // stretches the picture out of shape.
        const ratio = img.naturalWidth && img.naturalHeight
            ? img.naturalHeight / img.naturalWidth
            : rect.height / rect.width;

        // Never wider than the column it sits in, and never so small it
        // becomes impossible to grab again.
        const styles = window.getComputedStyle(this.surface);
        const maxWidth = this.surface.clientWidth
            - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight);

        const pullsLeft = corner === 'nw' || corner === 'sw';

        const onMove = (move) => {
            const delta = move.clientX - startX;
            const width = Math.round(Math.min(maxWidth, Math.max(40, startWidth + (pullsLeft ? -delta : delta))));

            this.applyImageWidth(img, width, Math.round(width * ratio));
            this.positionResizer();
            this.positionImagePanelOnly();
        };

        const onUp = () => {
            handle.removeEventListener('pointermove', onMove);
            handle.removeEventListener('pointerup', onUp);
            handle.removeEventListener('pointercancel', onUp);

            this.afterChange();
            this.syncSizeField();
        };

        handle.addEventListener('pointermove', onMove);
        handle.addEventListener('pointerup', onUp);
        handle.addEventListener('pointercancel', onUp);
    }

    /**
     * Write the display size onto the image.
     *
     * The width and height attributes are set alongside the inline width so
     * the browser reserves the right space before the image loads, which stops
     * the page jumping around as it renders.
     */
    applyImageWidth(img, width, height) {
        img.style.width = `${width}px`;
        img.style.height = 'auto';

        if (width) img.setAttribute('width', width);
        if (height) img.setAttribute('height', height);
    }

    applyImagePercent(img, percent) {
        img.style.width = `${percent}%`;
        img.style.height = 'auto';
        img.removeAttribute('width');
        img.removeAttribute('height');
    }

    resetImageSize(img) {
        img.style.removeProperty('width');
        img.style.removeProperty('height');
        img.removeAttribute('width');
        img.removeAttribute('height');
    }

    /** Keeps the panel's width box in step with a drag. */
    syncSizeField() {
        const field = this.imagePanel?.querySelector('[data-width]');

        if (field && this.activeImage) {
            field.value = Math.round(this.activeImage.getBoundingClientRect().width);
        }
    }

    positionImagePanelOnly() {
        if (!this.imagePanel || !this.activeImage) return;

        const wrap = this.wrap.getBoundingClientRect();
        const image = this.activeImage.getBoundingClientRect();

        this.imagePanel.style.top = `${image.bottom - wrap.top + 44}px`;
        this.imagePanel.style.left = `${Math.max(8, image.left - wrap.left)}px`;
    }

    /** Images pasted from elsewhere arrive bare; alignment needs a figure. */
    ensureFigure(img) {
        const parent = img.parentElement;

        if (parent && parent.tagName === 'FIGURE') return parent;

        const figure = document.createElement('figure');
        figure.className = 'rt-figure align-center';
        img.replaceWith(figure);
        figure.appendChild(img);

        return figure;
    }

    insertHtml(html) {
        this.surface.focus();

        try {
            document.execCommand('insertHTML', false, html);
        } catch (error) {
            this.surface.insertAdjacentHTML('beforeend', html);
        }

        this.afterChange();
    }

    toggleSource() {
        const showing = this.source.hidden;

        if (showing) {
            this.source.value = this.format(this.surface.innerHTML);
            this.source.style.minHeight = `${this.surface.offsetHeight}px`;
        } else {
            this.surface.innerHTML = this.source.value;
        }

        this.source.hidden = !showing;
        this.surface.hidden = showing;

        this.toolbar.querySelectorAll('.rt__btn, .rt__select').forEach((element) => {
            if (element.dataset.command === 'source') return;
            element.disabled = showing;
        });

        this.toolbar.querySelector('[data-command="source"]').classList.toggle('is-active', showing);

        this.afterChange();
    }

    // Events ---------------------------------------------------------------

    bind() {
        this.surface.addEventListener('input', () => this.afterChange());
        this.surface.addEventListener('blur', () => this.sync());
        this.source.addEventListener('input', () => this.afterChange());

        this.surface.addEventListener('click', (event) => {
            const img = event.target.closest('img');

            if (img && this.surface.contains(img)) {
                this.openImagePanel(img);
            } else {
                this.closeImagePanel();
            }
        });

        document.addEventListener('mousedown', (event) => {
            if (this.imagePanel && !this.wrap.contains(event.target)) this.closeImagePanel();
        });

        this.surface.addEventListener('scroll', () => this.positionImagePanel());
        window.addEventListener('resize', () => this.positionImagePanel());

        this.surface.addEventListener('keydown', (event) => this.handleKey(event));
        this.surface.addEventListener('paste', (event) => this.handlePaste(event));

        ['keyup', 'mouseup', 'focus'].forEach((type) => {
            this.surface.addEventListener(type, () => this.refreshToolbar());
        });

        // Dropping a file into the surface would otherwise navigate away from
        // the page, losing everything typed so far.
        this.surface.addEventListener('drop', (event) => {
            if (event.dataTransfer?.files?.length) event.preventDefault();
        });
    }

    handleKey(event) {
        const meta = event.ctrlKey || event.metaKey;

        if (meta && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            this.insertLink();
            return;
        }

        // A plain Enter inside a quote or code block should escape it rather
        // than adding another line to it.
        if (event.key === 'Enter' && !event.shiftKey) {
            const block = this.closestTag('blockquote') || this.closestTag('pre');

            if (block && this.isAtEndOfBlock(block)) {
                event.preventDefault();
                const paragraph = document.createElement('p');
                paragraph.innerHTML = '<br>';
                block.parentNode.insertBefore(paragraph, block.nextSibling);
                this.placeCaret(paragraph);
                this.afterChange();
            }
        }
    }

    /**
     * Paste is where the worst markup arrives: Word and Google Docs bring
     * whole stylesheets and nested spans with them. The HTML flavour is taken
     * but reduced to the tags this editor actually supports.
     */
    handlePaste(event) {
        const data = event.clipboardData;

        if (!data) return;

        const html = data.getData('text/html');
        const text = data.getData('text/plain');

        event.preventDefault();

        if (html && !event.shiftKey) {
            this.insertHtml(this.scrubPastedHtml(html));
        } else if (text) {
            // Blank lines become paragraphs, which is what someone pasting
            // prose expects.
            const paragraphs = text
                .split(/\n{2,}/)
                .map((chunk) => `<p>${this.escape(chunk).replace(/\n/g, '<br>')}</p>`)
                .join('');

            this.insertHtml(paragraphs);
        }
    }

    scrubPastedHtml(html) {
        const holder = document.createElement('div');
        holder.innerHTML = html;

        // Office pastes arrive wrapped in conditional comments and <o:p> tags.
        holder.querySelectorAll('style, script, meta, link, title, o\\:p').forEach((node) => node.remove());

        const allowed = new Set([
            'P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'A', 'UL', 'OL', 'LI',
            'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'CODE',
            'IMG', 'FIGURE', 'FIGCAPTION', 'HR', 'TABLE', 'THEAD', 'TBODY',
            'TR', 'TH', 'TD', 'SUB', 'SUP',
        ]);

        const walk = (node) => {
            Array.from(node.children).forEach((child) => {
                walk(child);

                if (!allowed.has(child.tagName)) {
                    // Keep the words, drop the wrapper.
                    while (child.firstChild) child.parentNode.insertBefore(child.firstChild, child);
                    child.remove();
                    return;
                }

                Array.from(child.attributes).forEach((attribute) => {
                    const name = attribute.name.toLowerCase();
                    const keep = ['href', 'src', 'alt', 'title', 'colspan', 'rowspan'];

                    if (!keep.includes(name)) child.removeAttribute(attribute.name);
                });

                if (child.tagName === 'A' && !this.isSafeUrl(child.getAttribute('href') || '')) {
                    child.removeAttribute('href');
                }
            });
        };

        walk(holder);

        return holder.innerHTML.replace(/<!--[\s\S]*?-->/g, '');
    }

    // Toolbar state --------------------------------------------------------

    refreshToolbar() {
        if (!this.source.hidden) return;

        ['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList',
            'insertOrderedList', 'justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull']
            .forEach((command) => {
                const button = this.toolbar.querySelector(`[data-command="${command}"]`);
                if (!button) return;

                let active = false;
                try {
                    active = document.queryCommandState(command);
                } catch (error) {
                    active = false;
                }

                button.classList.toggle('is-active', active);
            });

        const select = this.toolbar.querySelector('[data-control="block"]');

        if (select) {
            const block = this.currentBlock();
            select.value = Object.keys(BLOCKS).includes(block) ? block : 'p';
        }
    }

    currentBlock() {
        try {
            const value = document.queryCommandValue('formatBlock');
            return (value || 'p').toLowerCase().replace(/[<>]/g, '');
        } catch (error) {
            return 'p';
        }
    }

    // Value handling -------------------------------------------------------

    afterChange() {
        this.sync();
        this.refreshToolbar();

        if (this.onChange) this.onChange(this.value());
    }

    /** Copy the current value back into the original textarea. */
    sync() {
        const value = this.value();

        this.textarea.value = value;

        const words = this.constructor.wordCount(value);
        this.status.textContent = words === 1 ? '1 word' : `${words} words`;
    }

    value() {
        if (!this.source.hidden) return this.source.value;

        const clone = this.surface.cloneNode(true);

        clone.querySelectorAll('.rt-selected').forEach((element) => {
            element.classList.remove('rt-selected');
            if (!element.className) element.removeAttribute('class');
        });

        const html = clone.innerHTML.trim();

        // An "empty" contenteditable still contains a stray break.
        return html === '<br>' || html === '<p><br></p>' ? '' : html;
    }

    setValue(html) {
        this.surface.innerHTML = html || '';
        this.sync();
    }

    static wordCount(html) {
        const text = html.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').trim();
        return text === '' ? 0 : text.split(/\s+/).length;
    }

    // Helpers --------------------------------------------------------------

    closestTag(tag) {
        let node = window.getSelection()?.anchorNode;

        while (node && node !== this.surface) {
            if (node.nodeType === 1 && node.tagName.toLowerCase() === tag) return node;
            node = node.parentNode;
        }

        return null;
    }

    isAtEndOfBlock(block) {
        const selection = window.getSelection();
        if (!selection.rangeCount) return false;

        const range = selection.getRangeAt(0).cloneRange();
        range.selectNodeContents(block);
        range.setStart(selection.focusNode, selection.focusOffset);

        return range.toString().trim() === '';
    }

    placeCaret(element) {
        const range = document.createRange();
        range.setStart(element, 0);
        range.collapse(true);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    isSafeUrl(url) {
        const normalised = String(url).toLowerCase().replace(/[\s -]/g, '');
        const scheme = normalised.match(/^([a-z][a-z0-9+.-]*):/);

        return !scheme || ['http', 'https', 'mailto', 'tel'].includes(scheme[1]);
    }

    escape(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    /** Light indentation for the source view, so it is readable. */
    format(html) {
        return html
            .replace(/></g, '>\n<')
            .replace(/(<\/(p|h[1-6]|ul|ol|li|blockquote|pre|figure|table|tr)>)/g, '$1\n')
            .replace(/\n{2,}/g, '\n')
            .trim();
    }

    notify(message, tone = '') {
        this.status.textContent = message;
        this.status.className = `rt__status ${tone ? `is-${tone}` : ''}`;

        setTimeout(() => {
            this.status.className = 'rt__status';
            this.sync();
        }, 4000);
    }

    glyph(name) {
        const icons = {
            B: '<span class="rt__letter" style="font-weight:800">B</span>',
            I: '<span class="rt__letter" style="font-style:italic">I</span>',
            U: '<span class="rt__letter" style="text-decoration:underline">U</span>',
            S: '<span class="rt__letter" style="text-decoration:line-through">S</span>',
            'list-ul': '<svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="3.5" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="3.5" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>',
            'list-ol': '<svg viewBox="0 0 24 24"><path d="M9 6h12M9 12h12M9 18h12M3 5h1v4M3 15h2v1H3v2h2"/></svg>',
            outdent: '<svg viewBox="0 0 24 24"><path d="M21 6H9M21 12H11M21 18H9M7 9l-3 3 3 3"/></svg>',
            indent: '<svg viewBox="0 0 24 24"><path d="M21 6H9M21 12h-8M21 18H9M3 9l3 3-3 3"/></svg>',
            'align-left': '<svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h12M3 18h15"/></svg>',
            'align-center': '<svg viewBox="0 0 24 24"><path d="M3 6h18M6 12h12M5 18h14"/></svg>',
            'align-right': '<svg viewBox="0 0 24 24"><path d="M3 6h18M9 12h12M6 18h15"/></svg>',
            'align-justify': '<svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
            link: '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7L12 19"/></svg>',
            unlink: '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7L12 19"/><path d="M3 3l18 18"/></svg>',
            image: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
            divider: '<svg viewBox="0 0 24 24"><path d="M3 12h18"/></svg>',
            eraser: '<svg viewBox="0 0 24 24"><path d="M20 20H9l-5-5a2 2 0 0 1 0-3l8-8a2 2 0 0 1 3 0l6 6a2 2 0 0 1 0 3l-7 7"/></svg>',
            undo: '<svg viewBox="0 0 24 24"><path d="M3 7v6h6"/><path d="M3 13a9 9 0 1 0 3-7"/></svg>',
            redo: '<svg viewBox="0 0 24 24"><path d="M21 7v6h-6"/><path d="M21 13a9 9 0 1 1-3-7"/></svg>',
            code: '<svg viewBox="0 0 24 24"><path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/></svg>',
        };

        return icons[name] || `<span class="rt__letter">${name}</span>`;
    }
}

/** Turn every textarea carrying data-richtext into an editor. */
export function mountRichTextEditors(root = document) {
    root.querySelectorAll('textarea[data-richtext]:not([data-rt-ready])').forEach((textarea) => {
        textarea.dataset.rtReady = '1';

        new RichTextEditor(textarea, {
            label: textarea.dataset.label || 'Content',
            minHeight: textarea.dataset.minHeight || '320px',
        });
    });
}
