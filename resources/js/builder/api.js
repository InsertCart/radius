/**
 * Thin wrappers over the editor's endpoints.
 *
 * Every call carries the CSRF token and reports failures through a single
 * path, so the editor can surface a problem rather than silently losing work.
 */

const token = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token(),
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        let message = `Request failed (${response.status})`;

        try {
            const data = await response.json();
            message = data.message || message;
        } catch (e) {
            // A non-JSON error page; the status line is all we have.
        }

        throw new Error(message);
    }

    return response.json();
}

export class Api {
    constructor(config) {
        this.config = config;
    }

    saveDraft(tree) {
        return post(this.config.saveUrl, { tree });
    }

    publish(tree) {
        return post(this.config.publishUrl, { tree });
    }

    /** Re-render a single node after a content change. */
    renderNode(node) {
        return post(this.config.renderUrl, { node });
    }

    /** Re-render everything after a structural change. */
    renderTree(tree) {
        return post(this.config.renderUrl, { tree });
    }

    async media(page = 1, query = '') {
        const url = new URL(this.config.mediaUrl, window.location.origin);
        url.searchParams.set('page', page);
        url.searchParams.set('type', 'image');
        if (query) url.searchParams.set('q', query);

        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Could not load the media library.');

        return response.json();
    }

    async upload(files) {
        const body = new FormData();
        Array.from(files).forEach((file) => body.append('files[]', file));

        const response = await fetch(this.config.uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
            body,
        });

        const payload = await response.json().catch(() => ({}));

        // Safety rejections and validation errors carry a readable reason.
        const problems = [...(payload.rejected || []), ...Object.values(payload.errors || {}).flat()];

        if (problems.length) throw new Error(problems.join(' '));
        if (!response.ok) throw new Error('The upload failed.');

        return payload;
    }

    savePreset(name, data) {
        return post(this.config.presetsUrl, { name, data });
    }
}
