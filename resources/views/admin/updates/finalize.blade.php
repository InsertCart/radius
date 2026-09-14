@extends('admin.layout')
@section('title', $failed ? 'The update did not finish' : 'Update complete')

@section('content')
    <div class="mx-auto max-w-2xl">
        @if ($failed)
            <x-admin.card>
                <div class="text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-rose-100">
                        <svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 8v5m0 3.5h.01M10.3 3.9L2.4 17a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                        </svg>
                    </div>

                    <h2 class="mt-4 text-lg font-semibold text-slate-900">The finishing steps failed</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        The new files for version {{ $to }} are installed, but the database migrations or the cache
                        clearing did not complete. The site may not work correctly until this is resolved.
                    </p>

                    @if ($message)
                        <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-left font-mono text-xs text-slate-600">
                            {{ $message }}
                        </p>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('admin.updates.finalize') }}"
                       class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Try again
                    </a>

                    <form method="POST" action="{{ route('admin.updates.rollback') }}"
                          onsubmit="return confirm('Put version {{ $from }} back?')">
                        @csrf
                        <label class="mr-2 text-xs text-slate-600">
                            <input type="checkbox" name="restore_database" value="1" class="rounded border-slate-300">
                            also restore the database
                        </label>
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Roll back to {{ $from }}
                        </button>
                    </form>
                </div>
            </x-admin.card>
        @else
            <x-admin.card>
                <div class="text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-100">
                        <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>

                    <h2 class="mt-4 text-lg font-semibold text-slate-900">Updated to version {{ $to }}</h2>
                    <p class="mt-1 text-sm text-slate-600">Upgraded from {{ $from }}. Your content and settings are unchanged.</p>
                </div>

                <dl class="mt-5 divide-y divide-slate-100 border-t border-slate-100 text-sm">
                    @foreach ($steps as $label => $detail)
                        <div class="flex gap-3 py-2.5">
                            <dt class="w-48 shrink-0 font-medium text-slate-700">{{ $label }}</dt>
                            <dd class="min-w-0 whitespace-pre-line text-xs text-slate-500">{{ $detail }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($skipped)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
                        <p class="font-medium">{{ count($skipped) }} file(s) you had edited were left untouched:</p>
                        <ul class="mt-1 font-mono">
                            @foreach (array_slice($skipped, 0, 10) as $file)
                                <li>{{ $file }}</li>
                            @endforeach
                            @if (count($skipped) > 10)
                                <li>and {{ count($skipped) - 10 }} more</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="mt-5 flex justify-center gap-2">
                    <a href="{{ route('admin.dashboard') }}"
                       class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Back to the dashboard
                    </a>
                    <a href="{{ route('admin.system.index') }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Check the system page
                    </a>
                </div>
            </x-admin.card>
        @endif
    </div>
@endsection
