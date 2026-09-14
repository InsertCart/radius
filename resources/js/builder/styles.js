/**
 * Client-side mirror of the PHP StyleCompiler.
 *
 * The server stays authoritative: it recompiles the stylesheet on publish, and
 * that is what visitors receive. This exists purely so the editor can show a
 * colour or spacing change immediately instead of waiting on a round trip,
 * which is the difference between a builder that feels alive and one that
 * feels like a form.
 *
 * It is a mirror of the *value rendering*, not of any individual widget: both
 * sides read the same control definitions, so a new widget needs no changes
 * here.
 */

const BREAKPOINTS = { tablet: 1024, mobile: 767 };

const UNSAFE = /[{}<>;]|\/\*|\*\/|expression|javascript:|@import/gi;

function clean(value) {
    return String(value).replace(UNSAFE, '').trim();
}

function substitute(template, value) {
    return (template || '{{VALUE}}').replace('{{VALUE}}', value);
}

/** A slider stores {size, unit}; a bare number is accepted too. */
function renderSlider(property, value, template) {
    const size = value && typeof value === 'object' ? value.size : value;
    const unit = value && typeof value === 'object' ? value.unit ?? 'px' : 'px';

    if (size === '' || size === null || size === undefined || Number.isNaN(Number(size))) return null;

    return { [property]: substitute(template, `${size}${unit ?? ''}`) };
}

function renderDimensions(property, value) {
    if (!value || typeof value !== 'object') return null;

    const unit = value.unit || 'px';
    const sides = ['top', 'right', 'bottom', 'left'].map((side) => {
        const v = value[side];
        return v === '' || v === null || v === undefined || Number.isNaN(Number(v)) ? '0' : `${v}${unit}`;
    });

    const touched = ['top', 'right', 'bottom', 'left'].some(
        (side) => value[side] !== '' && value[side] !== null && value[side] !== undefined,
    );

    return touched ? { [property]: sides.join(' ') } : null;
}

function renderTypography(value) {
    if (!value || typeof value !== 'object') return null;

    const out = {};

    if (value.family) out['font-family'] = clean(value.family);
    if (value.weight) out['font-weight'] = clean(value.weight);
    if (value.transform) out['text-transform'] = clean(value.transform);
    if (value.style) out['font-style'] = clean(value.style);
    if (value.decoration) out['text-decoration'] = clean(value.decoration);

    const map = { size: 'font-size', line_height: 'line-height', letter_spacing: 'letter-spacing' };

    Object.entries(map).forEach(([key, property]) => {
        const rendered = renderSlider(property, value[key], '{{VALUE}}');
        if (rendered) Object.assign(out, rendered);
    });

    return Object.keys(out).length ? out : null;
}

function renderBorder(value) {
    if (!value || typeof value !== 'object') return null;
    if (!value.style || value.style === 'none') return null;

    const out = { 'border-style': clean(value.style) };

    const width = renderDimensions('border-width', value.width);
    if (width) Object.assign(out, width);

    if (value.color) out['border-color'] = clean(value.color);

    return out;
}

function renderShadow(property, value) {
    if (!value || typeof value !== 'object' || !value.color) return null;

    const parts = [
        `${Number(value.h) || 0}px`,
        `${Number(value.v) || 0}px`,
        `${Number(value.blur ?? 10)}px`,
        `${Number(value.spread) || 0}px`,
        clean(value.color),
    ];

    if (value.inset) parts.unshift('inset');

    return { [property]: parts.join(' ') };
}

function renderBackground(value) {
    if (!value) return null;

    if (typeof value !== 'object') return { 'background-color': clean(value) };

    if (value.type === 'gradient') {
        const from = clean(value.gradient_from || '#ffffff');
        const to = clean(value.gradient_to || '#000000');
        const angle = Number(value.gradient_angle ?? 180);
        return { 'background-image': `linear-gradient(${angle}deg, ${from} 0%, ${to} 100%)` };
    }

    const out = {};

    if (value.color) out['background-color'] = clean(value.color);

    if (value.image) {
        const url = typeof value.image === 'object' ? value.image.url : value.image;
        if (url) {
            out['background-image'] = `url('${String(url).replace(/['"()]/g, '')}')`;
            out['background-size'] = clean(value.size || 'cover');
            out['background-position'] = clean(value.position || 'center center');
            out['background-repeat'] = clean(value.repeat || 'no-repeat');
            if (value.attachment === 'fixed') out['background-attachment'] = 'fixed';
        }
    }

    return Object.keys(out).length ? out : null;
}

function renderValue(type, value, mapping) {
    const { property, template } = mapping;

    switch (type) {
        case 'dimensions':
            return renderDimensions(property, value);
        case 'typography':
            return renderTypography(value);
        case 'border':
            return renderBorder(value);
        case 'shadow':
            return renderShadow(property, value);
        case 'background':
            return renderBackground(value);
        case 'slider':
            return renderSlider(property, value, template);
        case 'toggle':
            return value ? { [property]: substitute(template, '1') } : null;
        default: {
            if (value === null || value === undefined || value === '' || typeof value === 'object') return null;
            const v = clean(value);
            return v === '' ? null : { [property]: substitute(template, v) };
        }
    }
}

function isResponsive(value) {
    return value && typeof value === 'object'
        && ('desktop' in value || 'tablet' in value || 'mobile' in value);
}

/**
 * Compile a list of nodes to CSS.
 *
 * `controlsFor(node)` returns the flat control definitions for a node, which
 * the editor already has from the boot payload.
 */
export function compile(nodes, controlsFor) {
    const rules = { '': {}, tablet: {}, mobile: {} };

    const add = (device, selector, property, value) => {
        rules[device][selector] = rules[device][selector] || {};
        rules[device][selector][property] = value;
    };

    const emit = (id, control, value, device) => {
        const media = device === 'desktop' ? '' : device;

        (control.selectors || []).forEach((mapping) => {
            const rendered = renderValue(control.type, value, mapping);
            if (!rendered) return;

            // Combinators survive: the selector is written by a block class,
            // and the only user-controlled part is the already-validated id.
            const selector = mapping.selector.replace('{{WRAPPER}}', `.cb-${id}`).replace(/[{}<;]/g, '');

            Object.entries(rendered).forEach(([property, declaration]) => {
                add(media, selector, property, declaration);
            });
        });
    };

    const walk = (list) => {
        list.forEach((node) => {
            const controls = controlsFor(node) || [];
            const settings = node.settings || {};

            controls.forEach((control) => {
                if (!control.selectors || !control.selectors.length) return;

                const value = settings[control.key];
                if (value === null || value === undefined || value === '') return;

                if (control.responsive && isResponsive(value)) {
                    ['desktop', 'tablet', 'mobile'].forEach((device) => {
                        const deviceValue = value[device];
                        if (deviceValue === undefined || deviceValue === null || deviceValue === '') return;
                        emit(node.id, control, deviceValue, device);
                    });
                } else {
                    emit(node.id, control, value, 'desktop');
                }
            });

            if (node.elements) walk(node.elements);
        });
    };

    walk(nodes);

    const block = (selectors) =>
        Object.entries(selectors)
            .map(([selector, declarations]) => {
                const body = Object.entries(declarations)
                    .map(([property, value]) => `${property}:${value}`)
                    .join(';');
                return body ? `${selector}{${body}}` : '';
            })
            .join('');

    let css = block(rules['']);

    Object.entries(BREAKPOINTS).forEach(([device, width]) => {
        const inner = block(rules[device]);
        if (inner) css += `@media(max-width:${width}px){${inner}}`;
    });

    return css;
}
