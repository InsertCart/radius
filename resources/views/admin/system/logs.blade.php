@extends('admin.layout')
@section('title', 'Error log')
@section('subtitle', 'Last 300 lines of '.basename($path))

@section('content')
    <x-admin.card :description="number_format($size / 1024, 1).' KB on disk'">
        @if (empty($lines))
            <p class="py-8 text-center text-sm text-slate-500">The log is empty. That is good news.</p>
        @else
<pre class="max-h-[32rem] overflow-auto rounded-xl bg-slate-900 p-4 text-[11px] leading-relaxed text-slate-100">{{ implode("\n", $lines) }}</pre>
        @endif
    </x-admin.card>
@endsection
