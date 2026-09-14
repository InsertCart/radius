@props(['name', 'value' => null, 'rows' => 4])

<textarea name="{{ $name }}"
          id="{{ $name }}"
          rows="{{ $rows }}"
          {{ $attributes->merge([
              'class' => 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
          ]) }}>{{ old($name, $value) }}</textarea>
