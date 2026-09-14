@extends('theme::layout')

@section('content')
    <div class="sf-wrap sf-wrap--narrow sf-center sf-pad-xl">
        <h1 class="sf-section__title">Completing your payment</h1>
        <p class="sf-muted sf-mt">
            Order {{ $order->order_number }} &middot; {{ money($order->grand_total, $order->currency) }}
        </p>
        <p class="sf-small sf-muted sf-mt">Opening the secure payment window&hellip;</p>

        <p class="sf-mt-lg">
            <button id="pay-now" class="sf-btn sf-btn--primary sf-btn--lg">Open payment window</button>
        </p>

        <p class="sf-mt">
            <a href="{{ route('checkout.cancel', ['gateway' => $gateway, 'order' => $order->order_number]) }}"
               class="sf-small sf-muted">Cancel and return</a>
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
