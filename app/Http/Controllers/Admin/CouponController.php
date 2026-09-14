<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::when($request->filled('q'),
                fn ($q) => $q->where('code', 'like', '%'.$request->string('q').'%'))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['type' => 'percent', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = Coupon::create($this->attributes($request->validate($this->rules()), $request));

        activity('coupon.created', "Created the coupon {$coupon->code}.", $coupon);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->fill($this->attributes($request->validate($this->rules($coupon)), $request))->save();

        activity('coupon.updated', "Updated the coupon {$coupon->code}.", $coupon);

        return back()->with('status', 'Coupon saved.');
    }

    public function show(Coupon $coupon): RedirectResponse
    {
        return redirect()->route('admin.coupons.edit', $coupon);
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $code = $coupon->code;
        $coupon->delete();

        activity('coupon.deleted', "Deleted the coupon {$code}.");

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon deleted.');
    }

    private function rules(?Coupon $coupon = null): array
    {
        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:percent,fixed,free_shipping'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function attributes(array $validated, Request $request): array
    {
        return [
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            // Percentages are stored to two decimal places (12.5% -> 1250);
            // fixed amounts use the same minor-unit convention as prices.
            'value' => to_minor_units($validated['value']),
            'min_order_total' => to_minor_units($validated['min_order_total'] ?? 0),
            'max_discount' => filled($validated['max_discount'] ?? null) ? to_minor_units($validated['max_discount']) : null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_limit_per_user' => $validated['usage_limit_per_user'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
