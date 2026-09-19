import { mountRichTextEditors } from './editor/richtext.js';
import { registerMediaPicker } from './editor/media-picker.js';
import Alpine from 'alpinejs';

/**
 * The admin panel is server-rendered; Alpine covers the small amount of
 * interactivity it needs (menus, tabs, repeaters) without shipping a SPA.
 */
window.Alpine = Alpine;

/**
 * Backing store for the <x-form.media> component: a text path field with an
 * inline upload that posts to the media library and writes back the stored
 * path.
 */
Alpine.data('mediaField', (name, initial) => ({
    name,
    path: initial || '',
    preview: null,
    uploading: false,

    init() {
        this.syncPreview();
    },

    syncPreview() {
        if (!this.path) {
            this.preview = null;
            return;
        }

        this.preview = this.path.startsWith('http')
            ? this.path
            : `${window.CMS.storageUrl}/${this.path.replace(/^\/+/, '')}`;
    },

    clear() {
        this.path = '';
        this.preview = null;
    },

    async upload(event) {
        const file = event.target.files[0];
        if (!file) return;

        this.uploading = true;

        const body = new FormData();
        body.append('files[]', file);

        try {
            const response = await fetch(window.CMS.mediaUploadUrl, {
                method: 'POST',
                body,
                headers: {
                    'X-CSRF-TOKEN': window.CMS.csrfToken,
                    Accept: 'application/json',
                },
            });

            if (!response.ok) throw new Error('Upload failed');

            const { data } = await response.json();

            if (data && data.length) {
                this.path = data[0].path;
                this.preview = data[0].url;
            }
        } catch (error) {
            alert('The file could not be uploaded. Check the file type and size.');
        } finally {
            this.uploading = false;
            event.target.value = '';
        }
    },
}));

/**
 * Ordered image list for a product gallery. Each image posts as gallery[] with
 * its media id, so the order on screen is the order saved.
 */
Alpine.data('mediaGallery', (initial = []) => ({
    items: initial,
    uploading: false,

    add(media) {
        if (media?.id && !this.items.some((item) => item.id === media.id)) {
            this.items.push({ id: media.id, url: media.thumb || media.url });
        }
    },

    remove(index) {
        this.items.splice(index, 1);
    },

    move(index, step) {
        const target = index + step;
        if (target < 0 || target >= this.items.length) return;

        const [item] = this.items.splice(index, 1);
        this.items.splice(target, 0, item);
    },

    pick() {
        window.dispatchEvent(new CustomEvent('cms:pick-media', {
            detail: { onPick: (media) => this.add(media) },
        }));
    },

    async upload(event) {
        const files = [...event.target.files];
        if (!files.length) return;

        this.uploading = true;

        const body = new FormData();
        files.forEach((file) => body.append('files[]', file));

        try {
            const response = await fetch(window.CMS.mediaUploadUrl, {
                method: 'POST',
                body,
                headers: {
                    'X-CSRF-TOKEN': window.CMS.csrfToken,
                    Accept: 'application/json',
                },
            });

            const { data = [], rejected = [] } = await response.json();

            data.forEach((media) => this.add(media));

            if (!response.ok || rejected.length) {
                alert('Some files could not be uploaded. Check the file type and size.');
            }
        } catch (error) {
            alert('The files could not be uploaded. Check the file type and size.');
        } finally {
            this.uploading = false;
            event.target.value = '';
        }
    },
}));

/**
 * Repeater used by the product variant editor: add and remove rows without a
 * round trip, with the index rewritten so the array posts contiguously.
 */
Alpine.data('repeater', (initial = []) => ({
    rows: initial.length ? initial : [],

    add(row = {}) {
        this.rows.push(row);
    },

    remove(index) {
        this.rows.splice(index, 1);
    },
}));

/**
 * Backing store for <x-form.multiselect>: a dropdown of checkboxes with a
 * search box over them, for choosing from a list too long to scroll (the
 * countries a shop sells to, for instance).
 *
 * Only the ticked values are rendered as hidden inputs, so an untouched
 * dropdown posts nothing and the server reads that as an empty list.
 */
Alpine.data('multiSelect', (options = [], initial = []) => ({
    open: false,
    search: '',
    options,
    selected: initial.map((value) => String(value)),

    get filtered() {
        const term = this.search.trim().toLowerCase();

        if (!term) {
            return this.options;
        }

        return this.options.filter(
            (option) =>
                option.label.toLowerCase().includes(term) ||
                option.value.toLowerCase().includes(term),
        );
    },

    get summary() {
        if (!this.selected.length) {
            return null;
        }

        // Past a handful, the names stop being readable and a count is kinder.
        const names = this.selected
            .map((value) => this.options.find((option) => option.value === value)?.label)
            .filter(Boolean);

        return names.length > 4 ? `${names.length} selected` : names.join(', ');
    },

    isSelected(value) {
        return this.selected.includes(value);
    },

    toggle(value) {
        this.selected = this.isSelected(value)
            ? this.selected.filter((existing) => existing !== value)
            : [...this.selected, value];
    },

    /** Applies to what the search is currently showing, not the whole list. */
    selectVisible() {
        const visible = this.filtered.map((option) => option.value);

        this.selected = [...new Set([...this.selected, ...visible])];
    },

    clear() {
        this.selected = [];
    },
}));

/**
 * Rich text editing for the content forms.
 *
 * Mounted after Alpine so any textarea inside an Alpine-controlled panel is
 * already in the DOM by the time the editors are attached.
 */
registerMediaPicker();

document.addEventListener('DOMContentLoaded', () => mountRichTextEditors());

Alpine.start();
