{{-- The SEO panel shared by posts, pages, products and categories.
     Expects $model (any HasSeo model) and $schemaTypes. --}}
<x-admin.card title="Search engine optimisation"
              description="Leave blank to fall back to the title and excerpt.">
    <div class="space-y-5">
        <x-form.field label="Meta title" name="meta_title"
                      help="Around 60 characters. Falls back to the title above.">
            <x-form.input name="meta_title" :value="$model->meta_title"
                          x-data="{}" x-on:input="$el.nextElementSibling.textContent = $el.value.length + ' characters'" />
            <p class="text-xs text-slate-400">{{ strlen((string) $model->meta_title) }} characters</p>
        </x-form.field>

        <x-form.field label="Meta description" name="meta_description"
                      help="Around 155 characters. This is the snippet shown in search results.">
            <x-form.textarea name="meta_description" :value="$model->meta_description" rows="3" />
        </x-form.field>

        <x-form.field label="Focus keywords" name="meta_keywords" help="Comma separated.">
            <x-form.input name="meta_keywords" :value="$model->meta_keywords" />
        </x-form.field>

        <x-form.media name="og_image" label="Social share image" :value="$model->og_image" />

        <x-form.field label="Schema type" name="schema_type"
                      help="The schema.org type emitted as JSON-LD for this item.">
            <x-form.select name="schema_type" :options="$schemaTypes" :value="$model->schema_type"
                           placeholder="Use the default for this content type" />
        </x-form.field>

        <x-form.field label="Canonical URL" name="canonical_url"
                      help="Only set this when the same content also lives at another address.">
            <x-form.input name="canonical_url" type="url" :value="$model->canonical_url" />
        </x-form.field>

        <x-form.toggle name="noindex" label="Hide from search engines" :checked="(bool) $model->noindex"
                       help="Adds a noindex tag to this page only." />
    </div>
</x-admin.card>
