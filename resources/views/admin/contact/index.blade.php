@extends('admin.layout')
@section('title', 'Messages')
@section('subtitle', $unreadCount.' unread')

@section('content')
    <x-admin.card bodyClass="">
        <form method="GET" class="flex flex-wrap gap-2 border-b border-slate-100 p-4">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search messages"
                   class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="checkbox" name="unread" value="1" @checked($filters['unread'] ?? false)
                       class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                Unread only
            </label>
            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
        </form>

        @if ($submissions->isEmpty())
            <x-admin.empty message="No messages yet." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($submissions as $submission)
                    <li>
                        <a href="{{ route('admin.contact.show', $submission) }}"
                           class="flex items-start gap-3 p-4 hover:bg-slate-50">
                            <span @class([
                                'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                'bg-indigo-500' => ! $submission->read_at,
                                'bg-transparent' => $submission->read_at,
                            ])></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p @class(['truncate text-sm', 'font-semibold text-slate-900' => ! $submission->read_at, 'text-slate-700' => $submission->read_at])>
                                        {{ $submission->name }}
                                        <span class="font-normal text-slate-400">&lt;{{ $submission->email }}&gt;</span>
                                    </p>
                                    <span class="shrink-0 text-xs text-slate-400">{{ $submission->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="truncate text-sm text-slate-600">{{ $submission->subject ?: 'No subject' }}</p>
                                <p class="truncate text-xs text-slate-400">{{ $submission->message }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 p-4">{{ $submissions->links() }}</div>
        @endif
    </x-admin.card>
@endsection
