/**
 * The editor's single source of truth.
 *
 * Everything else - the panel, the canvas, the navigator - reads from here and
 * subscribes to changes, so there is exactly one copy of the layout tree and
 * no chance of two views disagreeing about it.
 */

let nextId = 0;

/** Ids only need to be unique within a layout, and short enough to read. */
export function uid() {
    nextId += 1;
    return (
        Date.now().toString(36).slice(-4) +
        Math.random().toString(36).slice(2, 6) +
        nextId.toString(36)
    );
}

export class State {
    constructor(tree = [], options = {}) {
        this.tree = Array.isArray(tree) ? tree : [];
        this.selection = null;
        this.historyLimit = options.historyLimit || 50;

        this.past = [];
        this.future = [];
        this.listeners = new Map();

        this.ensureIds(this.tree);
    }

    // Events ---------------------------------------------------------------

    on(event, handler) {
        if (!this.listeners.has(event)) this.listeners.set(event, new Set());
        this.listeners.get(event).add(handler);
        return () => this.listeners.get(event).delete(handler);
    }

    emit(event, payload) {
        (this.listeners.get(event) || []).forEach((handler) => handler(payload));
    }

    // History --------------------------------------------------------------

    /**
     * Snapshot before a change.
     *
     * `coalesce` groups rapid edits of the same thing - dragging a slider, or
     * typing into a text field - into one undo step, so a single Ctrl+Z does
     * what the user expects rather than rewinding one character.
     */
    snapshot(coalesce = null) {
        if (coalesce && this.lastCoalesce === coalesce) return;

        this.lastCoalesce = coalesce;
        this.past.push(JSON.stringify(this.tree));

        if (this.past.length > this.historyLimit) this.past.shift();

        this.future = [];
        this.emit('history', this.historyState());
    }

    historyState() {
        return { canUndo: this.past.length > 0, canRedo: this.future.length > 0 };
    }

    undo() {
        if (!this.past.length) return;

        this.future.push(JSON.stringify(this.tree));
        this.tree = JSON.parse(this.past.pop());
        this.lastCoalesce = null;

        this.afterStructuralChange();
        this.emit('history', this.historyState());
    }

    redo() {
        if (!this.future.length) return;

        this.past.push(JSON.stringify(this.tree));
        this.tree = JSON.parse(this.future.pop());
        this.lastCoalesce = null;

        this.afterStructuralChange();
        this.emit('history', this.historyState());
    }

    // Lookup ---------------------------------------------------------------

    /** Walk the tree, calling back with (node, parentArray, index). */
    walk(callback, nodes = this.tree, parent = null) {
        for (let i = 0; i < nodes.length; i += 1) {
            const node = nodes[i];
            if (callback(node, nodes, i, parent) === false) return false;
            if (node.elements && this.walk(callback, node.elements, node) === false) return false;
        }
        return true;
    }

    find(id) {
        let found = null;
        this.walk((node) => {
            if (node.id === id) {
                found = node;
                return false;
            }
            return true;
        });
        return found;
    }

    /** The node, the array holding it, its index, and its parent node. */
    locate(id) {
        let result = null;
        this.walk((node, siblings, index, parent) => {
            if (node.id === id) {
                result = { node, siblings, index, parent };
                return false;
            }
            return true;
        });
        return result;
    }

    // Selection ------------------------------------------------------------

    select(id) {
        const node = id ? this.find(id) : null;
        this.selection = node ? node.id : null;
        this.emit('select', node);
    }

    selected() {
        return this.selection ? this.find(this.selection) : null;
    }

    // Mutation -------------------------------------------------------------

    /**
     * Change one setting on a node.
     *
     * `styleOnly` tells the caller that nothing needs re-rendering: the change
     * can be applied by rewriting the stylesheet, which is what makes colour
     * and spacing edits feel instant.
     */
    updateSetting(id, key, value, { styleOnly = false, coalesce = null } = {}) {
        const node = this.find(id);
        if (!node) return;

        this.snapshot(coalesce || `${id}:${key}`);

        node.settings = node.settings || {};
        node.settings[key] = value;

        this.emit('change', { node, key, value, styleOnly });
    }

    insert(node, targetId, position = 'inside') {
        this.snapshot();
        this.ensureIds([node]);

        if (!targetId) {
            this.tree.push(node);
        } else {
            const at = this.locate(targetId);
            if (!at) return null;

            if (position === 'inside') {
                at.node.elements = at.node.elements || [];
                at.node.elements.push(node);
            } else {
                at.siblings.splice(position === 'before' ? at.index : at.index + 1, 0, node);
            }
        }

        this.afterStructuralChange();
        return node;
    }

    remove(id) {
        const at = this.locate(id);
        if (!at) return;

        this.snapshot();
        at.siblings.splice(at.index, 1);

        // A column left with no widgets is fine, but a section with no columns
        // is invisible and unreachable, so it goes too.
        if (at.parent && at.parent.type === 'section' && at.parent.elements.length === 0) {
            const parentAt = this.locate(at.parent.id);
            if (parentAt) parentAt.siblings.splice(parentAt.index, 1);
        }

        if (this.selection === id) this.selection = null;

        this.afterStructuralChange();
    }

    duplicate(id) {
        const at = this.locate(id);
        if (!at) return null;

        this.snapshot();

        const copy = JSON.parse(JSON.stringify(at.node));
        this.reassignIds(copy);
        at.siblings.splice(at.index + 1, 0, copy);

        this.afterStructuralChange();
        return copy;
    }

    /** Move a node to a new parent or position. */
    move(id, targetId, position = 'inside') {
        if (id === targetId) return;

        const at = this.locate(id);
        if (!at) return;

        // Refuse to drop a node inside itself, which would detach the subtree.
        let ancestor = this.locate(targetId);
        while (ancestor) {
            if (ancestor.node.id === id) return;
            ancestor = ancestor.parent ? this.locate(ancestor.parent.id) : null;
        }

        this.snapshot();

        const [node] = at.siblings.splice(at.index, 1);
        const target = this.locate(targetId);

        if (!target) {
            this.tree.push(node);
        } else if (position === 'inside') {
            target.node.elements = target.node.elements || [];
            target.node.elements.push(node);
        } else {
            target.siblings.splice(position === 'before' ? target.index : target.index + 1, 0, node);
        }

        this.afterStructuralChange();
    }

    reorderTree(newTree) {
        this.snapshot();
        this.tree = newTree;
        this.afterStructuralChange();
    }

    afterStructuralChange() {
        this.lastCoalesce = null;
        this.emit('structure', this.tree);
    }

    // Ids ------------------------------------------------------------------

    ensureIds(nodes) {
        nodes.forEach((node) => {
            if (!node.id) node.id = uid();
            if (node.elements) this.ensureIds(node.elements);
        });
    }

    /** Fresh ids throughout, so a duplicate does not share styles. */
    reassignIds(node) {
        node.id = uid();
        (node.elements || []).forEach((child) => this.reassignIds(child));
    }

    serialize() {
        return JSON.parse(JSON.stringify(this.tree));
    }
}

// Node factories -----------------------------------------------------------

export function makeSection(columns = [100], defaults = {}) {
    return {
        id: uid(),
        type: 'section',
        settings: { ...defaults },
        elements: columns.map((width) => makeColumn(width)),
    };
}

export function makeColumn(width = 100, defaults = {}) {
    return {
        id: uid(),
        type: 'column',
        settings: { ...defaults, width: { desktop: width } },
        elements: [],
    };
}

export function makeWidget(type, defaults = {}) {
    return {
        id: uid(),
        type: 'widget',
        widgetType: type,
        settings: JSON.parse(JSON.stringify(defaults)),
        elements: [],
    };
}
