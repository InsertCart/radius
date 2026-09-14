@extends('admin.layout')
@section('title', 'Coupons')

@section('content')
    <x-admin.card bodyClass="">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <form method="GET" class="flex-1">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search codes"
                       class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </form>
            <a href="{{ route('admin.coupons.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New coupon</a>
        </div>

        @if ($coupons->isEmpty())
            <x-admin.empty message="No coupons yet." action="Create one" :actionUrl="route('admin.coupons.create')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Code</th>
                            <th class="px-4 py-2.5 font-medium">Discount</th>
                            <th class="px-4 py-2.5 font-medium">Used</th>
                            <th class="px-4 py-2.5 font-medium">Expires</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($coupons as $coupon)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="font-mono font-medium text-indigo-600 hover:underline">
                                        {{ $coupon->code }}
                                    </a>
                                    @if ($coupon->description)
                                        <p class="text-xs text-slate-400">{{ $coupon->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $coupon->displayValue() }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ $coupon->used_count }}@if ($coupon->usage_limit) / {{ $coupon->usage_limit }} @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ $coupon->expires_at ? format_date($coupon->expires_at) : 'Never' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$coupon->is_active ? 'green' : 'gray'">
                                        {{ $coupon->is_active ? 'Active' : 'Inactive' }}
                                    </x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}"
                                          onsubmit="return confirm('Delete this coupon?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $coupons->links() }}</div>
        @endif
    </x-admin.card>
@endsection
