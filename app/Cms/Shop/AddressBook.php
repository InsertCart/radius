<?php

namespace App\Cms\Shop;

use App\Models\Address;
use App\Models\User;

/**
 * Remembers the address a customer checked out with.
 *
 * Nobody should have to type their own address twice. A signed-in customer
 * gets it kept in their address book; a guest gets it kept in their session,
 * which at least covers coming back to the shop the same day.
 */
class AddressBook
{
    private const GUEST_KEY = 'checkout.addresses';

    /** Trim, drop the blanks, and settle the country on its ISO code. */
    public function normalise(array $address): array
    {
        $clean = [];

        foreach (Address::FIELDS as $field) {
            $value = trim((string) ($address[$field] ?? ''));
            $clean[$field] = $value === '' ? null : $value;
        }

        $clean['country'] = Countries::codeFor($clean['country']) ?? $clean['country'];

        return $clean;
    }

    /**
     * What the checkout form should open with.
     *
     * The order is deliberate: a saved address beats the session, and both
     * beat the bare name and phone off the account.
     */
    public function prefill(?User $user, string $kind = 'billing'): array
    {
        if ($user && $saved = $user->defaultAddress($kind)) {
            return $saved->toOrderArray();
        }

        $remembered = (array) session(self::GUEST_KEY.'.'.$kind, []);

        if ($remembered !== []) {
            return $this->normalise($remembered);
        }

        if (! $user) {
            return [];
        }

        return $this->normalise(['name' => $user->name, 'phone' => $user->phone]);
    }

    /**
     * Keep this address for next time.
     *
     * The first address a customer uses is saved whether or not they asked,
     * because a checkout form that has forgotten them is the thing they were
     * complaining about. Once they have a book, a new address is only added
     * when they tick the box - otherwise a one-off delivery to a friend would
     * quietly pile up in their account.
     */
    public function remember(?User $user, array $address, string $kind = 'billing', bool $asked = false): ?Address
    {
        $address = $this->normalise($address);

        if (blank($address['line1'] ?? null)) {
            return null;
        }

        session([self::GUEST_KEY.'.'.$kind => $address]);

        // The address book needs a whole address. A shop that has hidden the
        // city or country at checkout still remembers the rest for next time
        // above, but has nothing here worth saving.
        if (! $user || blank($address['city'] ?? null) || blank($address['country'] ?? null)) {
            return null;
        }

        $existing = $user->addresses()->get();

        // Already in the book: just move the default onto it, so the address
        // they actually used is the one that comes back next time.
        if ($match = $existing->first(fn (Address $saved) => $saved->matches($address))) {
            $match->makeDefault($kind === 'billing', $kind === 'shipping');

            return $match;
        }

        if ($existing->isNotEmpty() && ! $asked) {
            return null;
        }

        $saved = $user->addresses()->create($address + [
            'is_default_billing' => $existing->isEmpty() || $kind === 'billing',
            'is_default_shipping' => $existing->isEmpty() || $kind === 'shipping',
        ]);

        if (! $existing->isEmpty()) {
            $saved->makeDefault($kind === 'billing', $kind === 'shipping');
        }

        return $saved;
    }
}
