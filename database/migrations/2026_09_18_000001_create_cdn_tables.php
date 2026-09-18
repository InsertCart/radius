<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media storage providers, and the two facts the media library needs to know
 * about each file once one is switched on: whether it reached the provider,
 * and whether this server still has its own copy.
 *
 * Both default to the state every existing file is already in - not offloaded,
 * local copy present - so an upgrade changes nothing until an owner configures
 * a provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdn_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->boolean('is_enabled')->default(false);

            // Mirroring is the default. A buyer who offloads their media and
            // then loses the bucket should still have every file here.
            $table->boolean('keep_local')->default(true);

            // Lets several sites share one bucket without colliding.
            $table->string('path_prefix')->nullable();

            $table->text('credentials')->nullable();
            $table->timestamps();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->boolean('on_cdn')->default(false)->after('path');
            $table->boolean('has_local_copy')->default(true)->after('on_cdn');

            // The sync screen counts what is left to move, on every visit.
            $table->index(['on_cdn', 'has_local_copy']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['on_cdn', 'has_local_copy']);
            $table->dropColumn(['on_cdn', 'has_local_copy']);
        });

        Schema::dropIfExists('cdn_connections');
    }
};
