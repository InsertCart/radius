<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers where a theme came from, so one installed from the marketplace can
 * be offered its updates.
 *
 * These are columns rather than keys inside `meta` because ThemeManager::sync()
 * rewrites `meta` from theme.json on every pass - including each visit to the
 * Themes screen - and would erase them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('screenshot');
            $table->string('source_slug')->nullable()->after('source');
            $table->timestamp('installed_at')->nullable()->after('source_slug');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_slug', 'installed_at']);
        });
    }
};
