@extends('admin.layout')
@section('title', $gateway->name)
@section('subtitle', 'Payment gateway configuration')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.payments.update', $gateway) }}" class="lg:col-span-2">
            @csrf @method('PUT')

            <x-admin.card title="Credentials"
                          description="Stored encrypted. Leave a field blank to keep the value already saved.">
                <div class="space-y-5">
                    @foreach ($fields as $key => $field)
                        @if (($field['type'] ?? 'text') === 'textarea')
                            <x-form.field :label="$field['label']" :name="'credentials.'.$key" :help="$field['help'] ?? null">
                                <textarea name="credentials[{{ $key }}]" rows="4"
                                          class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          placeholder="{{ $filled[$key] ? 'Saved — type to replace' : '' }}">{{ old('credentials.'.$key, $filled[$key] ? $gateway->credential($key) : '') }}</textarea>
                            </x-form.field>
                        @else
                            <x-form.field :label="$field['label']" :name="'credentials.'.$key"
                                          :help="$field['help'] ?? (($field['type'] ?? '') === 'secret' ? 'Encrypted at rest and never shown again.' : null)">
                                <input type="{{ ($field['type'] ?? 'text') === 'secret' ? 'password' : 'text' }}"
                                       name="credentials[{{ $key }}]"
                                       autocomplete="new-password"
                                       value="{{ ($field['type'] ?? 'text') === 'secret' ? '' : old('credentials.'.$key, $gateway->credential($key)) }}"
                                       placeholder="{{ $filled[$key] ? '••••••••  (saved)' : 'Not set' }}"
                                       class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </x-form.field>
                        @endif
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="Behaviour" class="mt-6">
                <div class="space-y-5">
                    <x-form.field label="Mode" name="mode" help="Keep this on Test until you have made a successful test payment.">
                        <x-form.select name="mode" :value="$gateway->mode" :options="['test' => 'Test / sandbox', 'live' => 'Live']" />
                    </x-form.field>

                    <x-form.field label="Display order" name="sort_order">
                        <x-form.input name="sort_order" type="number" :value="$gateway->sort_order" />
                    </x-form.field>

                    <x-form.toggle name="is_enabled" label="Offer this gateway at checkout" :checked="$gateway->is_enabled"
                                   help="It will not switch on until every credential above is filled in." />
                </div>

                <div class="mt-6 flex gap-2 border-t border-slate-100 pt-5">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                        Save settings
                    </button>
                    <a href="{{ route('admin.payments.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">Back</a>
                </div>
            </x-admin.card>
        </form>

        <div class="space-y-6">
            @if ($webhookUrl)
                <x-admin.card title="Webhook URL" description="Paste this into the provider's dashboard.">
                    <code class="block break-all rounded-lg bg-slate-900 p-3 text-[11px] text-slate-100">{{ $webhookUrl }}</code>
                    <p class="mt-3 text-xs text-slate-500">
                        Webhooks confirm payments even when the customer closes the tab before
                        returning. The signature is verified on every call, so an unsigned request
                        is rejected.
                    </p>
                </x-admin.card>
            @endif

            <x-admin.card title="Supported currencies">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($definition['currencies'] ?? [] as $currency)
                        <x-admin.badge :color="$currency === setting('shop_currency') ? 'green' : 'gray'">
                            {{ $currency === '*' ? 'Any' : $currency }}
                        </x-admin.badge>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Your shop currency is {{ setting('shop_currency', 'USD') }}. Gateways that do not
                    support it are hidden at checkout.
                </p>
            </x-admin.card>
        </div>
    </div>
@endsection
