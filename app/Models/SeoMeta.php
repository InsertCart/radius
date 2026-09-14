<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SEO overrides for routes with no content model behind them, keyed by a
 * stable route key such as 'home' or 'shop.index'.
 */
class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'route_key', 'meta_title', 'meta_description', 'meta_keywords',
        'og_image', 'canonical_url', 'schema_type', 'schema_data', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'schema_data' => 'array',
            'noindex' => 'boolean',
        ];
    }
}
