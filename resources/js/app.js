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
 * Rich text editing for the content forms.
 *
 * Mounted after Alpine so any textarea inside an Alpine-controlled panel is
 * already in the DOM by the time the editors are attached.
 */
registerMediaPicker();

document.addEventListener('DOMContentLoaded', () => mountRichTextEditors());

Alpine.start();
