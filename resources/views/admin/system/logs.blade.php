@extends('admin.layout')
@section('title', 'Error log')
@section('subtitle', 'Last 300 lines of '.basename($path))

@section('content')
    <x-admin.card :description="number_format($size / 1024, 1).' KB on disk'">
        @if ($size > 0)
            <form method="POST" action="{{ route('admin.system.logs.clear') }}" class="mb-4 flex justify-end"
                  onsubmit="return confirm('Delete every entry in the error log? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                    Clear log
                </button>
            </form>
        @endif

        @if (empty($lines))
            <p class="py-8 text-center text-sm text-slate-500">The log is empty. That is good news.</p>
        @else
<pre class="max-h-[32rem] overflow-auto rounded-xl bg-slate-900 p-4 text-[11px] leading-relaxed text-slate-100">{{ implode("\n", $lines) }}</pre>
        @endif
    </x-admin.card>
@endsection
