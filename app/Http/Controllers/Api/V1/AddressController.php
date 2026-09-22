<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\Resource;
use App\Cms\Shop\AddressBook;
use App\Cms\Shop\Countries;
use App\Http\Controllers\Api\ApiController;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The customer's own address book, so checkout never asks twice.
 *
 * Every row is found through the signed-in customer's own relation. An id
 * from somewhere else does not resolve to a 403 with a hint attached - it
 * simply is not there.
 */
class AddressController extends ApiController
{
    public function __construct(private AddressBook $addresses) {}

    public function index(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseWhenOff()) {
            return $refusal;
        }

        return $this->data(
            $request->user()->addresses()->get()
                ->map(fn (Address $address) => Resource::address($address))
                ->all(),
            ['meta' => ['countries' => Countries::selling()]]
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseWhenOff()) {
            return $refusal;
        }

        $validated = $request->validate($this->rules());

        $address = $request->user()->addresses()->create(
            $this->addresses->normalise($validated) + ['label' => $validated['label'] ?? null]
        );

        // The first address saved is the one checkout should reach for.
        if ($request->boolean('make_default') || $request->user()->addresses()->count() === 1) {
            $address->makeDefault();
        }

        return $this->data(Resource::address($address->fresh()), status: 201);
    }

    public function update(Request $request, int $address): JsonResponse
    {
        if ($refusal = $this->refuseWhenOff()) {
            return $refusal;
        }

        $row = $this->own($request, $address);

        if (! $row) {
            return $this->fail('No such address.', 404, 'not_found');
        }

        $validated = $request->validate($this->rules());

        $row->update(
            $this->addresses->normalise($validated) + ['label' => $validated['label'] ?? null]
        );

        if ($request->boolean('make_default')) {
            $row->makeDefault();
        }

        return $this->data(Resource::address($row->fresh()));
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        if ($refusal = $this->refuseWhenOff()) {
            return $refusal;
        }

        $row = $this->own($request, $address);

        if (! $row) {
            return $this->fail('No such address.', 404, 'not_found');
        }

        $wasDefault = $row->is_default_billing || $row->is_default_shipping;

        $row->delete();

        // Deleting the default would leave checkout with nothing to fill
        // itself in from, so the next one takes over.
        if ($wasDefault && $next = $request->user()->addresses()->first()) {
            $next->makeDefault();
        }

        return $this->message('Address removed.');
    }

    // Internals -------------------------------------------------------------

    private function own(Request $request, int $id): ?Address
    {
        return $request->user()->addresses()->find($id);
    }

    /** A shop that does not remember addresses has no address book. */
    private function refuseWhenOff(): ?JsonResponse
    {
        return setting('shop_save_addresses', true)
            ? null
            : $this->fail('This shop does not keep an address book.', 404, 'not_available');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:190'],
            'line2' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', Rule::in(Countries::allowedCodes())],
            'make_default' => ['nullable', 'boolean'],
        ];
    }
}
