<?php

namespace App\Models;

use App\Cms\Shop\Countries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One entry in a customer's address book.
 *
 * Checkout writes a copy of whichever address was used into the order itself,
 * so this row only ever has to answer "what shall we fill the form in with
 * next time" - editing or deleting it cannot disturb a past order.
 */
class Address extends Model
{
    /** The address fields an order stores, in the order they read. */
    public const FIELDS = ['name', 'phone', 'line1', 'line2', 'city', 'state', 'postcode', 'country'];

    protected $fillable = [
        'user_id', 'label', 'name', 'phone', 'line1', 'line2',
        'city', 'state', 'postcode', 'country',
        'is_default_billing', 'is_default_shipping',
    ];

    protected function casts(): array
    {
        return [
            'is_default_billing' => 'boolean',
            'is_default_shipping' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Defaults first, then most recently added. */
    public function scopeInPickOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_default_billing')
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('id');
    }

    // Presentation --------------------------------------------------------

    public function countryName(): string
    {
        return Countries::name($this->country);
    }

    /** The shape checkout posts and an order stores. */
    public function toOrderArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
        ];
    }

    public function singleLine(): string
    {
        return format_address($this->toOrderArray());
    }

    /** What the address book card is titled. */
    public function title(): string
    {
        return $this->label ?: $this->name;
    }

    // Writing -------------------------------------------------------------

    /**
     * The same address twice in the book is clutter, not a feature. Two
     * addresses match when every field a customer typed matches.
     */
    public function matches(array $address): bool
    {
        foreach (self::FIELDS as $field) {
            $mine = strtolower(trim((string) $this->{$field}));
            $theirs = strtolower(trim((string) ($address[$field] ?? '')));

            if ($mine !== $theirs) {
                return false;
            }
        }

        return true;
    }

    /**
     * Make this the customer's default, clearing whichever address held the
     * flag before. Done in one transaction so a failure halfway cannot leave
     * a customer with two defaults - or none.
     */
    public function makeDefault(bool $billing = true, bool $shipping = true): void
    {
        DB::transaction(function () use ($billing, $shipping) {
            $siblings = static::forUser($this->user_id)->whereKeyNot($this->getKey());

            if ($billing) {
                (clone $siblings)->update(['is_default_billing' => false]);
                $this->is_default_billing = true;
            }

            if ($shipping) {
                (clone $siblings)->update(['is_default_shipping' => false]);
                $this->is_default_shipping = true;
            }

            $this->save();
        });
    }
}
