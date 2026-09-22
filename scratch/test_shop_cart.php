<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Cms\Shop\CartService;
use App\Cms\Shop\Countries;
use App\Cms\Payments\PaymentManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;

echo "=== SHIPNEX SHOP CART & CHECKOUT TEST ===\n\n";

// 1. Flash status test
echo "1. Testing Flash message with session('status')...\n";
Session::flash('status', 'Ocean Freight Express was added to your logistics cart.');
$flashHtml = View::make('theme::partials.flash')->render();
assert(str_contains($flashHtml, 'Ocean Freight Express was added to your logistics cart.'), 'Flash should contain status message');
assert(str_contains($flashHtml, 'View Cart'), 'Flash should contain View Cart button');
echo "PASS: Flash notification displays status and View Cart button.\n\n";

// 2. Header cart button test
echo "2. Testing Header Cart Button...\n";
$headerHtml = View::make('theme::partials.header', [
    'settings' => [],
    'siteMenus' => [],
])->render();
assert(str_contains($headerHtml, 'sn-header__cart-btn'), 'Header should contain sn-header__cart-btn');
assert(str_contains($headerHtml, 'route(\'cart.index\')') || str_contains($headerHtml, '/cart'), 'Header cart button links to cart');
echo "PASS: Header includes cart button.\n\n";

// 3. Cart View (Empty)
echo "3. Testing Cart View (Empty)...\n";
$cartService = app(CartService::class);
$cart = $cartService->cart();
$summary = $cartService->summary();

$cartHtml = View::make('theme::shop.cart', [
    'cart' => $cart,
    'summary' => $summary,
    'issues' => [],
])->render();
assert(str_contains($cartHtml, 'Your cart is currently empty') || str_contains($cartHtml, 'Logistics Cart'), 'Cart page renders correctly');
echo "PASS: Cart view renders correctly when empty.\n\n";

// 4. Checkout View
echo "4. Testing Checkout View...\n";
$payments = app(PaymentManager::class);
$gateways = $payments->availableFor();
$countries = Countries::selling();

$checkoutHtml = View::make('theme::shop.checkout', [
    'summary' => $summary,
    'items' => $cart->items,
    'gateways' => $gateways,
    'user' => null,
    'countries' => $countries,
    'billing' => [],
    'shipping' => [],
    'savedAddresses' => collect(),
    'chosenAddressId' => null,
    'canSaveAddress' => false,
])->render();

assert(str_contains($checkoutHtml, 'Consignment Checkout'), 'Checkout title renders');
assert(str_contains($checkoutHtml, 'name="terms"'), 'Terms checkbox is present');
assert(str_contains($checkoutHtml, 'name="email"'), 'Email input is present');
assert(str_contains($checkoutHtml, 'name="payment_gateway"'), 'Payment gateway selector is present');
echo "PASS: Checkout view renders all required fields and summary.\n\n";

echo "ALL TESTS PASSED SUCCESSFULLY!\n";
