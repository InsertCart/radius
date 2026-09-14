<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Redirecting to payment...</title>
    @vite('resources/css/app.css')
</head>
<body class="grid min-h-screen place-items-center bg-slate-100 px-4 text-center">
    <div>
        <p class="text-sm text-slate-600">Taking you to {{ ucfirst($gateway) }} to complete your payment...</p>
        <p class="mt-2 text-xs text-slate-400">Do not close this window.</p>

        {{-- Posted automatically; the button is the fallback when JS is off. --}}
        <form id="gateway-form" method="POST" action="{{ $action }}" class="mt-6">
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript>
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Continue to payment
                </button>
            </noscript>
        </form>
    </div>

    <script>document.getElementById('gateway-form').submit();</script>
</body>
</html>
