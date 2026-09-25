/**
 * Renders the settings panel.
 *
 * Controls are described by data from the server, so this file knows about
 * control *types* but nothing about individual widgets. Adding a setting to a
 * block never means touching the editor.
 */

import { RichTextEditor } from '../editor/richtext.js';
import { icon } from './icons.js';

/** Controls whose value maps straight to CSS and needs no re-render. */
export function isStyleOnly(control) {
    return Array.isArray(control.selectors) && control.selectors.length > 0;
}

export class ControlRenderer {
    constructor({ boot, onChange, openMedia, openIcons }) {
        this.boot = boot;
        this.onChange = onChange;
        this.openMedia = openMedia;
        this.openIcons = openIcons;
        this.device = 'desktop';
    }

    setDevice(device) {
        this.device = device;
    }

    /** Render one tab's worth of controls into a container. */
    render(container, tabs, tab, settings) {
        container.innerHTML = '';

        const sections = tabs[tab];

        if (!sections) {
            container.innerHTML = '<p class="cb-empty-note">Nothing to configure here.</p>';
            return;
        }

        Object.entries(sections).forEach(([sectionName, controls]) => {
            const group = document.createElement('section');
            group.className = 'cb-control-group';

            const heading = document.createElement('button');
            heading.type = 'button';
            heading.className = 'cb-control-group__head';
            heading.innerHTML = `<span>${escapeHtml(sectionName)}</span>${icon('chevron-down')}`;

            const body = document.createElement('div');
            body.className = 'cb-control-group__body';

            heading.addEventListener('click', () => {
                const collapsed = group.classList.toggle('is-collapsed');
                heading.setAttribute('aria-expanded', String(!collapsed));
            });

            controls.forEach((control) => {
                const field = this.field(control, settings);
                if (field) body.appendChild(field);
            });

            // A group whose every control is conditionally hidden adds noise.
            if (!body.children.length) return;

            group.append(heading, body);
            container.appendChild(group);
        });

        this.applyConditions(container, settings);
    }

    field(control, settings) {
        if (control.type === 'separator') {
            const hr = document.createElement('hr');
            hr.className = 'cb-control-sep';
            return hr;
        }

        const wrap = document.createElement('div');
        wrap.className = `cb-control cb-control--${control.type}`;
        wrap.dataset.key = control.key;

        if (control.condition) {
            wrap.dataset.condition = JSON.stringify(control.condition);
        }

        if (control.type === 'notice') {
            wrap.innerHTML = `<div class="cb-notice"><strong>${escapeHtml(control.label)}</strong>
                <p>${escapeHtml(control.help || '')}</p></div>`;
            return wrap;
        }

        // Label row, with the device switcher for responsive controls.
        if (control.label) {
            const label = document.createElement('div');
            label.className = 'cb-control__label';
            label.innerHTML = `<span>${escapeHtml(control.label)}</span>`;

            if (control.responsive) {
                const devices = document.createElement('span');
                devices.className = 'cb-control__devices';
                ['desktop', 'tablet', 'mobile'].forEach((device) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.device = device;
                    button.className = device === this.device ? 'is-active' : '';
                    button.title = device;
                    button.innerHTML = icon(device);
                    button.addEventListener('click', () => {
                        this.device = device;
                        document.dispatchEvent(new CustomEvent('cb:device', { detail: device }));
                    });
                    devices.appendChild(button);
                });
                label.appendChild(devices);
            }

            wrap.appendChild(label);
        }

        const input = this.input(control, settings);
        if (input) wrap.appendChild(input);

        if (control.help) {
            const help = document.createElement('p');
            help.className = 'cb-control__help';
            help.textContent = control.help;
            wrap.appendChild(help);
        }

        return wrap;
    }

    /** Current value, unwrapping the device layer for responsive controls. */
    value(control, settings) {
        const raw = settings[control.key];

        if (control.responsive && raw && typeof raw === 'object' && !Array.isArray(raw)
            && ('desktop' in raw || 'tablet' in raw || 'mobile' in raw)) {
            return raw[this.device] ?? (this.device === 'desktop' ? undefined : raw.desktop);
        }

        return raw;
    }

    /** Write a value back, preserving the other devices. */
    commit(control, settings, value, coalesce) {
        let next = value;

        if (control.responsive) {
            const existing = settings[control.key];
            const base = existing && typeof existing === 'object' && !Array.isArray(existing)
                && ('desktop' in existing || 'tablet' in existing || 'mobile' in existing)
                ? { ...existing }
                : {};

            base[this.device] = value;
            next = base;
        }

        this.onChange(control, next, { styleOnly: isStyleOnly(control), coalesce });
    }

    input(control, settings) {
        const value = this.value(control, settings);

        const builders = {
            text: () => this.textInput(control, settings, value, 'text'),
            email: () => this.textInput(control, settings, value, 'email'),
            url: () => this.textInput(control, settings, value, 'url'),
            number: () => this.numberInput(control, settings, value),
            textarea: () => this.textarea(control, settings, value),
            code: () => this.textarea(control, settings, value, true),
            richtext: () => this.richtext(control, settings, value),
            select: () => this.select(control, settings, value),
            source: () => this.select(control, settings, value),
            choose: () => this.choose(control, settings, value),
            toggle: () => this.toggle(control, settings, value),
            color: () => this.color(control, settings, value),
            slider: () => this.slider(control, settings, value),
            dimensions: () => this.dimensions(control, settings, value),
            typography: () => this.typography(control, settings, value),
            border: () => this.border(control, settings, value),
            shadow: () => this.shadow(control, settings, value),
            background: () => this.background(control, settings, value),
            image: () => this.image(control, settings, value),
            gallery: () => this.gallery(control, settings, value),
            icon: () => this.iconPicker(control, settings, value),
            link: () => this.link(control, settings, value),
            repeater: () => this.repeater(control, settings, value),
        };

        return (builders[control.type] || builders.text)();
    }

    // Simple inputs --------------------------------------------------------

    textInput(control, settings, value, type) {
        const input = document.createElement('input');
        input.type = type;
        input.className = 'cb-input';
        input.value = value ?? '';
        if (control.placeholder) input.placeholder = control.placeholder;

        input.addEventListener('input', () => {
            this.commit(control, settings, input.value, `${control.key}:text`);
        });

        return input;
    }

    numberInput(control, settings, value) {
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'cb-input';
        input.value = value ?? '';
        if (control.min !== undefined) input.min = control.min;
        if (control.max !== undefined) input.max = control.max;
        if (control.step !== undefined) input.step = control.step;

        input.addEventListener('input', () => {
            this.commit(control, settings, input.value === '' ? '' : Number(input.value), `${control.key}:num`);
        });

        return input;
    }

    textarea(control, settings, value, mono = false) {
        const input = document.createElement('textarea');
        input.className = mono ? 'cb-input cb-input--mono' : 'cb-input';
        input.rows = mono ? 8 : 4;
        input.value = value ?? '';
        if (control.placeholder) input.placeholder = control.placeholder;

        input.addEventListener('input', () => {
            this.commit(control, settings, input.value, `${control.key}:text`);
        });

        return input;
    }

    /**
     * The same editor the admin content forms use, so formatting behaves
     * identically whether someone is writing a page or a text widget.
     */
    richtext(control, settings, value) {
        const holder = document.createElement('div');
        const textarea = document.createElement('textarea');
        textarea.value = value ?? '';
        holder.appendChild(textarea);

        // Mounted on the next frame: the editor measures its surface, and
        // the panel has not laid this out yet.
        requestAnimationFrame(() => {
            new RichTextEditor(textarea, {
                label: control.label || 'Content',
                minHeight: '200px',
                openMedia: this.openMedia,
                onChange: (html) => this.commit(control, settings, html, `${control.key}:rich`),
            });
        });

        return holder;
    }

    select(control, settings, value) {
        const select = document.createElement('select');
        select.className = 'cb-input';

        const options = control.type === 'source'
            ? this.boot.sources?.[control.source] || {}
            : control.options || {};

        if (control.type === 'source') {
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = '— none —';
            select.appendChild(blank);
        }

        Object.entries(options).forEach(([key, label]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = typeof label === 'object' ? label.label : label;
            option.selected = String(value ?? '') === String(key);
            select.appendChild(option);
        });

        select.addEventListener('change', () => {
            this.commit(control, settings, select.value);
        });

        return select;
    }

    choose(control, settings, value) {
        const group = document.createElement('div');
        group.className = 'cb-choose';

        Object.entries(control.options || {}).forEach(([key, option]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.title = option.label || key;
            button.className = String(value ?? '') === key ? 'is-active' : '';
            button.innerHTML = icon(option.icon || 'square');

            button.addEventListener('click', () => {
                group.querySelectorAll('button').forEach((b) => b.classList.remove('is-active'));
                button.classList.add('is-active');
                this.commit(control, settings, key);
            });

            group.appendChild(button);
        });

        return group;
    }

    toggle(control, settings, value) {
        const label = document.createElement('label');
        label.className = 'cb-switch';

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = Boolean(value);

        const track = document.createElement('span');
        track.className = 'cb-switch__track';

        input.addEventListener('change', () => {
            this.commit(control, settings, input.checked);
        });

        label.append(input, track);
        return label;
    }

    color(control, settings, value) {
        const wrap = document.createElement('div');
        wrap.className = 'cb-color';

        const swatch = document.createElement('input');
        swatch.type = 'color';
        swatch.className = 'cb-color__swatch';
        // A CSS variable cannot be shown in a native colour input, so the
        // swatch falls back to a neutral while the text field keeps the token.
        swatch.value = /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#2563eb';

        const text = document.createElement('input');
        text.type = 'text';
        text.className = 'cb-input cb-color__text';
        text.value = value ?? '';
        text.placeholder = 'Inherit';

        swatch.addEventListener('input', () => {
            text.value = swatch.value;
            this.commit(control, settings, swatch.value, `${control.key}:color`);
        });

        text.addEventListener('input', () => {
            this.commit(control, settings, text.value, `${control.key}:color`);
        });

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'cb-color__clear';
        clear.title = 'Clear';
        clear.innerHTML = icon('close');
        clear.addEventListener('click', () => {
            text.value = '';
            this.commit(control, settings, '');
        });

        wrap.append(swatch, text, clear);

        // Palette tokens, so a site keeps a consistent set of colours.
        const tokens = this.boot.tokens?.color || [];
        if (tokens.length) {
            const palette = document.createElement('div');
            palette.className = 'cb-palette';

            tokens.forEach((token) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.title = token.label;
                dot.style.background = token.value;
                dot.addEventListener('click', () => {
                    const reference = `var(${token.variable}, ${token.value})`;
                    text.value = reference;
                    this.commit(control, settings, reference);
                });
                palette.appendChild(dot);
            });

            wrap.appendChild(palette);
        }

        return wrap;
    }

    slider(control, settings, value) {
        const current = value && typeof value === 'object' ? value : { size: value ?? '', unit: (control.units || ['px'])[0] };

        const wrap = document.createElement('div');
        wrap.className = 'cb-slider';

        const range = document.createElement('input');
        range.type = 'range';
        range.min = control.min ?? 0;
        range.max = control.max ?? 100;
        range.step = control.step ?? 1;
        range.value = current.size === '' ? range.min : current.size;

        const number = document.createElement('input');
        number.type = 'number';
        number.className = 'cb-input cb-slider__number';
        number.value = current.size ?? '';
        number.min = control.min ?? 0;
        number.max = control.max ?? 100;
        number.step = control.step ?? 1;

        const units = control.units || ['px'];
        const unitSelect = document.createElement('select');
        unitSelect.className = 'cb-slider__unit';
        units.forEach((unit) => {
            const option = document.createElement('option');
            option.value = unit;
            option.textContent = unit || '—';
            option.selected = (current.unit ?? units[0]) === unit;
            unitSelect.appendChild(option);
        });
        if (units.length < 2) unitSelect.hidden = true;

        const push = (size) => {
            this.commit(control, settings, { size, unit: unitSelect.value }, `${control.key}:slider`);
        };

        range.addEventListener('input', () => {
            number.value = range.value;
            push(Number(range.value));
        });

        number.addEventListener('input', () => {
            range.value = number.value;
            push(number.value === '' ? '' : Number(number.value));
        });

        unitSelect.addEventListener('change', () => push(number.value === '' ? '' : Number(number.value)));

        wrap.append(range, number, unitSelect);
        return wrap;
    }

    dimensions(control, settings, value) {
        const current = value && typeof value === 'object' ? { ...value } : {};
        const units = control.units || ['px', 'em', 'rem', '%'];

        const wrap = document.createElement('div');
        wrap.className = 'cb-dimensions';

        const inputs = {};
        const sides = ['top', 'right', 'bottom', 'left'];

        let linked = current.linked ?? false;

        const push = () => {
            const next = { unit: unitSelect.value, linked };
            sides.forEach((side) => {
                next[side] = inputs[side].value === '' ? '' : Number(inputs[side].value);
            });
            this.commit(control, settings, next, `${control.key}:dim`);
        };

        sides.forEach((side) => {
            const cell = document.createElement('label');
            cell.className = 'cb-dimensions__cell';

            const input = document.createElement('input');
            input.type = 'number';
            input.value = current[side] ?? '';
            input.placeholder = '0';

            input.addEventListener('input', () => {
                // With the sides linked, typing in one fills them all - the
                // usual shorthand for even padding.
                if (linked) sides.forEach((s) => { inputs[s].value = input.value; });
                push();
            });

            const caption = document.createElement('span');
            caption.textContent = side[0].toUpperCase();

            cell.append(input, caption);
            inputs[side] = input;
            wrap.appendChild(cell);
        });

        const unitSelect = document.createElement('select');
        unitSelect.className = 'cb-dimensions__unit';
        units.forEach((unit) => {
            const option = document.createElement('option');
            option.value = unit;
            option.textContent = unit;
            option.selected = (current.unit ?? units[0]) === unit;
            unitSelect.appendChild(option);
        });
        unitSelect.addEventListener('change', push);

        const link = document.createElement('button');
        link.type = 'button';
        link.className = `cb-dimensions__link ${linked ? 'is-active' : ''}`;
        link.title = 'Link values together';
        link.innerHTML = icon('lock');
        link.addEventListener('click', () => {
            linked = !linked;
            link.classList.toggle('is-active', linked);
            push();
        });

        wrap.append(unitSelect, link);
        return wrap;
    }

    typography(control, settings, value) {
        const current = value && typeof value === 'object' ? { ...value } : {};

        const wrap = document.createElement('div');
        wrap.className = 'cb-typography';

        const push = (key, v) => {
            current[key] = v;
            this.commit(control, settings, { ...current }, `${control.key}:typo`);
        };

        // Family
        const family = document.createElement('select');
        family.className = 'cb-input';
        Object.entries(this.boot.fonts?.system || {}).forEach(([v, label]) => {
            family.appendChild(new Option(label, v, false, current.family === v));
        });
        (this.boot.fonts?.google || []).forEach((name) => {
            family.appendChild(new Option(name, name, false, current.family === name));
        });
        family.addEventListener('change', () => push('family', family.value));
        wrap.appendChild(labelled('Family', family));

        // Size / line height / letter spacing
        [['size', 'Size', 8, 120, 'px'], ['line_height', 'Line height', 0.8, 3, ''], ['letter_spacing', 'Letter spacing', -5, 20, 'px']]
            .forEach(([key, label, min, max, unit]) => {
                const input = document.createElement('input');
                input.type = 'number';
                input.className = 'cb-input';
                input.min = min;
                input.max = max;
                input.step = key === 'line_height' ? 0.05 : 1;
                input.value = current[key]?.size ?? '';
                input.addEventListener('input', () => {
                    push(key, input.value === '' ? '' : { size: Number(input.value), unit });
                });
                wrap.appendChild(labelled(label, input));
            });

        // Weight / transform
        const weight = document.createElement('select');
        weight.className = 'cb-input';
        ['', '300', '400', '500', '600', '700', '800', '900'].forEach((w) => {
            weight.appendChild(new Option(w || 'Default', w, false, String(current.weight ?? '') === w));
        });
        weight.addEventListener('change', () => push('weight', weight.value));
        wrap.appendChild(labelled('Weight', weight));

        const transform = document.createElement('select');
        transform.className = 'cb-input';
        [['', 'Default'], ['none', 'None'], ['uppercase', 'UPPERCASE'], ['lowercase', 'lowercase'], ['capitalize', 'Capitalise']]
            .forEach(([v, label]) => {
                transform.appendChild(new Option(label, v, false, (current.transform ?? '') === v));
            });
        transform.addEventListener('change', () => push('transform', transform.value));
        wrap.appendChild(labelled('Transform', transform));

        return wrap;
    }

    border(control, settings, value) {
        const current = value && typeof value === 'object' ? { ...value } : {};

        const wrap = document.createElement('div');
        wrap.className = 'cb-subgroup';

        const push = () => this.commit(control, settings, { ...current }, `${control.key}:border`);

        const style = document.createElement('select');
        style.className = 'cb-input';
        ['none', 'solid', 'dashed', 'dotted', 'double'].forEach((v) => {
            style.appendChild(new Option(v === 'none' ? 'None' : v[0].toUpperCase() + v.slice(1), v, false, (current.style ?? 'none') === v));
        });
        style.addEventListener('change', () => { current.style = style.value; push(); });
        wrap.appendChild(labelled('Style', style));

        const width = document.createElement('input');
        width.type = 'number';
        width.className = 'cb-input';
        width.min = 0;
        width.value = current.width?.top ?? '';
        width.addEventListener('input', () => {
            const v = width.value === '' ? '' : Number(width.value);
            current.width = { top: v, right: v, bottom: v, left: v, unit: 'px' };
            push();
        });
        wrap.appendChild(labelled('Width (px)', width));

        const color = document.createElement('input');
        color.type = 'color';
        color.className = 'cb-color__swatch';
        color.value = /^#[0-9a-f]{6}$/i.test(current.color || '') ? current.color : '#e2e8f0';
        color.addEventListener('input', () => { current.color = color.value; push(); });
        wrap.appendChild(labelled('Colour', color));

        return wrap;
    }

    shadow(control, settings, value) {
        const current = value && typeof value === 'object' ? { ...value } : { h: 0, v: 4, blur: 12, spread: 0, color: '' };

        const wrap = document.createElement('div');
        wrap.className = 'cb-subgroup';

        const push = () => this.commit(control, settings, { ...current }, `${control.key}:shadow`);

        [['h', 'X offset'], ['v', 'Y offset'], ['blur', 'Blur'], ['spread', 'Spread']].forEach(([key, label]) => {
            const input = document.createElement('input');
            input.type = 'number';
            input.className = 'cb-input';
            input.value = current[key] ?? 0;
            input.addEventListener('input', () => { current[key] = Number(input.value); push(); });
            wrap.appendChild(labelled(label, input));
        });

        const color = document.createElement('input');
        color.type = 'color';
        color.className = 'cb-color__swatch';
        color.value = /^#[0-9a-f]{6}$/i.test(current.color || '') ? current.color : '#000000';
        color.addEventListener('input', () => { current.color = color.value; push(); });
        wrap.appendChild(labelled('Colour', color));

        return wrap;
    }

    background(control, settings, value) {
        const current = value && typeof value === 'object' ? { ...value } : { type: 'classic' };

        const wrap = document.createElement('div');
        wrap.className = 'cb-subgroup';

        const push = () => this.commit(control, settings, { ...current }, `${control.key}:bg`);

        const type = document.createElement('select');
        type.className = 'cb-input';
        [['classic', 'Colour or image'], ['gradient', 'Gradient']].forEach(([v, label]) => {
            type.appendChild(new Option(label, v, false, (current.type ?? 'classic') === v));
        });
        wrap.appendChild(labelled('Type', type));

        const classic = document.createElement('div');
        const gradient = document.createElement('div');

        const color = document.createElement('input');
        color.type = 'color';
        color.className = 'cb-color__swatch';
        color.value = /^#[0-9a-f]{6}$/i.test(current.color || '') ? current.color : '#ffffff';
        color.addEventListener('input', () => { current.color = color.value; push(); });
        classic.appendChild(labelled('Colour', color));

        const pick = document.createElement('button');
        pick.type = 'button';
        pick.className = 'cb-btn cb-btn--ghost cb-btn--block';
        pick.textContent = current.image ? 'Change image' : 'Choose image';
        pick.addEventListener('click', () => {
            this.openMedia((media) => {
                current.image = media.path;
                pick.textContent = 'Change image';
                push();
            });
        });
        classic.appendChild(labelled('Image', pick));

        [['gradient_from', 'From', '#ffffff'], ['gradient_to', 'To', '#2563eb']].forEach(([key, label, fallback]) => {
            const input = document.createElement('input');
            input.type = 'color';
            input.className = 'cb-color__swatch';
            input.value = /^#[0-9a-f]{6}$/i.test(current[key] || '') ? current[key] : fallback;
            input.addEventListener('input', () => { current[key] = input.value; push(); });
            gradient.appendChild(labelled(label, input));
        });

        const angle = document.createElement('input');
        angle.type = 'number';
        angle.className = 'cb-input';
        angle.value = current.gradient_angle ?? 180;
        angle.addEventListener('input', () => { current.gradient_angle = Number(angle.value); push(); });
        gradient.appendChild(labelled('Angle', angle));

        const sync = () => {
            classic.hidden = type.value === 'gradient';
            gradient.hidden = type.value !== 'gradient';
        };

        type.addEventListener('change', () => { current.type = type.value; sync(); push(); });
        sync();

        wrap.append(classic, gradient);
        return wrap;
    }

    image(control, settings, value) {
        const wrap = document.createElement('div');
        wrap.className = 'cb-media-field';

        const preview = document.createElement('div');
        preview.className = 'cb-media-field__preview';

        const paint = (path) => {
            preview.innerHTML = path
                ? `<img src="${resolveUrl(path)}" alt="">`
                : '<span>No image</span>';
        };

        paint(value);

        const choose = document.createElement('button');
        choose.type = 'button';
        choose.className = 'cb-btn cb-btn--ghost';
        choose.textContent = 'Choose';
        choose.addEventListener('click', () => {
            this.openMedia((media) => {
                paint(media.path);
                this.commit(control, settings, media.path);
            });
        });

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'cb-btn cb-btn--ghost';
        clear.textContent = 'Clear';
        clear.addEventListener('click', () => {
            paint(null);
            this.commit(control, settings, '');
        });

        const actions = document.createElement('div');
        actions.className = 'cb-media-field__actions';
        actions.append(choose, clear);

        wrap.append(preview, actions);
        return wrap;
    }

    gallery(control, settings, value) {
        const items = Array.isArray(value) ? [...value] : [];

        const wrap = document.createElement('div');
        wrap.className = 'cb-gallery-field';

        const grid = document.createElement('div');
        grid.className = 'cb-gallery-field__grid';

        const paint = () => {
            grid.innerHTML = '';
            items.forEach((item, index) => {
                const cell = document.createElement('div');
                cell.className = 'cb-gallery-field__item';
                cell.innerHTML = `<img src="${resolveUrl(item)}" alt="">`;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.innerHTML = icon('close');
                remove.addEventListener('click', () => {
                    items.splice(index, 1);
                    paint();
                    this.commit(control, settings, [...items]);
                });

                cell.appendChild(remove);
                grid.appendChild(cell);
            });
        };

        paint();

        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'cb-btn cb-btn--ghost cb-btn--block';
        add.textContent = 'Add images';
        add.addEventListener('click', () => {
            this.openMedia((media) => {
                items.push(media.path);
                paint();
                this.commit(control, settings, [...items]);
            }, true);
        });

        wrap.append(grid, add);
        return wrap;
    }

    iconPicker(control, settings, value) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cb-icon-field';

        const paint = (name) => {
            button.innerHTML = name
                ? `${icon(name)}<span>${escapeHtml(name)}</span>`
                : '<span>Choose an icon</span>';
        };

        paint(value);

        button.addEventListener('click', () => {
            this.openIcons((name) => {
                paint(name);
                this.commit(control, settings, name);
            });
        });

        return button;
    }

    link(control, settings, value) {
        const current = value && typeof value === 'object' ? { ...value } : { url: value || '', target: false, nofollow: false };

        const wrap = document.createElement('div');
        wrap.className = 'cb-subgroup';

        const push = () => this.commit(control, settings, { ...current }, `${control.key}:link`);

        const url = document.createElement('input');
        url.type = 'text';
        url.className = 'cb-input';
        url.placeholder = 'https://example.com or /about';
        url.value = current.url ?? '';
        url.addEventListener('input', () => { current.url = url.value; push(); });
        wrap.appendChild(url);

        [['target', 'Open in a new tab'], ['nofollow', 'Add nofollow']].forEach(([key, label]) => {
            const row = document.createElement('label');
            row.className = 'cb-checkline';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = Boolean(current[key]);
            input.addEventListener('change', () => { current[key] = input.checked; push(); });

            row.append(input, document.createTextNode(label));
            wrap.appendChild(row);
        });

        return wrap;
    }

    repeater(control, settings, value) {
        const items = Array.isArray(value) ? JSON.parse(JSON.stringify(value)) : [];

        const wrap = document.createElement('div');
        wrap.className = 'cb-repeater';

        const push = () => this.commit(control, settings, JSON.parse(JSON.stringify(items)));

        const paint = () => {
            wrap.innerHTML = '';

            items.forEach((item, index) => {
                const row = document.createElement('div');
                row.className = 'cb-repeater__item';

                const head = document.createElement('button');
                head.type = 'button';
                head.className = 'cb-repeater__head';
                head.innerHTML = `<span>${escapeHtml(item.title || item.text || item.label || item.author || item.name || item.quote || `Item ${index + 1}`)}</span>`;

                const body = document.createElement('div');
                body.className = 'cb-repeater__body';
                body.hidden = true;

                head.addEventListener('click', () => { body.hidden = !body.hidden; });

                const itemRenderer = new ControlRenderer({
                    boot: this.boot,
                    onChange: (nestedControl, val) => {
                        item[nestedControl.key] = val;
                        head.querySelector('span').textContent =
                            item.title || item.text || item.label || item.author || item.name || item.quote || `Item ${index + 1}`;
                        // A nested control can depend on a sibling - choices
                        // only matter for a dropdown - so re-check on change.
                        itemRenderer.applyConditions(body, item);
                        push();
                    },
                    openMedia: this.openMedia,
                    openIcons: this.openIcons,
                });
                itemRenderer.setDevice(this.device);

                (control.fields || []).forEach((field) => {
                    const nested = itemRenderer.field(field, item);
                    if (nested) body.appendChild(nested);
                });

                itemRenderer.applyConditions(body, item);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'cb-repeater__remove';
                remove.innerHTML = icon('close');
                remove.title = 'Remove';
                remove.addEventListener('click', () => {
                    items.splice(index, 1);
                    paint();
                    push();
                });

                head.appendChild(remove);
                row.append(head, body);
                wrap.appendChild(row);
            });

            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'cb-btn cb-btn--ghost cb-btn--block';
            add.textContent = 'Add item';
            add.addEventListener('click', () => {
                const blank = {};
                (control.fields || []).forEach((field) => {
                    blank[field.key] = field.default ?? '';
                });
                items.push(blank);
                paint();
                push();
            });

            wrap.appendChild(add);
        };

        paint();
        return wrap;
    }

    /**
     * Show or hide controls whose `condition` no longer matches, which is what
     * keeps a long panel readable.
     */
    applyConditions(container, settings) {
        container.querySelectorAll('[data-condition]').forEach((element) => {
            let condition;
            try {
                condition = JSON.parse(element.dataset.condition);
            } catch (e) {
                return;
            }

            const visible = Object.entries(condition).every(([key, expected]) => {
                const actual = settings[key];

                if (expected === '!empty') return actual !== '' && actual !== null && actual !== undefined;
                if (Array.isArray(expected)) return expected.map(String).includes(String(actual));
                if (typeof expected === 'boolean') return Boolean(actual) === expected;

                return String(actual ?? '') === String(expected);
            });

            element.hidden = !visible;
        });
    }
}

// Helpers ------------------------------------------------------------------

function labelled(text, input) {
    const row = document.createElement('label');
    row.className = 'cb-subfield';
    row.innerHTML = `<span>${escapeHtml(text)}</span>`;
    row.appendChild(input);
    return row;
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function resolveUrl(path) {
    if (!path) return '';
    const value = typeof path === 'object' ? path.url || path.path : path;
    if (!value) return '';
    return String(value).startsWith('http') ? value : `${window.CB_BOOT?.config?.storageUrl || '/storage'}/${String(value).replace(/^\/+/, '')}`;
}
