<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Firebase\FirebaseManager;
use App\Http\Controllers\Api\ApiController;
use App\Models\ContactSubmission;
use App\Models\PushDevice;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * The small write endpoints: a contact message, a newsletter sign-up and a
 * device's push token.
 *
 * Each one is the API half of a form the website already has, and each lands
 * in the same place the form does - the inbox under Messages, the subscriber
 * list, the device list - so an owner has one place to look regardless of
 * where something came from.
 */
class MessageController extends ApiController
{
    public function contact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $submission = ContactSubmission::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        $this->notifyOwner($submission);

        return $this->message('Thanks for getting in touch. We will reply shortly.', status: 201);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $subscriber = Subscriber::firstOrNew(['email' => strtolower($validated['email'])]);

        $subscriber->fill([
            'name' => $validated['name'] ?? $subscriber->name,
            'status' => 'subscribed',
            'source' => 'app',
            'ip_address' => $request->ip(),
            'confirmed_at' => $subscriber->confirmed_at ?? now(),
        ])->save();

        // The same answer whether or not the address was already on the list,
        // so this cannot be used to test which addresses are subscribed.
        return $this->message('Thanks for subscribing.');
    }

    /**
     * Register this device for push.
     *
     * Attached to the signed-in customer when there is one, so notifications
     * about an order reach the person who placed it; anonymous otherwise.
     */
    public function registerDevice(Request $request, FirebaseManager $firebase): JsonResponse
    {
        if (! $firebase->isEnabled()) {
            return $this->fail('Push notifications are not switched on for this site.', 404, 'not_available');
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        $firebase->registerDevice(
            $validated['token'],
            $request->user()?->id,
            $validated['platform'] ?? 'mobile'
        );

        return $this->message('Device registered.');
    }

    public function forgetDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        PushDevice::where('token_hash', hash('sha256', $validated['token']))->delete();

        return $this->message('Device forgotten.');
    }

    /** Best effort: a mail failure must not lose the submission itself. */
    private function notifyOwner(ContactSubmission $submission): void
    {
        $to = setting('site_email');

        if (blank($to)) {
            return;
        }

        try {
            Mail::raw(
                "New message from {$submission->name} <{$submission->email}>\n\n"
                ."Subject: {$submission->subject}\n\n{$submission->message}",
                fn ($message) => $message
                    ->to($to)
                    ->replyTo($submission->email, $submission->name)
                    ->subject('Contact form: '.($submission->subject ?: 'New message'))
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
