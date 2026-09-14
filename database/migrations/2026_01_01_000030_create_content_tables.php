<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns every SEO-aware model shares. Kept in one place so posts, pages,
     * products and categories all expose an identical SEO panel in the admin.
     */
    private function seoColumns(Blueprint $table): void
    {
        $table->string('meta_title')->nullable();
        $table->text('meta_description')->nullable();
        $table->string('meta_keywords')->nullable();
        $table->string('og_image')->nullable();
        $table->string('canonical_url')->nullable();
        $table->string('schema_type', 60)->nullable();
        $table->json('schema_data')->nullable();
        $table->boolean('noindex')->default(false);
    }

    public function up(): void
    {
        // Shared by the blog and the shop; 'type' keeps the trees apart.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('blog')->index();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('show_in_menu')->default(true);
            $this->seoColumns($table);
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('reading_minutes')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('allow_comments')->default(true);
            $this->seoColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('featured_image')->nullable();
            // Names a Blade view inside the active theme, e.g. 'landing'
            // resolves to theme::pages.landing. Falls back to the default page view.
            $table->string('template', 60)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('show_in_menu')->default(false);
            $table->boolean('is_homepage')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $this->seoColumns($table);
            $table->softDeletes();
            $table->timestamps();
        });

        // Menus are built in the admin and rendered by the active theme.
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();   // 'header', 'footer', ...
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('type', 20)->default('custom'); // custom|page|post|category|product|route
            $table->string('url')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('target', 20)->default('_self');
            $table->string('icon', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Hide an item automatically when the module behind it is switched off.
            $table->string('requires_module', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
