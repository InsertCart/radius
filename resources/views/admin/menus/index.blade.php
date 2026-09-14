@extends('admin.layout')
@section('title', 'Menus')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-admin.card bodyClass="">
                @if ($menus->isEmpty())
                    <x-admin.empty message="No menus yet. Themes usually look for one called 'header'." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($menus as $menu)
                            <li class="flex items-center justify-between gap-3 p-4">
                                <div>
                                    <a href="{{ route('admin.menus.edit', $menu) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                        {{ $menu->name }}
                                    </a>
                                    <p class="text-xs text-slate-400">
                                        <code>{{ $menu->slug }}</code> &middot; {{ $menu->items_count }} items
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}"
                                      onsubmit="return confirm('Delete this menu and its items?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.card>
        </div>

        <x-admin.card title="New menu">
            <form method="POST" action="{{ route('admin.menus.store') }}" class="space-y-4">
                @csrf

                <x-form.field label="Name" name="name" required>
                    <x-form.input name="name" required />
                </x-form.field>

                <x-form.field label="Slug" name="slug" required help="Themes reference menus by this, e.g. header or footer.">
                    <x-form.input name="slug" required />
                </x-form.field>

                <x-form.field label="Description" name="description">
                    <x-form.input name="description" />
                </x-form.field>

                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Create menu
                </button>
            </form>
        </x-admin.card>
    </div>
@endsection
