<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Payments\PaymentManager;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configure the payment providers.
 *
 * Credentials are write-only from this screen: stored values are never sent
 * back to the browser, and a blank field means "keep what is already saved".
 */
class PaymentGatewayController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    public function index(): View
    {
        // Pick up any gateway added to config since the last visit.
        $this->payments->sync();

        return view('admin.payments.index', [
            'gateways' => $this->payments->catalogue(),
            'currency' => setting('shop_currency', 'USD'),
        ]);
    }

    public function edit(PaymentGateway $gateway): View
    {
        $definition = $gateway->definition();

        abort_if(blank($definition), 404);

        return view('admin.payments.edit', [
            'gateway' => $gateway,
            'definition' => $definition,
            'fields' => $definition['fields'] ?? [],
            // Only which credentials are filled, never their values.
            'filled' => collect($definition['fields'] ?? [])
                ->mapWithKeys(fn ($field, $key) => [$key => filled($gateway->credential($key))])
                ->all(),
            'webhookUrl' => ($definition['supports_webhook'] ?? false)
                ? $this->payments->webhookUrl($gateway->slug)
                : null,
            'returnUrl' => route('checkout.return', ['gateway' => $gateway->slug, 'order' => 'ORDER_NUMBER']),
        ]);
    }

    public function update(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $definition = $gateway->definition();
        $fields = $definition['fields'] ?? [];

        $rules = [
            'mode' => ['required', 'in:test,live'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        foreach ($fields as $key => $field) {
            $rules['credentials.'.$key] = ($field['type'] ?? 'text') === 'textarea'
                ? ['nullable', 'string', 'max:2000']
                : ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules);

        $gateway->mergeCredentials($validated['credentials'] ?? []);
        $gateway->mode = $validated['mode'];
        $gateway->sort_order = $validated['sort_order'] ?? $gateway->sort_order;
        $gateway->is_enabled = $request->boolean('is_enabled');
        $gateway->save();

        $this->payments->flush();

        // Refuse to go live on incomplete credentials rather than letting a
        // customer hit a broken checkout.
        if ($gateway->is_enabled && ! $this->payments->driver($gateway->slug)->isConfigured()) {
            $gateway->update(['is_enabled' => false]);
            $this->payments->flush();

            return back()->with('error', "{$gateway->name} still has empty credentials, so it has been left switched off.");
        }

        activity('payment_gateway.updated', "Updated the {$gateway->name} payment settings.", $gateway, [
            'mode' => $gateway->mode,
            'enabled' => $gateway->is_enabled,
        ]);

        return back()->with('status', "{$gateway->name} settings saved.");
    }

    public function toggle(PaymentGateway $gateway): RedirectResponse
    {
        if (! $gateway->is_enabled && ! $this->payments->driver($gateway->slug)->isConfigured()) {
            return back()->with('error', "Add {$gateway->name}'s credentials before switching it on.");
        }

        $gateway->update(['is_enabled' => ! $gateway->is_enabled]);
        $this->payments->flush();

        activity('payment_gateway.toggled', "{$gateway->name} is now ".($gateway->is_enabled ? 'on' : 'off').'.', $gateway);

        return back()->with('status', "{$gateway->name} is now ".($gateway->is_enabled ? 'accepting payments.' : 'switched off.'));
    }
}
