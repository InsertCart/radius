<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        seo()->forRoute('contact')->schema('ContactPage');

        return view('theme::contact');
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot; bots fill it, humans never see it.
            'website' => ['nullable', 'size:0'],
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

        return back()->with('status', 'Thanks for getting in touch. We will reply shortly.');
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
