<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Cms\Shop\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Finds the cart this request is talking about.
 *
 * A signed-in customer's cart is theirs, found by user id, and no token comes
 * into it. A guest has no session to key a cart by, so the app carries an
 * opaque cart token instead: 48 random characters, issued by the first cart
 * call and sent back in X-Cart-Token afterwards.
 *
 * The token is not a credential - it identifies a basket, not a person, and
 * the request is already authenticated as a registered app before it reaches
 * here. It is random enough not to be guessable, which is what stops one
 * guest's basket showing up in another's.
 */
trait ResolvesApiCart
{
    /**
     * Point the cart service at this caller's cart.
     *
     * @return string|null The guest's cart token, when there is one. Null for
     *                     a signed-in customer, whose cart needs no token.
     */
    protected function bindCart(Request $request, CartService $cart): ?string
    {
        if ($request->user()) {
            $cart->useGuestKey(null);

            return null;
        }

        $token = $this->presentedCartToken($request) ?? 'cart_'.Str::random(48);

        $cart->useGuestKey($token);

        return $token;
    }

    /** A well-formed token from the request, or null. */
    protected function presentedCartToken(Request $request): ?string
    {
        $token = trim((string) ($request->header('X-Cart-Token') ?: $request->input('cart_token', '')));

        // Checked rather than trusted: this value becomes a database lookup,
        // and a 10,000 character "token" is not a cart.
        return preg_match('/^cart_[A-Za-z0-9]{32,64}$/', $token) === 1 ? $token : null;
    }
}
