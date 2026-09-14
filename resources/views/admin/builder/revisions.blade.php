@extends('admin.layout')
@section('title', 'Layout history')
@section('subtitle', 'Every published version, newest first')

@section('content')
    <x-admin.card bodyClass="">
        @if ($revisions->isEmpty())
            <x-admin.empty message="No previous versions yet. One is stored each time you publish." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($revisions as $revision)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <p class="text-sm font-medium text-slate-900">
                                {{ format_date($revision->created_at, 'd M Y, H:i') }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ $revision->summary() }}
                                @if ($revision->author) &middot; {{ $revision->author->name }} @endif
                                &middot; {{ $revision->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('admin.builder.revisions.restore', [$layout, $revision->id]) }}"
                              onsubmit="return confirm('Restore this version? The current layout is saved to history first.')">
                            @csrf
                            <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                Restore
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 p-4">{{ $revisions->links() }}</div>
        @endif
    </x-admin.card>

    <p class="mt-4">
        <a href="{{ route('admin.builder.index') }}" class="text-sm text-slate-500 hover:text-slate-900">
            &larr; Back to the builder
        </a>
    </p>
@endsection
