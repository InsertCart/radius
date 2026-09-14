@extends('admin.layout')
@section('title', 'Modules')
@section('subtitle', 'Switch features on and off. A disabled module registers no routes, so it costs nothing to run.')

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($modules as $slug => $module)
            <div @class([
                'rounded-2xl border bg-white p-5 shadow-sm transition',
                'border-indigo-200 ring-1 ring-indigo-100' => $module['enabled'],
                'border-slate-200 opacity-75' => ! $module['enabled'],
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="flex items-center gap-2 font-semibold text-slate-900">
                            {{ $module['name'] }}
                            @if ($module['core'] ?? false)
                                <x-admin.badge color="gray">Required</x-admin.badge>
                            @endif
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $module['description'] ?? '' }}</p>
                    </div>

                    <form method="POST" action="{{ route('admin.modules.toggle', $slug) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                @disabled($module['core'] ?? false)
                                @class([
                                    'relative inline-flex h-6 w-11 shrink-0 rounded-full transition',
                                    'bg-indigo-600' => $module['enabled'],
                                    'bg-slate-300' => ! $module['enabled'],
                                    'cursor-not-allowed opacity-50' => $module['core'] ?? false,
                                ])
                                title="{{ ($module['core'] ?? false) ? 'This module is required' : ($module['enabled'] ? 'Turn off' : 'Turn on') }}">
                            <span @class([
                                'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition',
                                'left-[22px]' => $module['enabled'],
                                'left-0.5' => ! $module['enabled'],
                            ])></span>
                        </button>
                    </form>
                </div>

                <dl class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    @if ($module['usage'])
                        <div class="flex justify-between"><dt>Content</dt><dd>{{ $module['usage'] }}</dd></div>
                    @endif
                    @if ($module['requires'])
                        <div class="flex justify-between">
                            <dt>Requires</dt>
                            <dd>{{ collect($module['requires'])->map(fn ($r) => modules()->name($r))->implode(', ') }}</dd>
                        </div>
                    @endif
                    @if ($module['dependents'])
                        <div class="flex justify-between text-amber-600">
                            <dt>Needed by</dt>
                            <dd>{{ collect($module['dependents'])->map(fn ($r) => modules()->name($r))->implode(', ') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endforeach
    </div>

    <p class="mt-6 text-xs text-slate-500">
        Turning a module off hides its screens and unregisters its routes. Nothing is deleted, so
        switching it back on restores everything exactly as it was.
    </p>
@endsection
