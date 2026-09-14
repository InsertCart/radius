<?php

namespace App\Http\Controllers\Front;

use App\Cms\Firebase\FirebaseManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Firebase web push: token registration and the service worker.
 */
class PushController extends Controller
{
    public function __construct(private FirebaseManager $firebase) {}

    public function register(Request $request): JsonResponse
    {
        abort_unless($this->firebase->isEnabled(), 404);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        $this->firebase->registerDevice(
            $validated['token'],
            $request->user()?->id,
            $validated['platform'] ?? 'web'
        );

        return response()->json(['registered' => true]);
    }

    /**
     * The service worker is generated rather than served as a static file so
     * it always carries the current Firebase config, and so it can be served
     * from the site root where a worker needs to live to control every page.
     */
    public function serviceWorker(): Response
    {
        abort_unless($this->firebase->isEnabled(), 404);

        $config = json_encode($this->firebase->clientConfig(), JSON_UNESCAPED_SLASHES);
        $siteName = json_encode(setting('site_name', config('app.name')));

        $script = <<<JS
        importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
        importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

        firebase.initializeApp({$config});

        const messaging = firebase.messaging();

        messaging.onBackgroundMessage(function (payload) {
            const notification = payload.notification || {};

            self.registration.showNotification(notification.title || {$siteName}, {
                body: notification.body || '',
                icon: notification.icon || '/favicon.ico',
                data: payload.data || {},
            });
        });

        self.addEventListener('notificationclick', function (event) {
            event.notification.close();

            const link = (event.notification.data && event.notification.data.link) || '/';
            event.waitUntil(clients.openWindow(link));
        });
        JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            // A worker may only control pages at or below its own scope.
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
