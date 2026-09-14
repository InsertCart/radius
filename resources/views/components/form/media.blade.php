@props(['name', 'label' => 'Image', 'value' => null])

{{-- A path field with an upload button. Files go through the media library so
     that validation and thumbnailing happen in exactly one place. --}}
<div x-data="mediaField('{{ $name }}', @js(old($name, $value)))" class="space-y-2">
    <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>

    <div class="flex items-start gap-3">
        <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50">
            <template x-if="preview">
                <img :src="preview" alt="" class="h-full w-full object-cover">
            </template>
            <template x-if="! preview">
                <span class="text-[10px] text-slate-400">No image</span>
            </template>
        </div>

        <div class="flex-1 space-y-2">
            <input type="hidden" name="{{ $name }}" :value="path">
            <input type="text" x-model="path" @input="syncPreview"
                   placeholder="Path or full URL"
                   class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <div class="flex gap-2">
                <button type="button" @click="$refs.file.click()"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                        x-text="uploading ? 'Uploading...' : 'Upload'"></button>
                <button type="button" @click="clear"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                    Clear
                </button>
            </div>
            <input type="file" x-ref="file" class="hidden" accept="image/*" @change="upload">
        </div>
    </div>

    @error($name)
        <p class="text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
