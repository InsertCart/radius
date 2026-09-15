<?php

namespace App\Cms\Seo;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the document head for the public site.
 *
 * A controller either hands over a model (which carries its own SEO columns)
 * or sets values directly. Anything left unset falls back to the site-wide
 * defaults from the SEO settings group, so every page emits a complete head
 * even when nobody has filled anything in.
 */
class SeoManager
{
    private ?string $title = null;
    private ?string $description = null;
    private ?string $keywords = null;
    private ?string $image = null;
    private ?string $canonical = null;
    private ?bool $noindex = null;
    private ?string $schemaType = null;
    private array $schemaData = [];

    /** Extra JSON-LD graphs (breadcrumbs, FAQ, item lists). */
    private array $extraSchemas = [];

    /** Schema types offered in the admin dropdowns. */
    public const SCHEMA_TYPES = [
        'Organization' => 'Organization',
        'LocalBusiness' => 'Local business',
        'Person' => 'Person',
        'WebSite' => 'Website',
        'WebPage' => 'Web page',
        'Article' => 'Article',
        'NewsArticle' => 'News article',
        'BlogPosting' => 'Blog posting',
        'Product' => 'Product',
        'Store' => 'Store',
        'OnlineStore' => 'Online store',
        'Service' => 'Service',
        'Event' => 'Event',
        'Course' => 'Course',
        'Recipe' => 'Recipe',
        'FAQPage' => 'FAQ page',
        'CollectionPage' => 'Collection page',
        'ContactPage' => 'Contact page',
        'AboutPage' => 'About page',
        'SoftwareApplication' => 'Software application',
        'JobPosting' => 'Job posting',
        'VideoObject' => 'Video',
    ];

    // Fluent setters -----------------------------------------------------

    public function title(?string $value): static
    {
        $this->title = $value;

        return $this;
    }

    public function description(?string $value): static
    {
        $this->description = $value ? Str::limit(trim(strip_tags($value)), 160, '') : null;

        return $this;
    }

    public function keywords(?string $value): static
    {
        $this->keywords = $value;

        return $this;
    }

    public function image(?string $value): static
    {
        $this->image = $value;

        return $this;
    }

    public function canonical(?string $value): static
    {
        $this->canonical = $value;

        return $this;
    }

    public function noindex(bool $value = true): static
    {
        $this->noindex = $value;

        return $this;
    }

    public function schema(string $type, array $data = []): static
    {
        $this->schemaType = $type;
        $this->schemaData = $data;

        return $this;
    }

    public function addSchema(array $graph): static
    {
        $this->extraSchemas[] = $graph;

        return $this;
    }

    /**
     * Takes SEO values from any model using the HasSeo trait. Explicitly-set
     * values still win, so a controller can override one field.
     */
    public function forModel(Model $model): static
    {
        $this->title ??= method_exists($model, 'seoTitle') ? $model->seoTitle() : ($model->title ?? $model->name ?? null);
        $this->description ??= method_exists($model, 'seoDescription') ? $model->seoDescription() : null;
        $this->keywords ??= $model->meta_keywords ?? null;
        $this->image ??= method_exists($model, 'seoImage') ? $model->seoImage() : null;
        $this->canonical ??= $model->canonical_url ?? null;
        $this->schemaType ??= method_exists($model, 'seoSchemaType') ? $model->seoSchemaType() : null;

        if ($this->noindex === null && isset($model->noindex)) {
            $this->noindex = (bool) $model->noindex;
        }

        if (blank($this->schemaData) && filled($model->schema_data ?? null)) {
            $this->schemaData = (array) $model->schema_data;
        }

        return $this;
    }

    /** Applies an override row for a route with no model behind it. */
    public function forRoute(string $routeKey): static
    {
        $meta = SeoMeta::where('route_key', $routeKey)->first();

        if (! $meta) {
            return $this;
        }

        $this->title ??= $meta->meta_title;
        $this->description ??= $meta->meta_description;
        $this->keywords ??= $meta->meta_keywords;
        $this->image ??= $meta->og_image;
        $this->canonical ??= $meta->canonical_url;
        $this->schemaType ??= $meta->schema_type;
        $this->noindex ??= $meta->noindex;

        if (blank($this->schemaData) && filled($meta->schema_data)) {
            $this->schemaData = (array) $meta->schema_data;
        }

        return $this;
    }

    // Resolution ----------------------------------------------------------

    public function resolvedTitle(): string
    {
        $siteName = (string) setting('site_name', config('app.name'));
        $separator = (string) setting('title_separator', '|');
        $title = $this->title ?: (string) setting('meta_title', $siteName);

        // Avoid "Site Name | Site Name" on the homepage.
        if (trim($title) === trim($siteName)) {
            return $title;
        }

        return trim("{$title} {$separator} {$siteName}");
    }

    public function resolvedDescription(): string
    {
        return (string) ($this->description ?: setting('meta_description', setting('site_tagline', '')));
    }

    public function resolvedImage(): ?string
    {
        $image = $this->image ?: setting('og_image');

        if (blank($image)) {
            return null;
        }

        return str_starts_with($image, 'http')
            ? $image
            : Storage::disk(config('cms.media.disk'))->url($image);
    }

    public function resolvedCanonical(): string
    {
        return $this->canonical ?: url()->current();
    }

    public function isNoindex(): bool
    {
        // A site-wide "discourage search engines" switch overrides per-page
        // settings, since it exists precisely to hide a site under construction.
        return setting('noindex', false) ? true : (bool) $this->noindex;
    }

    // Rendering -----------------------------------------------------------

    /** The complete head block, emitted by the @seoHead Blade directive. */
    public function render(): string
    {
        if (modules()->disabled('seo')) {
            return $this->renderMinimal();
        }

        $lines = [];
        $title = e($this->resolvedTitle());
        $description = e($this->resolvedDescription());
        $canonical = e($this->resolvedCanonical());
        $image = $this->resolvedImage();

        $lines[] = "<title>{$title}</title>";
        $lines[] = '<meta name="description" content="'.$description.'">';

        if ($keywords = $this->keywords ?: setting('meta_keywords')) {
            $lines[] = '<meta name="keywords" content="'.e($keywords).'">';
        }

        $lines[] = '<link rel="canonical" href="'.$canonical.'">';

        if ($this->isNoindex()) {
            $lines[] = '<meta name="robots" content="noindex, nofollow">';
        } else {
            $lines[] = '<meta name="robots" content="index, follow, max-image-preview:large">';
        }

        // Open Graph
        $lines[] = '<meta property="og:type" content="'.($this->schemaType === 'Article' ? 'article' : 'website').'">';
        $lines[] = '<meta property="og:site_name" content="'.e(setting('site_name', config('app.name'))).'">';
        $lines[] = '<meta property="og:title" content="'.$title.'">';
        $lines[] = '<meta property="og:description" content="'.$description.'">';
        $lines[] = '<meta property="og:url" content="'.$canonical.'">';

        if ($image) {
            $lines[] = '<meta property="og:image" content="'.e($image).'">';
        }

        // Twitter / X
        $lines[] = '<meta name="twitter:card" content="'.($image ? 'summary_large_image' : 'summary').'">';
        $lines[] = '<meta name="twitter:title" content="'.$title.'">';
        $lines[] = '<meta name="twitter:description" content="'.$description.'">';

        if ($image) {
            $lines[] = '<meta name="twitter:image" content="'.e($image).'">';
        }

        if ($handle = setting('twitter_handle')) {
            $lines[] = '<meta name="twitter:site" content="@'.e(ltrim($handle, '@')).'">';
        }

        if ($verification = setting('google_site_verification')) {
            $lines[] = '<meta name="google-site-verification" content="'.e($verification).'">';
        }

        foreach ($this->schemaGraphs() as $graph) {
            $lines[] = '<script type="application/ld+json">'
                .json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                .'</script>';
        }

        return implode("\n    ", $lines);
    }

    /** Title and description only, for when the SEO module is switched off. */
    private function renderMinimal(): string
    {
        return '<title>'.e($this->resolvedTitle()).'</title>'
            ."\n    ".'<meta name="description" content="'.e($this->resolvedDescription()).'">';
    }

    /** @return array<int, array<string, mixed>> */
    public function schemaGraphs(): array
    {
        $graphs = [$this->siteSchema()];

        if ($pageSchema = $this->pageSchema()) {
            $graphs[] = $pageSchema;
        }

        return array_merge($graphs, $this->extraSchemas);
    }

    /**
     * The site-level entity, whose type the owner chooses in SEO settings
     * (Organization, LocalBusiness, Person, Store...).
     */
    private function siteSchema(): array
    {
        $type = (string) setting('schema_type', 'Organization');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => setting('schema_org_name') ?: setting('site_name', config('app.name')),
            'url' => url('/'),
        ];

        // An explicit schema logo wins; otherwise this is the site logo, which
        // falls back to the one the CMS ships with. Search engines treat a
        // missing logo as a missing organisation mark, so there is always one.
        if ($logo = setting('schema_org_logo')) {
            $schema['logo'] = str_starts_with($logo, 'http')
                ? $logo
                : Storage::disk(config('cms.media.disk'))->url($logo);
        } elseif ($logo = site_logo_url()) {
            $schema['logo'] = $logo;
        }

        if ($description = setting('meta_description') ?: setting('site_tagline')) {
            $schema['description'] = $description;
        }

        $socials = array_values(array_filter([
            setting('social_facebook'), setting('social_instagram'), setting('social_twitter'),
            setting('social_linkedin'), setting('social_youtube'),
        ]));

        if ($socials !== []) {
            $schema['sameAs'] = $socials;
        }

        // Contact details only make sense on the business-shaped types.
        if (in_array($type, ['Organization', 'LocalBusiness', 'Store', 'OnlineStore'], true)) {
            if ($phone = setting('site_phone')) {
                $schema['telephone'] = $phone;
            }
            if ($email = setting('site_email')) {
                $schema['email'] = $email;
            }
            if ($address = setting('site_address')) {
                $schema['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address];
            }
        }

        return $schema;
    }

    /** The per-page entity, when the current page declared one. */
    private function pageSchema(): ?array
    {
        if (blank($this->schemaType)) {
            return null;
        }

        $schema = array_merge([
            '@context' => 'https://schema.org',
            '@type' => $this->schemaType,
            'name' => $this->title ?: $this->resolvedTitle(),
            'description' => $this->resolvedDescription(),
            'url' => $this->resolvedCanonical(),
        ], $this->schemaData);

        if ($image = $this->resolvedImage()) {
            $schema['image'] ??= $image;
        }

        return $schema;
    }

    /** Convenience builder for a breadcrumb trail. */
    public function breadcrumbs(array $crumbs): static
    {
        if ($crumbs === []) {
            return $this;
        }

        $items = [];
        $position = 1;

        foreach ($crumbs as $name => $url) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url,
            ];
        }

        return $this->addSchema([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    /** Resets state between requests in long-running workers (Octane). */
    public function reset(): void
    {
        $this->title = $this->description = $this->keywords = null;
        $this->image = $this->canonical = $this->schemaType = null;
        $this->noindex = null;
        $this->schemaData = [];
        $this->extraSchemas = [];
    }
}
