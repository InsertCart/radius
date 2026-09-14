@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-md px-4 py-24 text-center">
        <h1 class="text-xl font-semibold text-slate-900">Completing your payment</h1>
        <p class="mt-2 text-sm text-slate-600">Order {{ $order->order_number }} &middot; {{ money($order->grand_total, $order->currency) }}</p>
        <p class="mt-6 text-sm text-slate-500">Opening the secure payment window...</p>

        <button id="pay-now" class="mt-6 rounded-lg px-6 py-3 text-sm font-semibold text-white btn-brand">
            Open payment window
        </button>

        <p class="mt-4 text-xs text-slate-400">
            <a href="{{ route('checkout.cancel', ['gateway' => $gateway, 'order' => $order->order_number]) }}"
               class="hover:text-slate-600">Cancel and return</a>
        </p>
    </div>
@endsection

@push('scripts')
    @if ($gateway === 'razorpay')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            // Razorpay renders its modal over this page and POSTs the signed
            // result to callback_url, which our return route then verifies.
            const options = @json($options);
            const open = () => new Razorpay(options).open();

            document.getElementById('pay-now').addEventListener('click', open);
            window.addEventListener('load', open);
        </script>
    @elseif ($gateway === 'cashfree')
        <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
        <script>
            const options = @json($options);

            const open = async () => {
                const cashfree = Cashfree({ mode: options.mode });
                await cashfree.checkout({
                    paymentSessionId: options.payment_session_id,
                    redirectTarget: options.redirect_target || '_self',
                });
            };

            document.getElementById('pay-now').addEventListener('click', open);
            window.addEventListener('load', open);
        </script>
    @endif
@endpush
