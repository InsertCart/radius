@extends('admin.layout')
@section('title', 'Comments')

@section('content')
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.comments.index', ['status' => $key]) }}"
               @class([
                   'rounded-lg px-4 py-2 text-sm font-medium transition',
                   'bg-indigo-600 text-white' => $status === $key,
                   'bg-white text-slate-600 hover:bg-slate-50' => $status !== $key,
               ])>
                {{ $label }}
                @if (($counts[$key] ?? 0) > 0)
                    <span class="ml-1 text-xs opacity-75">{{ $counts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <x-admin.card bodyClass="">
        @if ($comments->isEmpty())
            <x-admin.empty message="Nothing to moderate here." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($comments as $comment)
                    <li class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">
                                    <span class="font-medium text-slate-900">{{ $comment->displayName() }}</span>
                                    <span class="text-slate-400">&lt;{{ $comment->author_email }}&gt;</span>
                                    <span class="text-xs text-slate-400">&middot; {{ $comment->created_at->diffForHumans() }}</span>
                                </p>
                                <p class="mt-1 text-sm text-slate-700">{{ $comment->body }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    on <a href="{{ route('admin.posts.edit', $comment->post_id) }}" class="hover:text-indigo-600">{{ $comment->post?->title ?? 'a deleted post' }}</a>
                                    @if ($comment->ip_address) &middot; {{ $comment->ip_address }} @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-admin.badge :color="match($comment->status) { 'approved' => 'green', 'spam' => 'red', default => 'amber' }">
                                    {{ ucfirst($comment->status) }}
                                </x-admin.badge>

                                @foreach (['approved' => 'Approve', 'pending' => 'Unapprove', 'spam' => 'Spam'] as $value => $label)
                                    @continue($comment->status === $value)
                                    <form method="POST" action="{{ route('admin.comments.update', $comment) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $value }}">
                                        <button class="text-xs text-slate-500 hover:text-indigo-600">{{ $label }}</button>
                                    </form>
                                @endforeach

                                <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}"
                                      onsubmit="return confirm('Delete this comment permanently?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 p-4">{{ $comments->links() }}</div>
        @endif
    </x-admin.card>
@endsection
