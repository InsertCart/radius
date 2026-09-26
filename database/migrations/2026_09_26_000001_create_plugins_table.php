<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separately installed add-ons. Rows are synced from the plugins/ folder, the
 * same way themes are from themes/, so a plugin copied in over FTP appears in
 * the admin panel on its next visit.
 *
 * A plugin arrives switched off. Nothing it contains runs until an
 * administrator turns it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('version', 40)->default('1.0.0');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('author_url')->nullable();
            $table->boolean('enabled')->default(false)->index();

            // The whole plugin.json, as last read from disk.
            $table->json('meta')->nullable();

            // The plugin's own settings. Kept here rather than in the settings
            // table so that uninstalling a plugin takes its settings with it.
            $table->json('config')->nullable();

            // Where it came from: 'manual' (uploaded or copied in) or
            // 'marketplace', which makes it eligible for directory updates.
            $table->string('source', 20)->default('manual');
            $table->string('source_slug')->nullable();

            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
