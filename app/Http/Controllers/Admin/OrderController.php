<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Payments\Drivers\WiseGateway;
use App\Cms\Payments\PaymentManager;
use App\Cms\Shop\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private PaymentManager $payments,
    ) {}

    public function index(Request $request): View
    {
        $orders = Order::with('user')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('order_number', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('transaction_id', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'filters' => $request->only(['q', 'status', 'payment_status']),
            'totals' => [
                'revenue' => (int) Order::paid()->sum('grand_total'),
                'pending' => Order::where('status', 'pending')->count(),
                'unpaid' => Order::whereIn('payment_status', ['unpaid', 'awaiting'])->count(),
            ],
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items.product', 'transactions', 'user']),
            'statuses' => Order::STATUSES,
            'canRefund' => $this->canRefund($order),
            'canReconcile' => $order->payment_gateway === 'wise' && ! $order->isPaid(),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
            'tracking_number' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->orders->updateStatus($order, $validated['status'], $validated['note'] ?? null);

        if (filled($validated['tracking_number'] ?? null)) {
            $order->update(['tracking_number' => $validated['tracking_number']]);
        }

        activity('order.status_changed', "Set order {$order->order_number} to {$validated['status']}.", $order);

        return back()->with('status', 'Order updated.');
    }

    /**
     * Mark an offline payment as received. Used for bank transfer, Wise and
     * cash on delivery, where no provider tells us the money arrived.
     */
    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['nullable', 'string', 'max:190'],
        ]);

        if ($order->isPaid()) {
            return back()->with('error', 'This order is already marked paid.');
        }

        $order->markPaid($validated['transaction_id'] ?? null);

        activity('order.marked_paid', "Marked order {$order->order_number} as paid.", $order);

        return back()->with('status', 'Order marked as paid.');
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:'.from_minor_units($order->grand_total)],
        ]);

        if (! $this->canRefund($order)) {
            return back()->with('error', 'This order cannot be refunded automatically. Refund it in your provider dashboard, then set the status to Refunded here.');
        }

        $amount = filled($validated['amount'] ?? null) ? to_minor_units($validated['amount']) : null;

        try {
            $result = $this->payments->driver($order->payment_gateway)->refund($order, $amount);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The refund could not be sent: '.$e->getMessage());
        }

        if (! $result->isSuccessful()) {
            return back()->with('error', $result->message ?? 'The gateway refused the refund.');
        }

        $isPartial = $amount !== null && $amount < $order->grand_total;

        $order->update(['payment_status' => $isPartial ? 'partially_refunded' : 'refunded']);

        if (! $isPartial) {
            $this->orders->updateStatus($order, 'refunded');
        }

        activity('order.refunded', "Refunded order {$order->order_number}.", $order, ['amount' => $amount]);

        return back()->with('status', 'Refund sent.');
    }

    /** Ask Wise whether a matching transfer has landed for this order. */
    public function reconcile(Order $order): RedirectResponse
    {
        if ($order->payment_gateway !== 'wise') {
            return back()->with('error', 'Reconciliation is only available for Wise transfers.');
        }

        try {
            $driver = $this->payments->driver('wise');
        } catch (\Throwable $e) {
            return back()->with('error', 'The Wise gateway is not configured.');
        }

        if (! $driver instanceof WiseGateway) {
            return back()->with('error', 'The Wise gateway is not available.');
        }

        $result = $driver->reconcile($order);

        if ($result->isSuccessful()) {
            $order->markPaid($result->reference);

            activity('order.reconciled', "Matched a Wise transfer to order {$order->order_number}.", $order);

            return back()->with('status', 'A matching transfer was found. The order is now marked paid.');
        }

        return back()->with('warning', $result->message ?? 'No matching transfer found yet.');
    }

    public function invoice(Order $order): View
    {
        return view('admin.orders.invoice', [
            'order' => $order->load('items'),
        ]);
    }

    /** Only paid orders on gateways whose driver implements refunds. */
    private function canRefund(Order $order): bool
    {
        if (! $order->isPaid() || blank($order->payment_gateway)) {
            return false;
        }

        return (bool) config("payments.gateways.{$order->payment_gateway}.supports_refund", false);
    }
}
