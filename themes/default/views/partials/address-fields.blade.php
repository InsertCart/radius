{{-- The fields of one address book entry.
     $address  - the entry being edited, or null when adding a new one.
     $formKey  - identifies this form, so a failed save only repopulates the
                 form it came from rather than every one on the page. --}}
@php
    $mine = (string) old('editing_id') === (string) $formKey;

    $value = [
        'label' => $mine ? old('label', $address?->label) : $address?->label,
        'name' => $mine ? old('name', $address?->name) : $address?->name,
        'phone' => $mine ? old('phone', $address?->phone) : $address?->phone,
        'line1' => $mine ? old('line1', $address?->line1) : $address?->line1,
        'line2' => $mine ? old('line2', $address?->line2) : $address?->line2,
        'city' => $mine ? old('city', $address?->city) : $address?->city,
        'state' => $mine ? old('state', $address?->state) : $address?->state,
        'postcode' => $mine ? old('postcode', $address?->postcode) : $address?->postcode,
        'country' => (string) ($mine ? old('country', $address?->country) : $address?->country),
    ];
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="{{ $formKey }}-label" class="mb-1 block text-sm font-medium text-slate-700">Name this address (optional)</label>
        <input type="text" name="label" id="{{ $formKey }}-label" placeholder="Home, Office"
               value="{{ $value['label'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label for="{{ $formKey }}-name" class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
        <input type="text" name="name" id="{{ $formKey }}-name" required autocomplete="name"
               value="{{ $value['name'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label for="{{ $formKey }}-phone" class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
        <input type="text" name="phone" id="{{ $formKey }}-phone" autocomplete="tel"
               value="{{ $value['phone'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div class="sm:col-span-2">
        <label for="{{ $formKey }}-line1" class="mb-1 block text-sm font-medium text-slate-700">Address</label>
        <input type="text" name="line1" id="{{ $formKey }}-line1" required autocomplete="address-line1"
               value="{{ $value['line1'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div class="sm:col-span-2">
        <label for="{{ $formKey }}-line2" class="mb-1 block text-sm font-medium text-slate-700">Apartment, suite (optional)</label>
        <input type="text" name="line2" id="{{ $formKey }}-line2" autocomplete="address-line2"
               value="{{ $value['line2'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label for="{{ $formKey }}-city" class="mb-1 block text-sm font-medium text-slate-700">City</label>
        <input type="text" name="city" id="{{ $formKey }}-city" required autocomplete="address-level2"
               value="{{ $value['city'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label for="{{ $formKey }}-state" class="mb-1 block text-sm font-medium text-slate-700">State / region</label>
        <input type="text" name="state" id="{{ $formKey }}-state" autocomplete="address-level1"
               value="{{ $value['state'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label for="{{ $formKey }}-postcode" class="mb-1 block text-sm font-medium text-slate-700">Postcode</label>
        <input type="text" name="postcode" id="{{ $formKey }}-postcode" autocomplete="postal-code"
               value="{{ $value['postcode'] }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label for="{{ $formKey }}-country" class="mb-1 block text-sm font-medium text-slate-700">Country</label>
        <select name="country" id="{{ $formKey }}-country" required autocomplete="country"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="">Choose a country</option>
            @foreach ($countries as $code => $countryName)
                <option value="{{ $code }}" @selected($value['country'] === (string) $code)>{{ $countryName }}</option>
            @endforeach
        </select>
    </div>
</div>

<label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="make_default" value="1" class="h-4 w-4 rounded border-slate-300"
           @checked($address?->is_default_billing)>
    Use this address at checkout by default
</label>

@if ($mine && $errors->any())
    <ul class="mt-4 space-y-1 text-xs text-rose-600">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
