<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * One configured media storage provider.
 *
 * A row exists for every provider in config/cdn.php, so switching from S3 to
 * Spaces and back does not throw the first set of credentials away. At most
 * one row is enabled at a time; enabling one switches the others off.
 *
 * Credentials are a single encrypted JSON blob rather than columns, exactly
 * as payment gateway keys are, so adding a field to the provider catalogue
 * never needs a migration.
 */
class CdnConnection extends Model
{
    protected $fillable = ['provider', 'is_enabled', 'keep_local', 'path_prefix', 'credentials'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'keep_local' => 'boolean',
        ];
    }

    /** The definition for this provider from config/cdn.php. */
    public function definition(): array
    {
        return config("cdn.providers.{$this->provider}", []);
    }

    public function name(): string
    {
        return $this->definition()['name'] ?? ucfirst($this->provider);
    }

    /** 'proxy', 's3' or 'ftp' - see config/cdn.php. */
    public function kind(): string
    {
        return $this->definition()['kind'] ?? 'proxy';
    }

    /** Whether files are actually moved off this server by this provider. */
    public function offloads(): bool
    {
        return $this->kind() !== 'proxy';
    }

    public function getRouteKeyName(): string
    {
        return 'provider';
    }

    // Credentials ---------------------------------------------------------

    public function credentials(): array
    {
        // Read straight out of the attribute bag. A row created with only a
        // provider slug has no 'credentials' key yet, and $this->credentials
        // would then look for a relationship of that name and find this
        // method instead.
        $stored = $this->attributes['credentials'] ?? null;

        if (blank($stored)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($stored), true) ?: [];
        } catch (\Throwable $e) {
            // Typically means APP_KEY changed after the credentials were saved.
            Log::warning("Could not decrypt credentials for the [{$this->provider}] storage connection. Re-enter them in the admin panel.");

            return [];
        }
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->credentials(), $key);

        return $value === null || $value === '' ? $default : $value;
    }

    public function setCredentials(array $values): void
    {
        $this->credentials = Crypt::encryptString(json_encode($values));
    }

    /**
     * Merges submitted values over the stored ones, ignoring blanks so that
     * leaving a masked password field untouched does not wipe it.
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

    /** True when every non-optional field for this provider has a value. */
    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        foreach ($this->definition()['fields'] ?? [] as $key => $field) {
            if (($field['optional'] ?? false) === true) {
                continue;
            }
            if (blank($credentials[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Which required fields are still empty, for the admin screen. */
    public function missingFields(): array
    {
        $credentials = $this->credentials();
        $missing = [];

        foreach ($this->definition()['fields'] ?? [] as $key => $field) {
            if (($field['optional'] ?? false) !== true && blank($credentials[$key] ?? null)) {
                $missing[] = $field['label'] ?? $key;
            }
        }

        return $missing;
    }

    // Addressing ----------------------------------------------------------

    /** The prefix every remote key sits under. Empty unless one was set. */
    public function prefix(): string
    {
        return trim((string) $this->path_prefix, '/');
    }

    /**
     * The address visitors fetch files from: the custom domain or CDN the
     * owner entered, falling back to the provider's own public address.
     */
    public function deliveryUrl(): ?string
    {
        if ($url = $this->credential('delivery_url')) {
            return rtrim((string) $url, '/');
        }

        $template = $this->definition()['public_url'] ?? null;

        if (! $template) {
            // 'Other S3-compatible': the endpoint they typed is all we have.
            return ($endpoint = $this->credential('endpoint'))
                ? rtrim((string) $endpoint, '/').'/'.$this->credential('bucket')
                : null;
        }

        return rtrim($this->fillTemplate($template), '/');
    }

    /** The API address uploads are sent to. Not used by 'proxy' providers. */
    public function endpoint(): ?string
    {
        if ($endpoint = $this->credential('endpoint')) {
            return rtrim((string) $endpoint, '/');
        }

        $template = $this->definition()['endpoint'] ?? null;

        return $template ? rtrim($this->fillTemplate($template), '/') : null;
    }

    public function region(): string
    {
        return (string) ($this->definition()['region'] ?? $this->credential('region', 'us-east-1'));
    }

    /** Whether the bucket belongs in the path rather than the hostname. */
    public function usesPathStyle(): bool
    {
        $declared = $this->definition()['path_style'] ?? null;

        return $declared ?? ($this->credential('path_style', 'yes') === 'yes');
    }

    private function fillTemplate(string $template): string
    {
        return str_replace(
            ['{region}', '{bucket}', '{account_id}'],
            [$this->region(), (string) $this->credential('bucket'), (string) $this->credential('account_id')],
            $template
        );
    }
}
