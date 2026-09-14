@props(['name', 'type' => 'text', 'value' => null])

<input type="{{ $type }}"
       name="{{ $name }}"
       id="{{ $name }}"
       value="{{ old($name, $value) }}"
       {{ $attributes->merge([
           'class' => 'block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 '
               .($errors->has($name) ? 'border-rose-400' : 'border'),
       ]) }}>
