/**
 * The preview iframe and everything drawn over it.
 *
 * The canvas is a real page rendered by the real theme, so what an admin
 * arranges is what a visitor will see. That means the editor cannot simply
 * manipulate its DOM as if it owned it: it listens inside the frame, maps
 * elements back to tree nodes by their data-cb-id, and draws its own chrome -
 * outlines, toolbars, drop lines - in a layer above the frame rather than
 * inside the page.
 */

export class Canvas {
    constructor({ frame, overlay, state, onSelect, onDrop, onRequestRender }) {
        this.frame = frame;
        this.overlay = overlay;
        this.state = state;
        this.onSelect = onSelect;
        this.onDrop = onDrop;
        this.onRequestRender = onRequestRender;

        this.outline = overlay.querySelector('#cb-outline');
        this.outlineLabel = overlay.querySelector('#cb-outline-label');
        this.toolbar = overlay.querySelector('#cb-elem-toolbar');
        this.dropLine = overlay.querySelector('#cb-drop-line');

        this.doc = null;
        this.hovered = null;
        this.dragging = null;

        this.frame.addEventListener('load', () => this.bind());

        // Chrome is positioned in page coordinates, so it has to follow the
        // frame as it scrolls or the window resizes.
        window.addEventListener('resize', () => this.reposition());
    }

    // Frame wiring ---------------------------------------------------------

    bind() {
        this.doc = this.frame.contentDocument;
        if (!this.doc) return;

        this.injectStyles();

        this.doc.addEventListener('click', (event) => this.handleClick(event), true);
        this.doc.addEventListener('mouseover', (event) => this.handleHover(event));
        this.doc.addEventListener('mouseleave', () => this.clearHover());
        this.doc.addEventListener('scroll', () => this.reposition(), true);
        this.doc.defaultView.addEventListener('scroll', () => this.reposition());

        this.doc.addEventListener('dragover', (event) => this.handleDragOver(event));
        this.doc.addEventListener('drop', (event) => this.handleDrop(event));

        this.emitReady();
    }

    emitReady() {
        document.dispatchEvent(new CustomEvent('cb:canvas-ready'));
    }

    /** The stylesheet the editor rewrites for instant style feedback. */
    injectStyles() {
        if (!this.doc) return;

        let tag = this.doc.getElementById('cb-preview-styles');

        if (!tag) {
            tag = this.doc.createElement('style');
            tag.id = 'cb-preview-styles';
            this.doc.head.appendChild(tag);
        }

        this.styleTag = tag;
    }

    setStyles(css) {
        if (this.styleTag) this.styleTag.textContent = css;
    }

    // Selection ------------------------------------------------------------

    handleClick(event) {
        const element = event.target.closest('[data-cb-id]');

        // Links and forms inside the preview must not navigate the canvas away
        // from the page being edited.
        const interactive = event.target.closest('a, button, form');
        if (interactive) event.preventDefault();

        if (!element) return;

        event.stopPropagation();
        this.select(element.dataset.cbId);
    }

    select(id) {
        this.selectedId = id;
        this.onSelect(id);
        this.reposition();
    }

    handleHover(event) {
        const element = event.target.closest('[data-cb-id]');
        if (!element || element === this.hovered) return;

        this.hovered = element;
        this.drawOutline(element, false);
    }

    clearHover() {
        this.hovered = null;
        if (this.selectedId) {
            this.drawOutline(this.element(this.selectedId), true);
        } else {
            this.outline.hidden = true;
            this.toolbar.hidden = true;
        }
    }

    element(id) {
        return this.doc ? this.doc.querySelector(`[data-cb-id="${CSS.escape(id)}"]`) : null;
    }

    /**
     * Position the outline and toolbar over an element.
     *
     * Coordinates come from the element's rect inside the frame, offset by the
     * frame's own position on the page.
     */
    drawOutline(element, selected) {
        if (!element || !this.doc) {
            this.outline.hidden = true;
            this.toolbar.hidden = true;
            return;
        }

        const rect = element.getBoundingClientRect();
        const frameRect = this.frame.getBoundingClientRect();

        const top = frameRect.top + rect.top;
        const left = frameRect.left + rect.left;

        Object.assign(this.outline.style, {
            top: `${top}px`,
            left: `${left}px`,
            width: `${rect.width}px`,
            height: `${rect.height}px`,
        });

        this.outline.hidden = false;
        this.outline.classList.toggle('is-selected', Boolean(selected));

        const type = element.dataset.cbType;
        const widget = element.dataset.cbWidget;
        this.outlineLabel.textContent = widget || type || '';

        if (selected) {
            // Above the element, unless it is near the top of the viewport.
            const toolbarTop = top < frameRect.top + 40 ? top + rect.height + 4 : top - 32;

            Object.assign(this.toolbar.style, {
                top: `${toolbarTop}px`,
                left: `${left}px`,
            });

            this.toolbar.hidden = false;
            this.toolbar.dataset.target = element.dataset.cbId;
        } else {
            this.toolbar.hidden = true;
        }
    }

    reposition() {
        if (this.selectedId) {
            this.drawOutline(this.element(this.selectedId), true);
        } else {
            this.outline.hidden = true;
            this.toolbar.hidden = true;
        }
    }

    // Dragging -------------------------------------------------------------

    /** Called by the panel when a widget starts being dragged in. */
    beginDrag(payload) {
        this.dragging = payload;
    }

    endDrag() {
        this.dragging = null;
        this.dropLine.hidden = true;
    }

    handleDragOver(event) {
        if (!this.dragging) return;

        event.preventDefault();

        const target = this.dropTarget(event);
        if (!target) {
            this.dropLine.hidden = true;
            return;
        }

        this.drawDropLine(target);
        this.pendingDrop = target;
    }

    handleDrop(event) {
        if (!this.dragging || !this.pendingDrop) return;

        event.preventDefault();

        this.onDrop(this.dragging, this.pendingDrop);

        this.pendingDrop = null;
        this.endDrag();
    }

    /**
     * Work out where a drop would land.
     *
     * Widgets go inside a column, before or after whatever they were dropped
     * nearest. Dropping on empty canvas appends a new section at the end.
     */
    dropTarget(event) {
        const point = { x: event.clientX, y: event.clientY };
        const element = this.doc.elementFromPoint(point.x, point.y);

        if (!element) return { id: null, position: 'end' };

        const widget = element.closest('[data-cb-type="widget"]');

        if (widget) {
            const rect = widget.getBoundingClientRect();
            const after = point.y > rect.top + rect.height / 2;
            return { id: widget.dataset.cbId, position: after ? 'after' : 'before', rect, after };
        }

        const column = element.closest('[data-cb-type="column"]');

        if (column) {
            return { id: column.dataset.cbId, position: 'inside', rect: column.getBoundingClientRect() };
        }

        const section = element.closest('[data-cb-type="section"]');

        if (section) {
            const rect = section.getBoundingClientRect();
            const after = point.y > rect.top + rect.height / 2;
            return { id: section.dataset.cbId, position: after ? 'after' : 'before', rect, after, isSection: true };
        }

        return { id: null, position: 'end' };
    }

    drawDropLine(target) {
        const frameRect = this.frame.getBoundingClientRect();

        if (!target.rect) {
            this.dropLine.hidden = true;
            return;
        }

        const top = target.position === 'inside'
            ? frameRect.top + target.rect.bottom - 2
            : frameRect.top + (target.after ? target.rect.bottom : target.rect.top);

        Object.assign(this.dropLine.style, {
            top: `${top}px`,
            left: `${frameRect.left + target.rect.left}px`,
            width: `${target.rect.width}px`,
        });

        this.dropLine.hidden = false;
    }

    // Rendering ------------------------------------------------------------

    /** Replace one element's markup after a content change. */
    replaceNode(id, html) {
        const element = this.element(id);
        if (!element || !html) return false;

        const holder = this.doc.createElement('div');
        holder.innerHTML = html;

        const replacement = holder.firstElementChild;
        if (!replacement) return false;

        element.replaceWith(replacement);
        this.reposition();
        return true;
    }

    /** Replace the whole canvas body after a structural change. */
    replaceAll(html) {
        const root = this.doc?.getElementById('cb-canvas-root');
        if (!root) return;

        root.innerHTML = html || '';
        this.reposition();
    }

    setDevice(width) {
        if (width) {
            this.frame.style.width = `${width}px`;
            this.frame.style.maxWidth = '100%';
        } else {
            this.frame.style.width = '100%';
            this.frame.style.maxWidth = 'none';
        }

        // The frame resize animates, so chrome is repositioned after it ends.
        setTimeout(() => this.reposition(), 260);
    }
}
