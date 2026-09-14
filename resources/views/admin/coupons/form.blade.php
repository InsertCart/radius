@extends('admin.layout')
@section('title', $coupon->exists ? 'Edit coupon' : 'New coupon')

@section('content')
    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
          x-data="{ type: @js(old('type', $coupon->type ?? 'percent')) }" class="mx-auto max-w-2xl">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif

        <x-admin.card>
            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Code" name="code" required help="Customers type this at checkout.">
                        <x-form.input name="code" :value="$coupon->code" required autofocus class="block w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm uppercase" />
                    </x-form.field>

                    <x-form.field label="Type" name="type" required>
                        <x-form.select name="type" :value="$coupon->type" x-model="type" :options="[
                            'percent' => 'Percentage off',
                            'fixed' => 'Fixed amount off',
                            'free_shipping' => 'Free shipping',
                        ]" />
                    </x-form.field>
                </div>

                <x-form.field label="Description" name="description">
                    <x-form.input name="description" :value="$coupon->description" />
                </x-form.field>

                <div x-show="type !== 'free_shipping'" x-cloak class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Value" name="value" required>
                        <x-form.input name="value" type="number" step="0.01" min="0" required
                                      :value="$coupon->exists ? from_minor_units($coupon->value) : ''" />
                    </x-form.field>

                    <div x-show="type === 'percent'" x-cloak>
                        <x-form.field label="Maximum discount" name="max_discount" help="Caps a percentage coupon.">
                            <x-form.input name="max_discount" type="number" step="0.01" min="0"
                                          :value="$coupon->max_discount ? from_minor_units($coupon->max_discount) : ''" />
                        </x-form.field>
                    </div>
                </div>

                <x-form.field label="Minimum order total" name="min_order_total">
                    <x-form.input name="min_order_total" type="number" step="0.01" min="0"
                                  :value="$coupon->exists ? from_minor_units($coupon->min_order_total) : '0'" />
                </x-form.field>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Total uses allowed" name="usage_limit" help="Leave blank for unlimited.">
                        <x-form.input name="usage_limit" type="number" min="1" :value="$coupon->usage_limit" />
                    </x-form.field>

                    <x-form.field label="Uses per customer" name="usage_limit_per_user">
                        <x-form.input name="usage_limit_per_user" type="number" min="1" :value="$coupon->usage_limit_per_user" />
                    </x-form.field>

                    <x-form.field label="Starts" name="starts_at">
                        <x-form.input name="starts_at" type="datetime-local" :value="$coupon->starts_at?->format('Y-m-d\TH:i')" />
                    </x-form.field>

                    <x-form.field label="Expires" name="expires_at">
                        <x-form.input name="expires_at" type="datetime-local" :value="$coupon->expires_at?->format('Y-m-d\TH:i')" />
                    </x-form.field>
                </div>

                <x-form.toggle name="is_active" label="Active" :checked="(bool) $coupon->is_active" />
            </div>

            <div class="mt-6 flex gap-2 border-t border-slate-100 pt-5">
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ $coupon->exists ? 'Save changes' : 'Create coupon' }}
                </button>
                <a href="{{ route('admin.coupons.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">Cancel</a>
            </div>
        </x-admin.card>
    </form>
@endsection
