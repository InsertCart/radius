<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 30);
            $table->string('to', 30)->index();
            $table->text('message');
            $table->string('status', 20)->default('queued')->index();
            $table->string('provider_reference')->nullable();
            $table->text('error')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });

        // Firebase Cloud Messaging registration tokens.
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->string('token_hash', 64)->unique();  // sha256, because tokens are too long to index
            $table->string('platform', 20)->default('web');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('status', 20)->default('subscribed')->index();
            $table->string('token', 64)->nullable()->index();  // unsubscribe link
            $table->string('source', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->json('extra')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // SEO overrides for routes that are not backed by a content model
        // (the homepage, the shop index, a search page and so on).
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('route_key')->unique();   // 'home', 'shop.index', ...
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('schema_type', 60)->nullable();
            $table->json('schema_data')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();
        });

        // 301/302 map so buyers can migrate an old site without losing rankings.
        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source')->unique();
            $table->string('destination');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('subscribers');
        Schema::dropIfExists('push_devices');
        Schema::dropIfExists('sms_logs');
    }
};
