@extends('admin.layout')
@section('title', 'Mobile API')
@section('subtitle', 'A JSON API for your app. Nothing answers it without a registered app key, and only the parts you switch on answer at all.')

@section('content')
    @php
        $credentials = session('credentials');
        $enabledCount = collect($features)->where('enabled', true)->count();
    @endphp

    {{-- Shown once, immediately after a secret is generated. There is no
         second chance: the stored copy is encrypted for the server's use and
         is never rendered. --}}
    @if ($credentials)
        <div class="mb-6 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-5">
            <h2 class="text-sm font-semibold text-emerald-900">Credentials for {{ $credentials['name'] }}</h2>
            <p class="mt-1 text-xs text-emerald-800">
                Copy the secret into your app now and keep it out of version control. It is not shown again,
                and it cannot be recovered — only replaced.
            </p>

            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-emerald-900">App key <span class="font-normal">(X-Api-Key)</span></dt>
                    <dd><input type="text" readonly onclick="this.select()" value="{{ $credentials['client_id'] }}"
                               class="mt-1 w-full rounded-lg border border-emerald-300 bg-white px-3 py-2 font-mono text-xs"></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-emerald-900">App secret <span class="font-normal">(X-Api-Secret)</span></dt>
                    <dd><input type="text" readonly onclick="this.select()" value="{{ $credentials['secret'] }}"
                               class="mt-1 w-full rounded-lg border border-emerald-300 bg-white px-3 py-2 font-mono text-xs"></dd>
                </div>
            </dl>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- Endpoints ------------------------------------------------ --}}
            <form method="POST" action="{{ route('admin.api.features') }}">
                @csrf
                @method('PUT')

                <x-admin.card title="Endpoints"
                              description="What your app is allowed to ask for. Everything else answers 404, whoever is calling."
                              bodyClass="divide-y divide-slate-100">
                    @foreach ($features as $key => $feature)
                        <div class="px-5 py-4 first:pt-1" x-data="{ open: false }">
                            <div class="flex items-start justify-between gap-4">
                                <label class="flex min-w-0 cursor-pointer items-start gap-3">
                                    <input type="checkbox"
                                           name="features[]"
                                           value="{{ $key }}"
                                           @checked($feature['enabled'])
                                           @disabled(! $feature['available'])
                                           class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40">
                                    <span class="min-w-0">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-semibold text-slate-900">{{ $feature['name'] }}</span>

                                            @if (! $feature['available'])
                                                <x-admin.badge color="gray">Needs the {{ $feature['module_name'] }} module</x-admin.badge>
                                            @elseif ($feature['enabled'])
                                                <x-admin.badge color="green">On</x-admin.badge>
                                            @endif

                                            @if (($feature['auth'] ?? 'none') === 'required')
                                                <x-admin.badge color="indigo">Sign-in required</x-admin.badge>
                                            @endif
                                        </span>

                                        <span class="mt-1 block text-xs text-slate-500">{{ $feature['description'] ?? '' }}</span>

                                        @if ($feature['requires'])
                                            <span class="mt-1 block text-xs text-slate-400">
                                                Needs: {{ collect($feature['requires'])->map(fn ($r) => api()->name($r))->implode(', ') }}
                                            </span>
                                        @endif

                                        @if ($feature['enables'])
                                            <span class="mt-1 block text-xs text-slate-400">
                                                Switches on with it: {{ collect($feature['enables'])->map(fn ($r) => api()->name($r))->implode(', ') }}
                                            </span>
                                        @endif
                                    </span>
                                </label>

                                @if ($feature['endpoints'])
                                    <button type="button" @click="open = ! open"
                                            class="shrink-0 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                        <span x-text="open ? 'Hide' : '{{ count($feature['endpoints']) }} endpoints'"></span>
                                    </button>
                                @endif
                            </div>

                            @if ($feature['endpoints'])
                                <dl x-show="open" x-cloak class="mt-3 space-y-1 rounded-xl bg-slate-50 p-3">
                                    @foreach ($feature['endpoints'] as $endpoint => $what)
                                        <div class="flex flex-wrap gap-x-3 text-xs">
                                            <dt class="font-mono text-slate-700">{{ $endpoint }}</dt>
                                            <dd class="text-slate-500">{{ $what }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-4">
                        <p class="text-xs text-slate-500">
                            None of this reaches the admin panel. There is no endpoint here that publishes content,
                            changes a setting or reads another customer's data.
                        </p>
                        <button type="submit"
                                class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Save endpoints
                        </button>
                    </div>
                </x-admin.card>
            </form>

            {{-- Apps ----------------------------------------------------- --}}
            <x-admin.card title="Apps"
                          description="Every caller needs one of these. Without a key and secret the API answers 401, whatever the address."
                          bodyClass="divide-y divide-slate-100">
                @forelse ($clients as $client)
                    <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4 first:pt-1">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-slate-900">{{ $client->name }}</span>
                                <x-admin.badge :color="$client->enabled ? 'green' : 'gray'">
                                    {{ $client->enabled ? 'Active' : 'Switched off' }}
                                </x-admin.badge>
                                <x-admin.badge color="blue">{{ $client->platformName() }}</x-admin.badge>
                            </div>

                            <p class="mt-1 font-mono text-xs text-slate-500">{{ $client->client_id }}</p>

                            <p class="mt-1 text-xs text-slate-400">
                                Secret ends &hellip;{{ $client->secret_hint }}
                                &middot; {{ $client->active_tokens_count }} signed-in device(s)
                                &middot; {{ $client->last_used_at ? 'last used '.$client->last_used_at->diffForHumans() : 'never used' }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.api.apps.secret', $client) }}"
                                  onsubmit="return confirm('Generate a new secret? Every build still using the old one stops working immediately.')">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                    New secret
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.api.apps.toggle', $client) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                    {{ $client->enabled ? 'Switch off' : 'Switch on' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.api.apps.destroy', $client) }}"
                                  onsubmit="return confirm('Delete this app? Every device signed in through it is signed out.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-6 text-center text-sm text-slate-500">
                        No apps registered yet, so nothing can call the API.
                    </div>
                @endforelse

                <form method="POST" action="{{ route('admin.api.apps.store') }}" class="flex flex-wrap items-end gap-3 px-5 pb-1 pt-4">
                    @csrf
                    <div class="min-w-48 flex-1">
                        <x-form.field label="App name" name="name">
                            <x-form.input name="name" required maxlength="120" placeholder="Our iOS & Android app" />
                        </x-form.field>
                    </div>
                    <div>
                        <x-form.field label="Platform" name="platform">
                            <x-form.select name="platform" :options="$platforms" :value="old('platform')" />
                        </x-form.field>
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Register app
                    </button>
                </form>
            </x-admin.card>

            {{-- Signed-in devices ---------------------------------------- --}}
            <x-admin.card title="Signed-in devices"
                          description="Customers currently signed in through an app. Revoking one makes its token useless immediately."
                          bodyClass="divide-y divide-slate-100">
                <x-slot:actions>
                    @if ($activeSessions > 0)
                        <form method="POST" action="{{ route('admin.api.sessions.revoke-all') }}"
                              onsubmit="return confirm('Sign every device out? Everybody will have to sign in again.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                                Sign out all {{ $activeSessions }}
                            </button>
                        </form>
                    @endif
                </x-slot:actions>

                @forelse ($signedIn as $token)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 first:pt-1">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-slate-900">
                                {{ $token->user?->name ?? 'Deleted account' }}
                                <span class="text-slate-400">&middot; {{ $token->client?->name ?? 'Deleted app' }}</span>
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $token->device_name ?: 'Unnamed device' }}
                                &middot; {{ $token->last_used_at ? 'active '.$token->last_used_at->diffForHumans() : 'never used' }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('admin.api.sessions.revoke', $token) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                Sign out
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-5 py-6 text-center text-sm text-slate-500">Nobody is signed in through an app.</div>
                @endforelse
            </x-admin.card>
        </div>

        {{-- Right column ------------------------------------------------- --}}
        <div class="space-y-6">

            <x-admin.card title="Where it answers">
                <p class="text-xs text-slate-500">Point your app at this address.</p>
                <input type="text" readonly onclick="this.select()" value="{{ $baseUrl }}"
                       class="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs">

                <dl class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <div class="flex justify-between"><dt>Endpoint groups on</dt><dd>{{ $enabledCount }} of {{ count($features) }}</dd></div>
                    <div class="flex justify-between"><dt>Registered apps</dt><dd>{{ $clients->count() }}</dd></div>
                    <div class="flex justify-between"><dt>Signed-in devices</dt><dd>{{ $activeSessions }}</dd></div>
                </dl>

                <div class="mt-4 rounded-xl bg-slate-900 p-3">
                    <p class="text-[11px] font-medium text-slate-400">Every request carries</p>
<pre class="mt-1 overflow-x-auto text-[11px] leading-relaxed text-slate-200">X-Api-Key: &lt;app key&gt;
X-Api-Secret: &lt;app secret&gt;
Authorization: Bearer &lt;token&gt;</pre>
                    <p class="mt-2 text-[11px] text-slate-400">
                        The bearer token comes from <span class="font-mono">POST /auth/login</span> and is only
                        needed for endpoints about a particular customer.
                    </p>
                </div>
            </x-admin.card>

            {{-- Security ------------------------------------------------- --}}
            <form method="POST" action="{{ route('admin.api.security') }}">
                @csrf
                @method('PUT')

                <x-admin.card title="Security">
                    <div class="space-y-5">
                        <x-form.toggle name="signature_required"
                                       label="Require signed requests"
                                       :checked="$security['signature_required']"
                                       help="Each request is signed with the app secret instead of carrying it. The secret never travels, and a captured request stops working within the window below." />

                        <x-form.field label="Signing window (seconds)" name="signature_window"
                                      help="How far a device's clock may drift, and how long a signed request stays valid.">
                            <x-form.input type="number" name="signature_window" min="60" max="900"
                                          :value="$security['signature_window']" />
                        </x-form.field>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.field label="Sign-in lasts (days)" name="token_days">
                                <x-form.input type="number" name="token_days" min="1" max="365"
                                              :value="$security['token_days']" />
                            </x-form.field>
                            <x-form.field label="Refresh lasts (days)" name="refresh_days">
                                <x-form.input type="number" name="refresh_days" min="1" max="730"
                                              :value="$security['refresh_days']" />
                            </x-form.field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.field label="Requests per minute" name="rate_limit">
                                <x-form.input type="number" name="rate_limit" min="1" max="6000"
                                              :value="$security['rate_limit']" />
                            </x-form.field>
                            <x-form.field label="Sign-ins per minute" name="auth_rate_limit">
                                <x-form.input type="number" name="auth_rate_limit" min="1" max="600"
                                              :value="$security['auth_rate_limit']" />
                            </x-form.field>
                        </div>

                        <x-form.toggle name="guest_cart"
                                       label="Let signed-out visitors keep a cart"
                                       :checked="$security['guest_cart']"
                                       help="Off means the app must ask for an account before the first item goes in the basket." />

                        <x-form.toggle name="allow_staff"
                                       label="Allow staff accounts to sign in"
                                       :checked="$security['allow_staff']"
                                       help="Off, and recommended off. The API serves the storefront: it has no admin endpoints, so a staff password buys nothing here but risk." />
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4 text-right">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Save security
                        </button>
                    </div>
                </x-admin.card>
            </form>
        </div>
    </div>
@endsection
