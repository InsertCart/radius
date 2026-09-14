{{-- Firebase web push. The token is registered only after the visitor grants
     permission, and the whole block is skipped when Firebase is switched off. --}}
@php $firebase = app(\App\Cms\Firebase\FirebaseManager::class); @endphp

@push('scripts')
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import { getMessaging, getToken, onMessage } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging.js';

    const app = initializeApp(@json($firebase->clientConfig()));

    async function subscribe() {
        if (!('serviceWorker' in navigator) || Notification.permission === 'denied') return;

        try {
            const registration = await navigator.serviceWorker.register('{{ route('push.sw') }}');
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') return;

            const messaging = getMessaging(app);
            const token = await getToken(messaging, {
                vapidKey: @json($firebase->vapidKey()),
                serviceWorkerRegistration: registration,
            });

            if (!token) return;

            await fetch('{{ route('push.register') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ token, platform: 'web' }),
            });

            onMessage(messaging, (payload) => {
                const n = payload.notification || {};
                new Notification(n.title || @json(setting('site_name')), { body: n.body || '' });
            });
        } catch (error) {
            // A blocked or unsupported browser is not worth surfacing.
        }
    }

    // Asking on load is a good way to get denied. Wait for a real interaction.
    window.addEventListener('click', subscribe, { once: true });
</script>
@endpush
