<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Firebase\FirebaseManager;
use App\Cms\Sms\SmsManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

/**
 * Maintenance actions and "does this actually work?" tests for the
 * integrations, so a buyer can confirm their credentials without placing a
 * real order or waiting for a real signup.
 */
class ToolsController extends Controller
{
    public function clearCache(): RedirectResponse
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        settings()->flush();
        modules()->flush();
        themes()->flush();

        activity('system.cache_cleared', 'Cleared the application caches.');

        return back()->with('status', 'Caches cleared.');
    }

    /**
     * Caches config, routes and views for production. Deliberately separate
     * from clearing, because caching routes freezes the module toggles.
     */
    public function optimize(): RedirectResponse
    {
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Optimisation failed: '.$e->getMessage());
        }

        activity('system.optimized', 'Cached the config, routes and views.');

        return back()->with('status', 'Optimised. Remember to clear the caches again before changing modules or settings that affect routing.');
    }

    public function storageLink(): RedirectResponse
    {
        if (file_exists(public_path('storage'))) {
            return back()->with('status', 'The storage link already exists.');
        }

        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            return back()->with('error',
                'Could not create the symlink, which is common on shared hosting. Ask your host to link public/storage to storage/app/public, or copy the folder manually.');
        }

        return back()->with('status', 'Storage link created.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            Mail::raw(
                "This is a test message from {$request->getHost()}.\n\nIf you are reading it, your email settings are working.",
                fn ($message) => $message
                    ->to($validated['email'])
                    ->subject('Test email from '.setting('site_name', config('app.name')))
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The email could not be sent: '.$e->getMessage());
        }

        return back()->with('status', "Test email sent to {$validated['email']}. Check the inbox and the spam folder.");
    }

    public function testSms(Request $request, SmsManager $sms): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $result = $sms->send(
            $validated['phone'],
            'Test message from '.setting('site_name', config('app.name')).'. Your SMS settings are working.'
        );

        return $result->successful
            ? back()->with('status', "Test SMS sent to {$validated['phone']}.")
            : back()->with('error', 'The SMS could not be sent: '.$result->error);
    }

    public function testPush(Request $request, FirebaseManager $firebase): RedirectResponse
    {
        if (! $firebase->hasServiceAccount()) {
            return back()->with('error',
                'No Firebase service account found. Upload it to '.$firebase->credentialsPath().' first.');
        }

        $result = $firebase->broadcast(
            setting('site_name', config('app.name')),
            'This is a test notification. Your Firebase settings are working.'
        );

        if ($result['sent'] === 0 && $result['failed'] === 0) {
            return back()->with('warning', 'No devices are registered yet. Allow notifications on the site first.');
        }

        return back()->with('status', "Push sent to {$result['sent']} device(s); {$result['failed']} failed.");
    }
}
