<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value store behind the settings() helper. Everything the admin
        // panel exposes as an option lands here.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->string('type')->default('text');
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        // One row per optional feature. Disabled modules register no routes.
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->boolean('is_core')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        // Installed front-end templates. Rows are synced from themes/ on boot.
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('version')->default('1.0.0');
            $table->string('author')->nullable();
            $table->string('author_url')->nullable();
            $table->text('description')->nullable();
            $table->string('screenshot')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->json('meta')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 60)->default('public');
            $table->string('path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->json('conversions')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mime_type', 'created_at']);
        });

        // Attaches media to any model (post featured image, product gallery...).
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('mediable');
            $table->string('collection', 60)->default('default');
            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['mediable_type', 'mediable_id', 'collection'], 'mediables_lookup_index');
        });

        // Admin audit trail. Useful for buyers running multi-admin sites.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('mediables');
        Schema::dropIfExists('media');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('settings');
    }
};
