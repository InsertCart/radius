/**
 * The visual editor.
 *
 * Wires the panel, the canvas and the state together, and owns the two
 * behaviours that make the whole thing feel quick:
 *
 *  - a style change rewrites the preview's stylesheet directly, with no server
 *    round trip, so dragging a slider is immediate;
 *  - a content change re-renders only the element that changed.
 *
 * Structural edits - adding, moving, deleting - re-render the whole canvas,
 * which is rare enough that the round trip is not felt.
 */

import { Api } from './api.js';
import { Canvas } from './canvas.js';
import { ControlRenderer, isStyleOnly } from './controls.js';
import { icon } from './icons.js';
import { compile } from './styles.js';
import { State, makeSection, makeWidget } from './state.js';

const boot = window.CB_BOOT;

class Editor {
    constructor() {
        this.boot = boot;
        this.api = new Api(boot.config);
        this.state = new State(boot.tree, { historyLimit: boot.config.historyLimit });
        this.device = 'desktop';
        this.tab = 'content';
        this.dirty = false;

        this.el = {
            panel: document.getElementById('cb-panel'),
            widgetList: document.getElementById('cb-widget-list'),
            widgetSearch: document.getElementById('cb-widget-search'),
            viewWidgets: document.getElementById('cb-view-widgets'),
            viewSettings: document.getElementById('cb-view-settings'),
            viewNavigator: document.getElementById('cb-view-navigator'),
            navigator: document.getElementById('cb-navigator'),
            controls: document.getElementById('cb-controls'),
            settingsTitle: document.getElementById('cb-settings-title'),
            tabs: document.getElementById('cb-tabs'),
            saveState: document.getElementById('cb-save-state'),
            undo: document.getElementById('cb-undo'),
            redo: document.getElementById('cb-redo'),
            publish: document.getElementById('cb-publish'),
            loading: document.getElementById('cb-loading'),
            ghost: document.getElementById('cb-drag-ghost'),
        };

        this.canvas = new Canvas({
            frame: document.getElementById('cb-canvas'),
            overlay: document.getElementById('cb-overlay'),
            state: this.state,
            onSelect: (id) => this.selectNode(id),
            onDrop: (payload, target) => this.handleDrop(payload, target),
        });

        this.controls = new ControlRenderer({
            boot,
            onChange: (control, value, options) => this.applySetting(control, value, options),
            openMedia: (cb, multiple) => this.openMedia(cb, multiple),
            openIcons: (cb) => this.openIcons(cb),
        });

        this.bind();
        this.renderWidgetPanel();
        this.updateHistoryButtons();
        this.checkRequiredWidget();
    }

    // Wiring ---------------------------------------------------------------

    bind() {
        this.state.on('change', ({ node, styleOnly }) => this.onSettingChanged(node, styleOnly));
        this.state.on('structure', () => this.onStructureChanged());
        this.state.on('history', (history) => this.updateHistoryButtons(history));

        document.addEventListener('cb:canvas-ready', () => {
            this.el.loading.hidden = true;
            this.refreshStyles();
        });

        document.addEventListener('cb:device', (event) => this.setDevice(event.detail));

        // Panel navigation
        document.getElementById('cb-panel-widgets').addEventListener('click', () => this.showView('widgets'));
        document.getElementById('cb-settings-back').addEventListener('click', () => this.showView('widgets'));
        document.getElementById('cb-navigator-back').addEventListener('click', () => this.showView('widgets'));
        document.getElementById('cb-open-navigator').addEventListener('click', () => {
            this.renderNavigator();
            this.showView('navigator');
        });

        // Element actions
        document.getElementById('cb-duplicate').addEventListener('click', () => this.duplicateSelected());
        document.getElementById('cb-delete').addEventListener('click', () => this.deleteSelected());

        document.getElementById('cb-elem-toolbar').addEventListener('click', (event) => {
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (!action) return;

            if (action === 'duplicate') this.duplicateSelected();
            if (action === 'delete') this.deleteSelected();
        });

        // Tabs
        this.el.tabs.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-tab]');
            if (!tab) return;

            this.tab = tab.dataset.tab;
            this.el.tabs.querySelectorAll('[data-tab]').forEach((b) => {
                b.setAttribute('aria-selected', String(b.dataset.tab === this.tab));
            });
            this.renderSettings();
        });

        // Device switcher
        document.querySelectorAll('[data-device]').forEach((button) => {
            if (button.closest('.cb-control__devices')) return;
            button.addEventListener('click', () => this.setDevice(button.dataset.device));
        });

        // Undo / redo / publish
        this.el.undo.addEventListener('click', () => this.state.undo());
        this.el.redo.addEventListener('click', () => this.state.redo());
        this.el.publish.addEventListener('click', () => this.publish());

        this.el.widgetSearch.addEventListener('input', () => this.renderWidgetPanel(this.el.widgetSearch.value));

        document.querySelectorAll('[data-starter]').forEach((button) => {
            button.addEventListener('click', () => {
                this.applyStarter(button.dataset.region, button.dataset.starter, button);
            });
        });

        document.addEventListener('keydown', (event) => this.handleShortcut(event));

        // Leaving with unsaved work should require a deliberate confirmation.
        window.addEventListener('beforeunload', (event) => {
            if (!this.dirty) return;
            event.preventDefault();
            event.returnValue = '';
        });

        this.bindModals();
    }

    handleShortcut(event) {
        const meta = event.ctrlKey || event.metaKey;
        if (!meta) return;

        if (event.key === 'z' && !event.shiftKey) {
            event.preventDefault();
            this.state.undo();
        } else if ((event.key === 'z' && event.shiftKey) || event.key === 'y') {
            event.preventDefault();
            this.state.redo();
        } else if (event.key === 's') {
            event.preventDefault();
            this.publish();
        } else if (event.key === 'd' && this.state.selection) {
            event.preventDefault();
            this.duplicateSelected();
        }
    }

    // Widget panel ---------------------------------------------------------

    renderWidgetPanel(query = '') {
        const term = query.trim().toLowerCase();
        this.el.widgetList.innerHTML = '';

        // Layout presets come first: most sections start from a column split.
        if (!term) {
            const layouts = document.createElement('div');
            layouts.className = 'cb-widget-group';
            layouts.innerHTML = '<h3>Add a section</h3>';

            const grid = document.createElement('div');
            grid.className = 'cb-layout-grid';

            Object.entries(this.boot.structure.presets).forEach(([key, widths]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'cb-layout-preset';
                button.title = widths.map((w) => `${Math.round(w)}%`).join(' / ');
                button.innerHTML = widths
                    .map((w) => `<span style="flex:0 0 ${w}%"></span>`)
                    .join('');

                button.addEventListener('click', () => this.addSection(widths));
                grid.appendChild(button);
            });

            layouts.appendChild(grid);
            this.el.widgetList.appendChild(layouts);
        }

        this.boot.widgets.forEach((group) => {
            const matching = group.widgets.filter((widget) => {
                if (!term) return true;
                return (
                    widget.name.toLowerCase().includes(term) ||
                    widget.type.includes(term) ||
                    (widget.keywords || []).some((k) => k.includes(term))
                );
            });

            if (!matching.length) return;

            const section = document.createElement('div');
            section.className = 'cb-widget-group';
            section.innerHTML = `<h3>${group.label}</h3>`;

            const grid = document.createElement('div');
            grid.className = 'cb-widget-grid';

            matching.forEach((widget) => {
                const tile = document.createElement('button');
                tile.type = 'button';
                tile.className = 'cb-widget-tile';
                tile.draggable = true;
                tile.dataset.widget = widget.type;
                tile.innerHTML = `${icon(widget.icon)}<span>${widget.name}</span>`;

                tile.addEventListener('dragstart', (event) => {
                    event.dataTransfer.effectAllowed = 'copy';
                    event.dataTransfer.setData('text/plain', widget.type);
                    this.canvas.beginDrag({ kind: 'widget', type: widget.type });
                });

                tile.addEventListener('dragend', () => this.canvas.endDrag());

                // Clicking appends to the end, for people who would rather not
                // drag at all.
                tile.addEventListener('click', () => this.addWidget(widget.type));

                grid.appendChild(tile);
            });

            section.appendChild(grid);
            this.el.widgetList.appendChild(section);
        });

        if (!this.el.widgetList.children.length) {
            this.el.widgetList.innerHTML = '<p class="cb-empty-note">No widgets match that search.</p>';
        }
    }

    /**
     * Drop a ready-made region layout into the tree.
     *
     * Inserted rather than saved directly, so it joins the undo stack and
     * stays a draft until published - the same as anything hand-built.
     */
    async applyStarter(region, key, button) {
        const original = button.innerHTML;
        button.disabled = true;

        try {
            const response = await fetch(`${this.boot.config.starterUrl}/${region}/${key}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('Could not load that starting point.');

            const { tree } = await response.json();

            tree.forEach((node) => this.state.insert(node, null));

            this.hideRegionNote();
            this.setStatus('Starting point added. Edit it, then publish.');
        } catch (error) {
            this.setStatus(error.message, 'error');
            button.innerHTML = original;
        } finally {
            button.disabled = false;
        }
    }

    /**
     * Warn when a functional page has lost the widget that makes it work.
     *
     * Checked live rather than only on publish, so the warning appears the
     * moment a cart widget is deleted rather than after the damage is live.
     */
    checkRequiredWidget() {
        const guard = document.getElementById('cb-guard');
        if (!guard) return;

        const required = guard.dataset.requires;
        let found = false;

        this.state.walk((node) => {
            if (node.widgetType === required) {
                found = true;
                return false;
            }
            return true;
        });

        // Only a warning while the layout has content: an empty layout is
        // fine, because the theme's own page is still being used.
        guard.hidden = found || this.state.tree.length === 0;
    }

    /** The note only applies while the region is untouched. */
    hideRegionNote() {
        const note = document.getElementById('cb-region-note');
        if (note) note.hidden = this.state.tree.length > 0;
    }

    showView(view) {
        this.el.viewWidgets.hidden = view !== 'widgets';
        this.el.viewSettings.hidden = view !== 'settings';
        this.el.viewNavigator.hidden = view !== 'navigator';
    }

    // Adding ---------------------------------------------------------------

    addSection(widths) {
        const section = makeSection(widths, this.defaultsFor('section'));
        this.state.insert(section, null);
        this.state.select(section.id);
    }

    /** Append a widget, creating a section for it when the page is empty. */
    addWidget(type) {
        const schema = this.boot.schemas[type];
        if (!schema) return;

        const widget = makeWidget(type, schema.defaults);

        let columnId = this.currentColumnId();

        if (!columnId) {
            const section = makeSection([100], this.defaultsFor('section'));
            this.state.insert(section, null);
            columnId = section.elements[0].id;
        }

        this.state.insert(widget, columnId, 'inside');
        this.state.select(widget.id);
    }

    /** The column a new widget should land in, based on the selection. */
    currentColumnId() {
        const selected = this.state.selected();

        if (selected) {
            if (selected.type === 'column') return selected.id;

            const at = this.state.locate(selected.id);
            if (at?.parent?.type === 'column') return at.parent.id;
            if (selected.type === 'section' && selected.elements?.length) return selected.elements[0].id;
        }

        const last = this.state.tree[this.state.tree.length - 1];
        return last?.elements?.[0]?.id || null;
    }

    defaultsFor(type) {
        const tabs = this.boot.structure[type] || {};
        const defaults = {};

        Object.values(tabs).forEach((sections) => {
            Object.values(sections).forEach((controls) => {
                controls.forEach((control) => {
                    if (control.default !== null && control.default !== undefined) {
                        defaults[control.key] = control.default;
                    }
                });
            });
        });

        return defaults;
    }

    handleDrop(payload, target) {
        if (payload.kind !== 'widget') return;

        const schema = this.boot.schemas[payload.type];
        if (!schema) return;

        const widget = makeWidget(payload.type, schema.defaults);

        if (!target.id) {
            const section = makeSection([100], this.defaultsFor('section'));
            this.state.insert(section, null);
            this.state.insert(widget, section.elements[0].id, 'inside');
        } else if (target.isSection) {
            const section = makeSection([100], this.defaultsFor('section'));
            this.state.insert(section, target.id, target.position);
            this.state.insert(widget, section.elements[0].id, 'inside');
        } else {
            this.state.insert(widget, target.id, target.position);
        }

        this.state.select(widget.id);
    }

    // Selection and settings ----------------------------------------------

    selectNode(id) {
        this.state.select(id);

        const node = this.state.selected();
        if (!node) return;

        this.canvas.selectedId = id;
        this.canvas.reposition();

        this.el.settingsTitle.textContent = this.labelFor(node);
        this.showView('settings');
        this.renderSettings();
    }

    labelFor(node) {
        if (node.type === 'section') return 'Section';
        if (node.type === 'column') return 'Column';
        return this.boot.schemas[node.widgetType]?.name || node.widgetType;
    }

    tabsFor(node) {
        if (node.type === 'section') return this.boot.structure.section;
        if (node.type === 'column') return this.boot.structure.column;
        return this.boot.schemas[node.widgetType]?.tabs || {};
    }

    renderSettings() {
        const node = this.state.selected();
        if (!node) return;

        this.controls.setDevice(this.device);
        this.controls.render(this.el.controls, this.tabsFor(node), this.tab, node.settings || {});
    }

    applySetting(control, value, options) {
        const node = this.state.selected();
        if (!node) return;

        this.state.updateSetting(node.id, control.key, value, options);

        // Conditional controls may need to appear or disappear.
        this.controls.applyConditions(this.el.controls, node.settings || {});
    }

    // Reacting to state ----------------------------------------------------

    onSettingChanged(node, styleOnly) {
        this.markDirty();

        if (styleOnly) {
            // No round trip: rewrite the preview stylesheet in place.
            this.refreshStyles();
            return;
        }

        this.rerenderNode(node);
    }

    onStructureChanged() {
        this.markDirty();
        this.rerenderAll();
        this.renderNavigator();
        this.hideRegionNote();
        this.checkRequiredWidget();
    }

    /** Controls for a node, flattened, for the client-side style compiler. */
    controlsFor(node) {
        const tabs = this.tabsFor(node);
        const flat = [];

        Object.values(tabs || {}).forEach((sections) => {
            Object.values(sections).forEach((controls) => flat.push(...controls));
        });

        return flat;
    }

    refreshStyles() {
        const css = compile(this.state.tree, (node) => this.controlsFor(node));
        this.canvas.setStyles(css);
    }

    async rerenderNode(node) {
        clearTimeout(this.renderTimer);

        // Debounced: typing into a text field should not fire a request per
        // keystroke.
        this.renderTimer = setTimeout(async () => {
            try {
                const { html } = await this.api.renderNode(node);

                if (!this.canvas.replaceNode(node.id, html)) {
                    this.rerenderAll();
                }

                this.refreshStyles();
            } catch (error) {
                this.setStatus(error.message, 'error');
            }
        }, 300);
    }

    async rerenderAll() {
        clearTimeout(this.fullRenderTimer);

        this.fullRenderTimer = setTimeout(async () => {
            try {
                const { html } = await this.api.renderTree(this.state.serialize());
                this.canvas.replaceAll(html);
                this.refreshStyles();
            } catch (error) {
                this.setStatus(error.message, 'error');
            }
        }, 120);
    }

    // Navigator ------------------------------------------------------------

    renderNavigator() {
        if (this.el.viewNavigator.hidden) return;

        const build = (nodes, depth = 0) => {
            const list = document.createElement('ul');
            list.className = 'cb-navigator__list';

            nodes.forEach((node) => {
                const item = document.createElement('li');

                const row = document.createElement('button');
                row.type = 'button';
                row.className = `cb-navigator__row ${node.id === this.state.selection ? 'is-active' : ''}`;
                row.style.paddingLeft = `${8 + depth * 14}px`;

                const iconName = node.type === 'widget'
                    ? this.boot.schemas[node.widgetType]?.icon || 'square'
                    : (node.type === 'section' ? 'tabs' : 'grid');

                row.innerHTML = `${icon(iconName)}<span>${this.labelFor(node)}</span>`;
                row.addEventListener('click', () => {
                    this.selectNode(node.id);
                    this.canvas.element(node.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });

                item.appendChild(row);

                if (node.elements?.length) item.appendChild(build(node.elements, depth + 1));

                list.appendChild(item);
            });

            return list;
        };

        this.el.navigator.innerHTML = '';

        if (!this.state.tree.length) {
            this.el.navigator.innerHTML = '<p class="cb-empty-note">This layout is empty.</p>';
            return;
        }

        this.el.navigator.appendChild(build(this.state.tree));
    }

    // Element actions ------------------------------------------------------

    duplicateSelected() {
        const node = this.state.selected();
        if (!node) return;

        const copy = this.state.duplicate(node.id);
        if (copy) this.state.select(copy.id);
    }

    deleteSelected() {
        const node = this.state.selected();
        if (!node) return;

        this.state.remove(node.id);
        this.showView('widgets');
        this.canvas.selectedId = null;
        this.canvas.reposition();
    }

    setDevice(device) {
        this.device = device;
        this.controls.setDevice(device);

        document.querySelectorAll('.cb-toolbar [data-device]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.device === device);
        });

        const width = this.boot.config.breakpoints?.[device]?.width;
        this.canvas.setDevice(width);

        this.renderSettings();
    }

    // Saving ---------------------------------------------------------------

    markDirty() {
        this.dirty = true;
        this.setStatus('Unsaved changes');
        this.scheduleAutosave();
    }

    scheduleAutosave() {
        clearTimeout(this.autosaveTimer);

        this.autosaveTimer = setTimeout(async () => {
            try {
                await this.api.saveDraft(this.state.serialize());
                this.setStatus('Draft saved');
            } catch (error) {
                this.setStatus('Could not save the draft', 'error');
            }
        }, this.boot.config.autosaveDelay);
    }

    async publish() {
        this.el.publish.disabled = true;
        this.setStatus('Publishing…');

        try {
            await this.api.publish(this.state.serialize());

            this.dirty = false;
            this.setStatus('Published', 'ok');
        } catch (error) {
            this.setStatus(error.message, 'error');
        } finally {
            this.el.publish.disabled = false;
        }
    }

    setStatus(message, tone = '') {
        this.el.saveState.textContent = message;
        this.el.saveState.className = `cb-save-state ${tone ? `is-${tone}` : ''}`;
    }

    updateHistoryButtons(history = this.state.historyState()) {
        this.el.undo.disabled = !history.canUndo;
        this.el.redo.disabled = !history.canRedo;
    }

    // Modals ---------------------------------------------------------------

    bindModals() {
        document.querySelectorAll('.cb-modal [data-close]').forEach((element) => {
            element.addEventListener('click', () => this.closeModals());
        });

        // Escape always closes, so a modal can never strand someone even if a
        // close button is ever missed.
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') this.closeModals();
        });

        document.getElementById('cb-media-upload').addEventListener('change', async (event) => {
            if (!event.target.files.length) return;

            try {
                await this.api.upload(event.target.files);
                this.loadMedia();
            } catch (error) {
                this.setStatus(error.message, 'error');
            }

            event.target.value = '';
        });

        document.getElementById('cb-icon-search').addEventListener('input', (event) => {
            this.renderIconGrid(event.target.value);
        });
    }

    /** Close every modal and forget whatever they were going to write into. */
    closeModals() {
        document.querySelectorAll('.cb-modal').forEach((modal) => {
            modal.hidden = true;
        });

        this.mediaCallback = null;
        this.iconCallback = null;
    }

    openMedia(callback, multiple = false) {
        // Only ever one at a time, so a second picker cannot open behind the
        // first and leave two stacked.
        this.closeModals();

        this.mediaCallback = callback;
        this.mediaMultiple = multiple;

        document.getElementById('cb-media-modal').hidden = false;

        this.loadMedia();
    }

    async loadMedia() {
        const grid = document.getElementById('cb-media-grid');
        grid.innerHTML = '<p class="cb-empty-note">Loading…</p>';

        try {
            const { data } = await this.api.media();

            grid.innerHTML = '';

            if (!data.length) {
                grid.innerHTML = '<p class="cb-empty-note">No images yet. Upload one to get started.</p>';
                return;
            }

            data.forEach((item) => {
                const cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'cb-media-cell';
                cell.innerHTML = `<img src="${item.thumb}" alt="${item.name}">`;

                cell.addEventListener('click', () => {
                    this.mediaCallback?.(item);

                    // A gallery keeps the picker open so several can be added.
                    if (!this.mediaMultiple) this.closeModals();
                });

                grid.appendChild(cell);
            });
        } catch (error) {
            grid.innerHTML = `<p class="cb-empty-note">${error.message}</p>`;
        }
    }

    openIcons(callback) {
        this.closeModals();

        this.iconCallback = callback;
        document.getElementById('cb-icon-modal').hidden = false;

        const search = document.getElementById('cb-icon-search');
        search.value = '';
        this.renderIconGrid('');
        search.focus();
    }

    renderIconGrid(query) {
        const grid = document.getElementById('cb-icon-grid');
        const term = query.trim().toLowerCase();

        grid.innerHTML = '';

        Object.entries(this.boot.icons).forEach(([group, names]) => {
            const matching = names.filter((name) => !term || name.includes(term));
            if (!matching.length) return;

            const heading = document.createElement('h4');
            heading.textContent = group;
            grid.appendChild(heading);

            const row = document.createElement('div');
            row.className = 'cb-icon-grid__row';

            matching.forEach((name) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.title = name;
                button.innerHTML = icon(name);
                button.addEventListener('click', () => {
                    this.iconCallback?.(name);
                    this.closeModals();
                });
                row.appendChild(button);
            });

            grid.appendChild(row);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.CB_BOOT) new Editor();
});
