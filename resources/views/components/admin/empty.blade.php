@props(['message' => 'Nothing here yet.', 'action' => null, 'actionUrl' => null])

<div class="px-6 py-12 text-center">
    <p class="text-sm text-slate-500">{{ $message }}</p>
    @if ($action && $actionUrl)
        <a href="{{ $actionUrl }}" class="mt-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            {{ $action }}
        </a>
    @endif
</div>
