<?php

namespace App\Cms\Shop;

use Illuminate\Validation\Rule;

/**
 * Which checkout fields the shop asks for, and which of them it insists on.
 *
 * Set under Settings -> Checkout. Every checkout - a theme's own page, the
 * builder's Checkout widget and the mobile API - takes its validation rules
 * from here, so hiding or requiring a field is enforced by the server and
 * not merely by what a form happens to draw.
 *
 * Email and full name are not configurable: an order with no email cannot be
 * confirmed or looked up, and every payment provider and address record
 * needs a name.
 */
class CheckoutFields
{
    public const REQUIRED = 'required';

    public const OPTIONAL = 'optional';

    public const HIDDEN = 'hidden';

    /**
     * The configurable fields and how each behaved before it was configurable,
     * which is also what a site that never opens the settings keeps getting.
     */
    public const DEFAULTS = [
        'phone' => self::OPTIONAL,
        'line1' => self::REQUIRED,
        'line2' => self::OPTIONAL,
        'city' => self::REQUIRED,
        'state' => self::OPTIONAL,
        'postcode' => self::OPTIONAL,
        'country' => self::REQUIRED,
        'customer_note' => self::OPTIONAL,
    ];

    /** The address parts, in the order a form lays them out. */
    public const ADDRESS = ['line1', 'line2', 'city', 'state', 'postcode', 'country'];

    private const MAX = [
        'name' => 120, 'phone' => 30, 'line1' => 190, 'line2' => 190,
        'city' => 120, 'state' => 120, 'postcode' => 30, 'customer_note' => 1000,
    ];

    public function mode(string $field): string
    {
        if (! array_key_exists($field, self::DEFAULTS)) {
            return self::REQUIRED;
        }

        // A shop that only sells to some countries has to know where the
        // order is going, or it cannot refuse the ones it does not serve.
        if ($field === 'country' && ! Countries::sellsWorldwide()) {
            return self::REQUIRED;
        }

        $mode = setting('checkout_field_'.$field, self::DEFAULTS[$field]);

        return in_array($mode, [self::REQUIRED, self::OPTIONAL, self::HIDDEN], true)
            ? $mode
            : self::DEFAULTS[$field];
    }

    public function shows(string $field): bool
    {
        return $this->mode($field) !== self::HIDDEN;
    }

    public function requires(string $field): bool
    {
        return $this->mode($field) === self::REQUIRED;
    }

    /**
     * Whether the form asks for an address at all.
     *
     * A shop selling only downloads can hide every part of it; the "ship to a
     * different address" choice then has nothing to offer and is left out.
     */
    public function asksForAddress(): bool
    {
        foreach (self::ADDRESS as $field) {
            if ($this->shows($field)) {
                return true;
            }
        }

        return false;
    }

    /** Every field's mode, for a mobile app to build its form from. */
    public function toArray(): array
    {
        $modes = ['email' => self::REQUIRED, 'name' => self::REQUIRED];

        foreach (array_keys(self::DEFAULTS) as $field) {
            $modes[$field] = $this->mode($field);
        }

        return $modes;
    }

    /**
     * Validation rules for the contact, note and address fields.
     *
     * A hidden field gets no rule at all, so anything posted for it is left
     * out of the validated data and never reaches the order.
     */
    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'email', 'max:190'],
            'billing.name' => ['required', 'string', 'max:'.self::MAX['name']],
        ];

        foreach (['phone', 'customer_note'] as $field) {
            if ($this->shows($field)) {
                $rules[$field] = [$this->requires($field) ? 'required' : 'nullable', 'string', 'max:'.self::MAX[$field]];
            }
        }

        foreach (self::ADDRESS as $field) {
            if ($this->shows($field)) {
                $rules['billing.'.$field] = [$this->requires($field) ? 'required' : 'nullable', ...$this->typeRules($field)];
            }
        }

        if ($this->asksForAddress()) {
            $rules['ship_to_different'] = ['nullable', 'boolean'];
            $rules['shipping.name'] = ['required_if:ship_to_different,1', 'nullable', 'string', 'max:'.self::MAX['name']];

            foreach (self::ADDRESS as $field) {
                if ($this->shows($field)) {
                    $rules['shipping.'.$field] = [
                        $this->requires($field) ? 'required_if:ship_to_different,1' : 'nullable',
                        'nullable',
                        ...$this->typeRules($field),
                    ];
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'billing.country.in' => 'We are not able to sell to that country yet.',
            'shipping.country.in' => 'We are not able to deliver to that country yet.',
        ];
    }

    private function typeRules(string $field): array
    {
        return $field === 'country'
            ? ['string', Rule::in(Countries::allowedCodes())]
            : ['string', 'max:'.self::MAX[$field]];
    }
}
