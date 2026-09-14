/**
 * Editor chrome icons.
 *
 * The markup comes from a hidden sprite the server rendered, so the icon set
 * lives in PHP only and the two never drift apart.
 */

let sprite = null;

function load() {
    if (sprite) return sprite;

    sprite = new Map();

    document.querySelectorAll('#cb-icon-sprite template[data-icon]').forEach((template) => {
        sprite.set(template.dataset.icon, template.innerHTML.trim());
    });

    return sprite;
}

export function icon(name) {
    if (!name) return '';
    return load().get(name) || load().get('square') || '';
}

export function iconNames() {
    return Array.from(load().keys());
}
