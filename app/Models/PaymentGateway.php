<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Per-gateway configuration. Credentials are stored as a single encrypted
 * JSON blob rather than plain columns, so adding a field to config/payments.php
 * never needs a migration.
 */
class PaymentGateway extends Model
{
    protected $fillable = ['slug', 'name', 'is_enabled', 'mode', 'credentials', 'instructions', 'sort_order'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->orderBy('sort_order');
    }

    /** The definition for this gateway from config/payments.php. */
    public function definition(): array
    {
        return config("payments.gateways.{$this->slug}", []);
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    public function flow(): string
    {
        return $this->definition()['flow'] ?? 'redirect';
    }

    // Credentials ---------------------------------------------------------

    public function credentials(): array
    {
        if (blank($this->credentials)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->credentials), true) ?: [];
        } catch (\Throwable $e) {
            // Typically means APP_KEY changed after the credentials were saved.
            Log::warning("Could not decrypt credentials for gateway [{$this->slug}]. Re-enter them in the admin panel.");

            return [];
        }
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials(), $key, $default);
    }

    public function setCredentials(array $values): void
    {
        $this->credentials = Crypt::encryptString(json_encode($values));
    }

    /**
     * Merges submitted values over the stored ones, ignoring blank secrets so
     * that leaving a masked password field untouched does not wipe it.
     */
    public function mergeCredentials(array $submitted): void
    {
        $existing = $this->credentials();

        foreach ($submitted as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $existing[$key] = $value;
        }

        $this->setCredentials($existing);
    }

    /** True when every non-optional field for this gateway has a value. */
    public function isConfigured(): bool
    {
        $fields = $this->definition()['fields'] ?? [];
        $credentials = $this->credentials();

        foreach ($fields as $key => $field) {
            if (($field['optional'] ?? false) === true) {
                continue;
            }
            if (blank($credentials[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function supportsCurrency(string $currency): bool
    {
        $allowed = $this->definition()['currencies'] ?? ['*'];

        return in_array('*', $allowed, true) || in_array(strtoupper($currency), $allowed, true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
