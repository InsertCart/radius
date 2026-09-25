<?php

namespace App\Http\Controllers\Front;

use App\Cms\Forms\ContactFormSchema;
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
        // What the form asked for, as the form itself declared it. A request
        // carrying no token - a theme's own page - gets the built-in schema,
        // and so does one whose token will not decrypt.
        $schema = ContactFormSchema::fromToken($request->input(ContactFormSchema::TOKEN_INPUT))
            ?? ContactFormSchema::legacy();

        $validated = $request->validate(
            ContactFormSchema::rules($schema),
            [],
            ContactFormSchema::attributes($schema)
        );

        $extra = ContactFormSchema::extraValues($schema, $validated);

        $submission = ContactSubmission::create([
            'name' => $validated['name'] ?? '',
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'] ?? '',
            'status' => 'new',
            'extra' => $extra ?: null,
            'ip_address' => $request->ip(),
        ]);

        $this->notifyOwner($submission);

        return back()
            ->with('status', $schema['success'])
            // Which form on the page was posted, so a page carrying several
            // of them shows the confirmation under the right one.
            ->with('contact_form', $request->input('_form'));
    }

    /** Best effort: a mail failure must not lose the submission itself. */
    private function notifyOwner(ContactSubmission $submission): void
    {
        $to = setting('site_email');

        if (blank($to)) {
            return;
        }

        $body = 'New message from '.($submission->name ?: 'someone')." <{$submission->email}>\n\n"
            ."Subject: {$submission->subject}\n\n{$submission->message}";

        foreach ($submission->extra ?? [] as $answer) {
            $body .= "\n\n{$answer['label']}: {$answer['value']}";
        }

        try {
            Mail::raw(
                $body,
                fn ($message) => $message
                    ->to($to)
                    ->replyTo($submission->email, $submission->name ?: null)
                    ->subject('Contact form: '.($submission->subject ?: 'New message'))
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
