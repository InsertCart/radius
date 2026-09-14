<?php

namespace App\Cms\Payments;

use App\Cms\Payments\Contracts\PaymentGatewayContract;
use App\Models\Order;
use App\Models\PaymentGateway as GatewayModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves payment drivers and decides which are offered at checkout.
 */
class PaymentManager
{
    /** @var array<string, PaymentGatewayContract> */
    private array $resolved = [];

    private ?Collection $models = null;

    /** Every gateway row, keyed by slug. */
    public function models(): Collection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        if (! $this->hasTable()) {
            return $this->models = collect();
        }

        return $this->models = GatewayModel::orderBy('sort_order')->get()->keyBy('slug');
    }

    public function model(string $slug): ?GatewayModel
    {
        return $this->models()->get($slug);
    }

    /**
     * Build the driver for a gateway.
     *
     * @throws \InvalidArgumentException when the slug is unknown or its driver
     *                                   class is missing.
     */
    public function driver(string $slug): PaymentGatewayContract
    {
        if (isset($this->resolved[$slug])) {
            return $this->resolved[$slug];
        }

        $model = $this->model($slug);

        if (! $model) {
            throw new \InvalidArgumentException("Unknown payment gateway [{$slug}].");
        }

        $class = config("payments.gateways.{$slug}.driver");

        if (! $class || ! class_exists($class)) {
            throw new \InvalidArgumentException("No driver class is registered for payment gateway [{$slug}].");
        }

        return $this->resolved[$slug] = new $class($model);
    }

    /**
     * Gateways a customer may actually pick: switched on, fully configured,
     * and able to handle the shop's currency.
     *
     * @return Collection<string, GatewayModel>
     */
    public function availableFor(?Order $order = null): Collection
    {
        if (modules()->disabled('payments')) {
            return collect();
        }

        $currency = $order?->currency ?? setting('shop_currency', 'USD');

        return $this->models()
            ->filter(fn (GatewayModel $model) => $model->is_enabled)
            ->filter(fn (GatewayModel $model) => $model->supportsCurrency($currency))
            ->filter(function (GatewayModel $model) {
                try {
                    return $this->driver($model->slug)->isConfigured();
                } catch (\Throwable $e) {
                    return false;
                }
            });
    }

    public function isAvailable(string $slug, ?Order $order = null): bool
    {
        return $this->availableFor($order)->has($slug);
    }

    /** Everything declared in config, whether installed or not - for the admin UI. */
    public function catalogue(): array
    {
        $catalogue = [];

        foreach (config('payments.gateways', []) as $slug => $definition) {
            $model = $this->model($slug);

            $catalogue[$slug] = [
                'slug' => $slug,
                'name' => $definition['name'] ?? ucfirst($slug),
                'flow' => $definition['flow'] ?? 'redirect',
                'currencies' => $definition['currencies'] ?? ['*'],
                'fields' => $definition['fields'] ?? [],
                'supports_refund' => $definition['supports_refund'] ?? false,
                'supports_webhook' => $definition['supports_webhook'] ?? false,
                'model' => $model,
                'enabled' => (bool) $model?->is_enabled,
                'configured' => $model ? $this->safelyConfigured($slug) : false,
                'mode' => $model?->mode ?? 'test',
            ];
        }

        return $catalogue;
    }

    private function safelyConfigured(string $slug): bool
    {
        try {
            return $this->driver($slug)->isConfigured();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The webhook URL a site owner pastes into the provider's dashboard. */
    public function webhookUrl(string $slug): string
    {
        return route('checkout.webhook', ['gateway' => $slug]);
    }

    /**
     * Creates a row for every gateway declared in config. Run by the installer
     * and by `php artisan cms:sync`.
     */
    public function sync(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        $created = 0;
        $sort = 0;

        foreach (config('payments.gateways', []) as $slug => $definition) {
            $model = GatewayModel::firstOrNew(['slug' => $slug]);

            if (! $model->exists) {
                $model->is_enabled = false;   // nothing is live until configured
                $model->mode = 'test';
                $created++;
            }

            $model->fill([
                'name' => $definition['name'] ?? ucfirst($slug),
                'sort_order' => $sort++,
            ])->save();
        }

        $this->flush();

        return $created;
    }

    public function flush(): void
    {
        $this->models = null;
        $this->resolved = [];
    }

    private function hasTable(): bool
    {
        try {
            return Schema::hasTable('payment_gateways');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
