@extends('installer.layout', ['step' => 'requirements'])
@section('title', 'Server requirements')
@section('description', 'Everything below needs a green tick before the CMS can run.')

@section('content')
    <div class="space-y-6">
        <div>
            <h3 class="mb-2 text-sm font-semibold text-slate-700">PHP and extensions</h3>
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                @foreach ($checks as $check)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <div>
                            <span class="text-slate-700">{{ $check['label'] }}</span>
                            <span class="ml-1 text-xs text-slate-400">{{ $check['required'] }}</span>
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-50 text-emerald-700' => $check['passed'],
                            'bg-rose-50 text-rose-700' => ! $check['passed'],
                        ])>{{ $check['current'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-slate-700">Folder permissions</h3>
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                @foreach ($permissions as $permission)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <code class="text-slate-700">{{ $permission['label'] }}</code>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-50 text-emerald-700' => $permission['passed'],
                            'bg-rose-50 text-rose-700' => ! $permission['passed'],
                        ])>{{ $permission['current'] }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-slate-500">
                If any folder is not writable, set it to 755 (or 775) and make sure it is owned by
                the user your web server runs as.
            </p>
        </div>

        <div class="border-t border-slate-100 pt-5">
            @if ($canContinue)
                <a href="{{ route('install.database') }}"
                   class="inline-block rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Continue to the database
                </a>
            @else
                <div class="flex flex-wrap items-center gap-3">
                    <button disabled class="cursor-not-allowed rounded-lg bg-slate-300 px-5 py-2.5 text-sm font-semibold text-white">
                        Continue
                    </button>
                    <a href="{{ route('install.requirements') }}" class="text-sm text-indigo-600 hover:underline">Re-check</a>
                    <span class="text-sm text-rose-600">Fix the items marked in red first.</span>
                </div>
            @endif
        </div>
    </div>
@endsection
