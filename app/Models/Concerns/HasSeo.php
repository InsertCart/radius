<?php

namespace App\Models\Concerns;

/**
 * Shared behaviour for every model that owns a set of SEO columns.
 *
 * Each model decides what a sensible fallback looks like by overriding
 * seoFallbackTitle() / seoFallbackDescription(); the SEO manager then never
 * has to special-case model types when it builds the document head.
 */
trait HasSeo
{
    /** Columns added by the seoColumns() helper in the migrations. */
    public function seoAttributes(): array
    {
        return [
            'meta_title', 'meta_description', 'meta_keywords', 'og_image',
            'canonical_url', 'schema_type', 'schema_data', 'noindex',
        ];
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->seoFallbackTitle();
    }

    public function seoDescription(): string
    {
        return (string) ($this->meta_description ?: $this->seoFallbackDescription());
    }

    public function seoImage(): ?string
    {
        return $this->og_image ?: ($this->featured_image ?? null);
    }

    public function seoSchemaType(): ?string
    {
        return $this->schema_type ?: $this->seoDefaultSchemaType();
    }

    protected function seoFallbackTitle(): string
    {
        return (string) ($this->title ?? $this->name ?? '');
    }

    protected function seoFallbackDescription(): string
    {
        $source = $this->excerpt ?? $this->short_description ?? $this->description ?? $this->content ?? '';

        return \Illuminate\Support\Str::limit(trim(strip_tags((string) $source)), 155);
    }

    protected function seoDefaultSchemaType(): ?string
    {
        return null;
    }
}
