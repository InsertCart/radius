<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A named colour, font or spacing value offered throughout the editor.
 *
 * Tokens are what keep a site visually consistent: a colour picked from the
 * palette is stored as var(--cb-color-primary), so changing the token later
 * updates every element using it rather than leaving one-off hex codes behind.
 */
class DesignToken extends Model
{
    protected $fillable = ['group', 'key', 'label', 'value', 'sort_order'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('cms.builder.tokens'));
        static::deleted(fn () => Cache::forget('cms.builder.tokens'));
    }

    public function scopeColors(Builder $query): Builder
    {
        return $query->where('group', 'color');
    }

    public function scopeFonts(Builder $query): Builder
    {
        return $query->where('group', 'font');
    }

    /** The CSS custom property this token is referenced by. */
    public function variable(): string
    {
        return '--cb-'.$this->group.'-'.$this->key;
    }

    /** All tokens grouped for the editor's pickers. */
    public static function grouped(): array
    {
        return Cache::remember('cms.builder.tokens', 3600, function () {
            return static::orderBy('sort_order')->get()
                ->groupBy('group')
                ->map(fn ($tokens) => $tokens->map(fn (self $token) => [
                    'key' => $token->key,
                    'label' => $token->label,
                    'value' => $token->value,
                    'variable' => $token->variable(),
                ])->values()->all())
                ->all();
        });
    }

    /** The :root block that makes every token available as a CSS variable. */
    public static function rootCss(): string
    {
        $declarations = '';

        foreach (static::grouped() as $tokens) {
            foreach ($tokens as $token) {
                $value = str_replace(['{', '}', ';', '<', '>'], '', $token['value']);
                $declarations .= $token['variable'].':'.$value.';';
            }
        }

        return $declarations === '' ? '' : ':root{'.$declarations.'}';
    }
}
