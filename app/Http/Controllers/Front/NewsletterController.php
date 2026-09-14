<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'size:0'],
        ]);

        $subscriber = Subscriber::firstOrNew(['email' => strtolower($validated['email'])]);

        // Re-subscribing an address that previously opted out is fine; the
        // same message is returned either way so the form cannot be used to
        // test whether an address is on the list.
        $subscriber->fill([
            'name' => $validated['name'] ?? $subscriber->name,
            'status' => 'subscribed',
            'source' => 'website',
            'ip_address' => $request->ip(),
            'confirmed_at' => $subscriber->confirmed_at ?? now(),
        ])->save();

        return back()->with('status', 'Thanks for subscribing.');
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();

        $subscriber->update(['status' => 'unsubscribed']);

        seo()->title('Unsubscribed')->noindex();

        return view('theme::newsletter-unsubscribed', ['subscriber' => $subscriber]);
    }
}
