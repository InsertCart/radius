<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Cms\Seo\SeoManager;

/**
 * Shared SEO handling for the content CRUD screens.
 *
 * Posts, pages, products and categories all carry the same SEO columns, so the
 * rules and the schema-type list live here rather than in four controllers.
 */
trait HandlesSeoFields
{
    /** Validation rules for the SEO panel. */
    protected function seoRules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'schema_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(SeoManager::SCHEMA_TYPES))],
            'noindex' => ['nullable', 'boolean'],
        ];
    }

    /** The schema.org types offered in the dropdown. */
    protected function schemaTypes(): array
    {
        return SeoManager::SCHEMA_TYPES;
    }

    /**
     * Pull the SEO fields out of validated input, normalising the checkbox.
     */
    protected function seoAttributes(array $validated, \Illuminate\Http\Request $request): array
    {
        return [
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'meta_keywords' => $validated['meta_keywords'] ?? null,
            'og_image' => $validated['og_image'] ?? null,
            'canonical_url' => $validated['canonical_url'] ?? null,
            'schema_type' => $validated['schema_type'] ?? null,
            'noindex' => $request->boolean('noindex'),
        ];
    }
}
