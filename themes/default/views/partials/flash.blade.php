@php
    // The front layout ships no Alpine, so this partial is plain HTML + a few
    // lines of vanilla JS, with its own scoped styles (no asset rebuild needed).
    $flashes = ['status' => 'ok', 'warning' => 'warn', 'error' => 'bad'];
    $titles  = ['status' => 'Success', 'warning' => 'Heads up', 'error' => 'Something went wrong'];

    $messages = [];
    foreach ($flashes as $key => $tone) {
        if (session($key)) {
            $messages[] = ['tone' => $tone, 'title' => $titles[$key], 'text' => session($key)];
        }
    }

    $tone  = $errors->any() ? 'bad' : ($messages[0]['tone'] ?? null);
    $title = $errors->any() ? 'Please check the form' : ($messages[0]['title'] ?? null);
@endphp

@if ($messages || $errors->any())
    <dialog id="flash-dialog" class="flash-dialog flash-dialog--{{ $tone }}"
            aria-labelledby="flash-dialog-title" role="{{ $tone === 'ok' ? 'status' : 'alertdialog' }}">
        <div class="flash-dialog__icon" aria-hidden="true">
            @if ($tone === 'ok')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5m0 3.5v.01"/></svg>
            @endif
        </div>

        <h2 id="flash-dialog-title" class="flash-dialog__title">{{ $title }}</h2>

        @foreach ($messages as $message)
            <p class="flash-dialog__text">{{ $message['text'] }}</p>
        @endforeach

        @if ($errors->any())
            <ul class="flash-dialog__errors">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif

        <form method="dialog">
            <button type="submit" class="flash-dialog__btn btn-brand" autofocus>OK</button>
        </form>
    </dialog>

    <style>
        .flash-dialog { width: min(92vw, 26rem); padding: 1.75rem 1.5rem 1.5rem; border: 0; border-radius: 1rem;
            text-align: center; color: #1e293b; background: #fff; box-shadow: 0 20px 50px -12px rgb(15 23 42 / .35); }
        .flash-dialog::backdrop { background: rgb(15 23 42 / .45); backdrop-filter: blur(2px); }
        .flash-dialog[open] { animation: flash-dialog-in .18s ease-out; }
        @keyframes flash-dialog-in { from { opacity: 0; transform: translateY(8px) scale(.97); } }
        .flash-dialog__icon { width: 3rem; height: 3rem; margin: 0 auto .875rem; padding: .7rem; border-radius: 9999px; }
        .flash-dialog--ok   .flash-dialog__icon { background: #d1fae5; color: #047857; }
        .flash-dialog--warn .flash-dialog__icon { background: #fef3c7; color: #b45309; }
        .flash-dialog--bad  .flash-dialog__icon { background: #ffe4e6; color: #be123c; }
        .flash-dialog__title { margin: 0 0 .375rem; font-size: 1.125rem; font-weight: 600; }
        .flash-dialog__text { margin: 0 0 .25rem; font-size: .9375rem; color: #475569; }
        .flash-dialog__errors { margin: .5rem 0 0; padding-left: 1.1rem; text-align: left; font-size: .875rem; color: #be123c; list-style: disc; }
        .flash-dialog__btn { margin-top: 1.25rem; width: 100%; padding: .625rem 1rem; border: 0; border-radius: .625rem;
            color: #fff; font-weight: 600; cursor: pointer; }
        .flash-dialog__btn:hover { filter: brightness(1.08); }
        .dark .flash-dialog { background: #1e293b; color: #f1f5f9; }
        .dark .flash-dialog__text { color: #cbd5e1; }
    </style>

    <script>
        (function () {
            var dialog = document.getElementById('flash-dialog');
            if (!dialog) return;

            // Clicking the dimmed backdrop (outside the card) closes it too.
            dialog.addEventListener('click', function (e) {
                var r = dialog.getBoundingClientRect();
                if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dialog.close();
            });

            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
                dialog.querySelector('button').addEventListener('click', function (e) { e.preventDefault(); dialog.removeAttribute('open'); });
            }
        })();
    </script>
@endif
