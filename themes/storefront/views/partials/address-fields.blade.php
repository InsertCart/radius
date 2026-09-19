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

<div class="sf-fields sf-fields--2">
    <div class="sf-span2">
        <label for="{{ $formKey }}-label" class="sf-label">Name this address (optional)</label>
        <input type="text" name="label" id="{{ $formKey }}-label" class="sf-input" placeholder="Home, Office"
               value="{{ $value['label'] }}">
    </div>
    <div>
        <label for="{{ $formKey }}-name" class="sf-label">Full name</label>
        <input type="text" name="name" id="{{ $formKey }}-name" required class="sf-input" autocomplete="name"
               value="{{ $value['name'] }}">
    </div>
    <div>
        <label for="{{ $formKey }}-phone" class="sf-label">Phone</label>
        <input type="text" name="phone" id="{{ $formKey }}-phone" class="sf-input" autocomplete="tel"
               value="{{ $value['phone'] }}">
    </div>
    <div class="sf-span2">
        <label for="{{ $formKey }}-line1" class="sf-label">Address</label>
        <input type="text" name="line1" id="{{ $formKey }}-line1" required class="sf-input" autocomplete="address-line1"
               value="{{ $value['line1'] }}">
    </div>
    <div class="sf-span2">
        <label for="{{ $formKey }}-line2" class="sf-label">Apartment, suite (optional)</label>
        <input type="text" name="line2" id="{{ $formKey }}-line2" class="sf-input" autocomplete="address-line2"
               value="{{ $value['line2'] }}">
    </div>
    <div>
        <label for="{{ $formKey }}-city" class="sf-label">City</label>
        <input type="text" name="city" id="{{ $formKey }}-city" required class="sf-input" autocomplete="address-level2"
               value="{{ $value['city'] }}">
    </div>
    <div>
        <label for="{{ $formKey }}-state" class="sf-label">State / region</label>
        <input type="text" name="state" id="{{ $formKey }}-state" class="sf-input" autocomplete="address-level1"
               value="{{ $value['state'] }}">
    </div>
    <div>
        <label for="{{ $formKey }}-postcode" class="sf-label">Postcode</label>
        <input type="text" name="postcode" id="{{ $formKey }}-postcode" class="sf-input" autocomplete="postal-code"
               value="{{ $value['postcode'] }}">
    </div>
    <div>
        <label for="{{ $formKey }}-country" class="sf-label">Country</label>
        <select name="country" id="{{ $formKey }}-country" required class="sf-select" autocomplete="country">
            <option value="">Choose a country</option>
            @foreach ($countries as $code => $countryName)
                <option value="{{ $code }}" @selected($value['country'] === (string) $code)>{{ $countryName }}</option>
            @endforeach
        </select>
    </div>
</div>

<label class="sf-check sf-mt">
    <input type="checkbox" name="make_default" value="1" @checked($address?->is_default_billing)>
    <span>Use this address at checkout by default</span>
</label>

@if ($mine && $errors->any())
    <ul class="sf-help sf-mt">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
