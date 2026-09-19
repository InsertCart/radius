@extends('theme::account.layout')
@section('heading', 'Your addresses')

@php
    // A failed save reopens the form it came from, so nothing typed is lost.
    $reopen = (string) old('editing_id');
@endphp

@section('account')
    <div class="space-y-6">
        <p class="text-sm text-slate-500">
            Checkout fills itself in from your default address, so you only type it once.
        </p>

        @if ($addresses->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                No saved addresses yet. The one you use at checkout is kept here automatically.
            </p>
        @endif

        @foreach ($addresses as $address)
            <div class="rounded-2xl border border-slate-200 p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ $address->title() }}
                            @if ($address->is_default_billing || $address->is_default_shipping)
                                <span class="ml-1 rounded-full bg-slate-900 px-2 py-0.5 text-xs font-medium text-white">Default</span>
                            @endif
                        </p>
                        <address class="mt-1 text-sm not-italic leading-relaxed text-slate-600">{{ $address->singleLine() }}</address>
                    </div>

                    <div class="flex items-center gap-2 text-sm">
                        @unless ($address->is_default_billing && $address->is_default_shipping)
                            <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                                @csrf @method('PATCH')
                                <button class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Make default</button>
                            </form>
                        @endunless

                        <form method="POST" action="{{ route('account.addresses.destroy', $address) }}"
                              onsubmit="return confirm('Remove this address?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg px-3 py-1.5 text-rose-600 hover:bg-rose-50">Remove</button>
                        </form>
                    </div>
                </div>

                <details class="mt-4 border-t border-slate-100 pt-4" @if ($reopen === (string) $address->id) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-slate-900">Edit this address</summary>

                    <form method="POST" action="{{ route('account.addresses.update', $address) }}" class="mt-5">
                        @csrf @method('PATCH')
                        <input type="hidden" name="editing_id" value="{{ $address->id }}">
                        @include('theme::partials.address-fields', ['address' => $address, 'formKey' => $address->id, 'countries' => $countries])
                        <button class="mt-5 rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">Save address</button>
                    </form>
                </details>
            </div>
        @endforeach

        <details class="rounded-2xl border border-slate-200 p-6" @if ($reopen === 'new' || $addresses->isEmpty()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">Add an address</summary>

            <form method="POST" action="{{ route('account.addresses.store') }}" class="mt-5">
                @csrf
                <input type="hidden" name="editing_id" value="new">
                @include('theme::partials.address-fields', ['address' => null, 'formKey' => 'new', 'countries' => $countries])
                <button class="mt-5 rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">Save address</button>
            </form>
        </details>
    </div>
@endsection
