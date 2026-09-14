<?php

namespace App\Http\Controllers\Front;

use App\Cms\Shop\DownloadService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in customer's own area.
 */
class AccountController extends Controller
{
    public function __construct(private DownloadService $downloads) {}

    public function dashboard(Request $request): View
    {
        seo()->title('Your account')->noindex();

        return view('theme::account.dashboard', [
            'user' => $request->user(),
            'recentOrders' => modules()->enabled('shop')
                ? Order::where('user_id', $request->user()->id)->latest()->limit(5)->get()
                : collect(),
        ]);
    }

    public function profile(Request $request): View
    {
        seo()->title('Your profile')->noindex();

        return view('theme::account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Changing the address invalidates the previous verification.
        if ($validated['email'] !== $user->email) {
            $validated['email_verified_at'] = null;
        }

        $user->fill($validated)->save();

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That password is not correct.',
            ]);
        }

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('status', 'Password changed.');
    }

    public function orders(Request $request): View
    {
        seo()->title('Your orders')->noindex();

        return view('theme::account.orders', [
            'orders' => Order::where('user_id', $request->user()->id)
                ->withCount('items')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function showOrder(Request $request, string $order): View
    {
        $order = $this->findOrder($request, $order);

        seo()->title('Order '.$order->order_number)->noindex();

        return view('theme::account.order', ['order' => $order->load('items')]);
    }

    /**
     * Serve a purchased download.
     *
     * The file lives on a disk with no public URL, so this method is the only
     * way to it. Every condition is checked here: the order belongs to the
     * signed-in customer, it has been paid for, the line item really belongs to
     * that order, and the customer is still within whatever download limit the
     * shop has set.
     */
    public function download(Request $request, string $order, OrderItem $item): StreamedResponse
    {
        $order = $this->findOrder($request, $order);

        // Loaded from the order rather than trusted from the URL, so a valid
        // item id from somebody else's order cannot be substituted.
        $item = $order->items()->whereKey($item->getKey())->firstOrFail();

        abort_unless(filled($item->digital_file), 404);

        if ($reason = $item->downloadRefusalReason()) {
            abort(403, $reason);
        }

        abort_unless($this->downloads->exists($item->digital_file), 404,
            'That file is no longer available. Please contact us.');

        $item->increment('download_count');

        activity('order.downloaded', "Downloaded \"{$item->name}\" from order {$order->order_number}.", $order);

        return $this->downloads->send($item);
    }

    private function findOrder(Request $request, string $orderNumber): Order
    {
        return Order::where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
