@extends('theme::account.layout')
@section('heading', 'Your addresses')

@php
    // A failed save reopens the form it came from, so nothing typed is lost.
    $reopen = (string) old('editing_id');
@endphp

@section('account')
    <p class="sf-help">Checkout fills itself in from your default address, so you only type it once.</p>

    @if ($addresses->isEmpty())
        <div class="sf-empty">No saved addresses yet. The one you use at checkout is kept here automatically.</div>
    @endif

    @foreach ($addresses as $address)
        <div class="sf-panel">
            <div class="sf-addr__head">
                <div>
                    <p class="sf-panel__title">
                        {{ $address->title() }}
                        @if ($address->is_default_billing || $address->is_default_shipping)
                            <span class="sf-pill">Default</span>
                        @endif
                    </p>
                    <address class="sf-addr__lines">{{ $address->singleLine() }}</address>
                </div>

                <div class="sf-addr__actions">
                    @unless ($address->is_default_billing && $address->is_default_shipping)
                        <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                            @csrf @method('PATCH')
                            <button class="sf-btn sf-btn--ghost sf-btn--sm">Make default</button>
                        </form>
                    @endunless

                    <form method="POST" action="{{ route('account.addresses.destroy', $address) }}"
                          onsubmit="return confirm('Remove this address?')">
                        @csrf @method('DELETE')
                        <button class="sf-btn sf-btn--ghost sf-btn--sm sf-btn--danger">Remove</button>
                    </form>
                </div>
            </div>

            <details class="sf-addr__edit" @if ($reopen === (string) $address->id) open @endif>
                <summary>Edit this address</summary>

                <form method="POST" action="{{ route('account.addresses.update', $address) }}" class="sf-mt">
                    @csrf @method('PATCH')
                    <input type="hidden" name="editing_id" value="{{ $address->id }}">
                    @include('theme::partials.address-fields', ['address' => $address, 'formKey' => $address->id, 'countries' => $countries])
                    <button class="sf-btn sf-btn--primary sf-mt">Save address</button>
                </form>
            </details>
        </div>
    @endforeach

    <div class="sf-panel">
        <details class="sf-addr__edit" @if ($reopen === 'new' || $addresses->isEmpty()) open @endif>
            <summary>Add an address</summary>

            <form method="POST" action="{{ route('account.addresses.store') }}" class="sf-mt">
                @csrf
                <input type="hidden" name="editing_id" value="new">
                @include('theme::partials.address-fields', ['address' => null, 'formKey' => 'new', 'countries' => $countries])
                <button class="sf-btn sf-btn--primary sf-mt">Save address</button>
            </form>
        </details>
    </div>
@endsection
