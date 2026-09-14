<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A visual layout: the tree of sections, columns and widgets that the
         * builder edits.
         *
         * A layout attaches either to a content model (a page, post or
         * product) or to a named theme region such as the header. Region
         * layouts are scoped to a theme, because a layout built against one
         * theme's markup rarely fits another.
         */
        Schema::create('layouts', function (Blueprint $table) {
            $table->id();

            // Content layout: the model this belongs to.
            $table->nullableMorphs('layoutable');

            // Region layout: 'header', 'footer', 'sidebar', ... plus its theme.
            $table->string('region', 60)->nullable();
            $table->string('theme_slug', 60)->nullable();

            $table->json('data')->nullable();

            // The published layout is what visitors see; the draft is what the
            // editor autosaves into, so an unfinished edit never goes live.
            $table->json('draft_data')->nullable();

            // Compiled CSS for this layout, rebuilt whenever the tree changes.
            $table->longText('compiled_css')->nullable();

            $table->boolean('is_enabled')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['region', 'theme_slug']);
            $table->index(['layoutable_type', 'layoutable_id', 'is_enabled'], 'layouts_owner_index');
        });

        /*
         * Point-in-time snapshots, so an editor can undo a bad save after the
         * fact. Trimmed to a fixed number per layout.
         */
        Schema::create('layout_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_id')->constrained()->cascadeOnDelete();
            $table->json('data');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['layout_id', 'created_at']);
        });

        /*
         * Reusable saved blocks: an admin designs a section once and drops it
         * into any page. Also backs the starter templates shipped with the CMS.
         */
        Schema::create('layout_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 60)->default('section');
            $table->string('type', 20)->default('section'); // section|page
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('data');
            $table->boolean('is_global')->default(false);
            $table->boolean('is_builtin')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /*
         * Design tokens: the palette and type scale the editor offers, so a
         * site stays visually consistent instead of accumulating one-off hex
         * codes. Referenced from settings as var(--cb-color-primary).
         */
        Schema::create('design_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('group', 30)->default('color'); // color|font|spacing
            $table->string('key', 60);
            $table->string('label');
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_tokens');
        Schema::dropIfExists('layout_presets');
        Schema::dropIfExists('layout_revisions');
        Schema::dropIfExists('layouts');
    }
};
