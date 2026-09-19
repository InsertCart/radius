{{-- Renders one settings field from its config/settings.php definition. --}}
@php
    $label = $field['label'] ?? \Illuminate\Support\Str::headline($key);
    $help = $field['help'] ?? null;
    $options = $field['options'] ?? [];

    // Options may be an inline map or the name of a shared set.
    if (is_string($options)) {
        $options = $optionSets[$options] ?? [];
    }
@endphp

@switch($type)
    @case('notice')
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-sm font-medium text-slate-700">{{ $label }}</p>
            @if ($help)
                <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $help }}</p>
            @endif
        </div>
        @break

    @case('boolean')
        <x-form.toggle :name="$key" :label="$label" :checked="(bool) $value" :help="$help"
                       x-model="values['{{ $key }}']" />
        @break

    @case('textarea')
        <x-form.field :label="$label" :name="$key" :help="$help">
            <x-form.textarea :name="$key" :value="$value" rows="3" />
        </x-form.field>
        @break

    @case('code')
        <x-form.field :label="$label" :name="$key" :help="$help">
            <x-form.textarea :name="$key" :value="$value" rows="6" spellcheck="false"
                             class="block w-full rounded-lg border border-slate-300 bg-slate-900 px-3 py-2 font-mono text-xs text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        </x-form.field>
        @break

    @case('select')
        <x-form.field :label="$label" :name="$key" :help="$help">
            <x-form.select :name="$key" :options="$options" :value="$value" x-model="values['{{ $key }}']" />
        </x-form.field>
        @break

    @case('multiselect')
        <x-form.field :label="$label" :name="$key" :help="$help">
            <x-form.multiselect :name="$key" :options="$options" :value="(array) $value" />
        </x-form.field>
        @break

    @case('image')
        <x-form.media :name="$key" :label="$label" :value="$value" />
        @break

    @case('color')
        <x-form.field :label="$label" :name="$key" :help="$help">
            <div class="flex items-center gap-2">
                <input type="color" name="{{ $key }}" value="{{ old($key, $value ?: '#2563eb') }}"
                       class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300">
                <span class="font-mono text-xs text-slate-500">{{ old($key, $value) }}</span>
            </div>
        </x-form.field>
        @break

    @case('secret')
        <x-form.field :label="$label" :name="$key"
                      :help="$help ?? 'Leave blank to keep the saved value. Stored encrypted; never shown again.'">
            <x-form.input :name="$key" type="password" autocomplete="new-password"
                          placeholder="{{ settings()->get($key) ? '••••••••  (saved)' : 'Not set' }}" />
        </x-form.field>
        @break

    @default
        <x-form.field :label="$label" :name="$key" :help="$help">
            <x-form.input :name="$key" :type="$type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text'))"
                          :value="$value" step="any" />
        </x-form.field>
@endswitch
