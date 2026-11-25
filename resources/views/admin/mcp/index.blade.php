@extends('admin.layout')
@section('title', 'MCP server')
@section('subtitle', 'Connect an AI client to your blog posts.')

@section('content')
    <div class="max-w-3xl space-y-6">
        <x-admin.card title="Connection">
            <p class="text-sm text-slate-600">Choose Streamable HTTP in your MCP client and enter this endpoint.</p>
            <code class="mt-2 block break-all">{{ route('mcp.server') }}</code>
            <p class="mt-3 text-sm text-slate-600">Set the Authorization header to <code>Bearer YOUR_TOKEN</code>. Use HTTPS for remote connections.</p>
        </x-admin.card>

        @if (session('mcp_secret'))
            <x-admin.card title="Copy your token">
                <label class="block text-sm">Save this token in your client's secret settings. It appears once.
                    <input readonly onclick="this.select()" value="{{ session('mcp_secret') }}"
                           class="mt-2 w-full rounded-lg border px-3 py-2 font-mono text-sm">
                </label>
            </x-admin.card>
        @endif

        <x-admin.card title="Issue a token">
            <form method="POST" action="{{ route('admin.mcp.store') }}" class="space-y-4">
                @csrf
                <label class="block text-sm">Client name
                    <input name="name" value="{{ old('name') }}" maxlength="100" required
                           class="mt-1 block w-full rounded-lg border px-3 py-2">
                </label>
                <label class="block text-sm">Blog access
                    <select name="access" class="mt-1 block w-full rounded-lg border px-3 py-2">
                        <option value="read">Read and analyse all posts, including drafts</option>
                        <option value="write">Read, analyse, create, edit and publish posts</option>
                    </select>
                </label>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Create token</button>
            </form>
        </x-admin.card>

        <x-admin.card title="Issued tokens">
            <ul class="divide-y divide-slate-100">
                @forelse ($tokens as $token)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-medium">{{ $token->name }}</p>
                            <p class="text-xs text-slate-500">{{ $token->can_write ? 'Read and write' : 'Read only' }} · {{ $token->user?->name ?? 'Former account' }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.mcp.destroy', $token) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm font-semibold text-red-700" aria-label="Revoke token for {{ $token->name }}">Revoke</button>
                        </form>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No tokens issued.</li>
                @endforelse
            </ul>
        </x-admin.card>
    </div>
@endsection
