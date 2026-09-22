<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Api\ApiManager;
use App\Cms\Api\TokenIssuer;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Mobile API screen: what the API exposes, which apps may call it, and
 * who is signed in through them.
 *
 * Three decisions live here, and they are separate on purpose:
 *
 *   Endpoints - which groups of endpoints answer at all.
 *   Apps      - the credentials without which nothing answers.
 *   Security  - signing, token lifetimes, rate limits, staff accounts.
 *
 * Issuing a credential is issuing a key to the storefront, so this whole
 * screen is admin-only - editors never see it.
 */
class ApiController extends Controller
{
    public function __construct(
        private ApiManager $api,
        private TokenIssuer $tokens,
    ) {}

    public function index(Request $request): View
    {
        // The first time an owner opens this screen after switching the
        // module on, the catalogue's own defaults are written, so the page
        // shows a working configuration rather than everything off.
        if (! $this->api->configured()) {
            $this->api->seedDefaults();
        }

        $clients = ApiClient::withCount([
            'tokens as active_tokens_count' => fn ($query) => $query->active(),
        ])->latest()->get();

        return view('admin.api.index', [
            'features' => $this->api->all(),
            'security' => $this->api->security(),
            'clients' => $clients,
            'baseUrl' => $this->api->baseUrl(),
            'platforms' => ApiClient::PLATFORMS,
            'signedIn' => ApiToken::with(['user', 'client'])
                ->active()
                ->latest('last_used_at')
                ->limit(20)
                ->get(),
            'activeSessions' => ApiToken::active()->count(),
        ]);
    }

    // Endpoints -------------------------------------------------------------

    public function updateFeatures(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in(array_keys($this->api->catalogue()))],
        ]);

        [$alsoOn, $alsoOff] = $this->api->saveFeatures($validated['features'] ?? []);

        $message = 'API endpoints saved.';

        // Dependencies are resolved for the owner rather than argued with, so
        // the screen says what it did on their behalf.
        if ($alsoOn !== []) {
            $message .= ' '.$this->names($alsoOn).' switched on as well, because the parts you chose need it.';
        }

        if ($alsoOff !== []) {
            $message .= ' '.$this->names($alsoOff).' could not stay on without what it depends on, so it was switched off.';
        }

        activity('api.features', $message, properties: ['features' => $this->api->enabledFeatures()]);

        return back()->with('status', $message);
    }

    // Security --------------------------------------------------------------

    public function updateSecurity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signature_window' => ['required', 'integer', 'min:60', 'max:900'],
            'token_days' => ['required', 'integer', 'min:1', 'max:365'],
            'refresh_days' => ['required', 'integer', 'min:1', 'max:730'],
            'rate_limit' => ['required', 'integer', 'min:1', 'max:6000'],
            'auth_rate_limit' => ['required', 'integer', 'min:1', 'max:600'],
        ]);

        $this->api->saveSecurity($validated + [
            'signature_required' => $request->boolean('signature_required'),
            'allow_staff' => $request->boolean('allow_staff'),
            'guest_cart' => $request->boolean('guest_cart'),
        ]);

        activity('api.security', 'Updated the API security options.');

        return back()->with('status', 'API security options saved.');
    }

    // Apps ------------------------------------------------------------------

    public function storeClient(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', 'string', Rule::in(array_keys(ApiClient::PLATFORMS))],
        ]);

        [$client, $secret] = ApiClient::issue(
            $validated['name'],
            $validated['platform'],
            $request->user()->id,
        );

        activity('api.client.created', "Registered the app [{$client->name}].", $client);

        // The secret is shown once and never again - it is not stored in a
        // form this screen could read back.
        return back()
            ->with('status', "{$client->name} is registered. Copy its secret now: this is the only time it is shown.")
            ->with('credentials', [
                'name' => $client->name,
                'client_id' => $client->client_id,
                'secret' => $secret,
            ]);
    }

    public function regenerateSecret(Request $request, ApiClient $client): RedirectResponse
    {
        $secret = $client->regenerateSecret();

        activity('api.client.regenerated', "Generated a new secret for [{$client->name}].", $client);

        return back()
            ->with('status', "New secret for {$client->name}. Every build still using the old one will stop working.")
            ->with('credentials', [
                'name' => $client->name,
                'client_id' => $client->client_id,
                'secret' => $secret,
            ]);
    }

    public function toggleClient(Request $request, ApiClient $client): RedirectResponse
    {
        $client->forceFill(['enabled' => ! $client->enabled])->save();

        $state = $client->enabled ? 'on' : 'off';

        activity('api.client.toggled', "Switched the app [{$client->name}] {$state}.", $client);

        return back()->with('status', "{$client->name} is now {$state}.");
    }

    public function destroyClient(Request $request, ApiClient $client): RedirectResponse
    {
        $name = $client->name;

        // Tokens issued through it go with it - the rows cascade - so every
        // device signed in through this app is signed out.
        $client->delete();

        activity('api.client.deleted', "Deleted the app [{$name}].");

        return back()->with('status', "{$name} has been removed, along with every session it issued.");
    }

    // Sessions --------------------------------------------------------------

    public function revokeToken(Request $request, ApiToken $token): RedirectResponse
    {
        $token->revoke();

        activity('api.token.revoked', 'Signed a device out of the API.', $token);

        return back()->with('status', 'That device has been signed out.');
    }

    public function revokeAllTokens(Request $request): RedirectResponse
    {
        $count = ApiToken::query()->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'refresh_hash' => null]);

        activity('api.tokens.revoked', "Signed every device out of the API ({$count}).");

        return back()->with('status', "Signed out {$count} device(s). Everybody will have to sign in again.");
    }

    private function names(array $keys): string
    {
        return collect($keys)->map(fn (string $key) => $this->api->name($key))->join(', ', ' and ');
    }
}
