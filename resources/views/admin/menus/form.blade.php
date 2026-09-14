@extends('admin.layout')
@section('title', 'New menu')

@section('content')
    <form method="POST" action="{{ route('admin.menus.store') }}" class="mx-auto max-w-lg">
        @csrf
        <x-admin.card>
            <div class="space-y-5">
                <x-form.field label="Name" name="name" required>
                    <x-form.input name="name" :value="$menu->name" required autofocus />
                </x-form.field>
                <x-form.field label="Slug" name="slug" required>
                    <x-form.input name="slug" :value="$menu->slug" required />
                </x-form.field>
                <x-form.field label="Description" name="description">
                    <x-form.input name="description" :value="$menu->description" />
                </x-form.field>
            </div>
            <div class="mt-6 border-t border-slate-100 pt-5">
                <button class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Create menu
                </button>
            </div>
        </x-admin.card>
    </form>
@endsection
