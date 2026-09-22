<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two things the mobile API needs to know before it answers anybody:
 * which app is calling, and which customer that app is calling on behalf of.
 *
 * An app is registered once, in the admin panel, and gets a key and a secret.
 * A customer signs in through that app and gets a token of their own. Neither
 * is stored in a form that is useful to somebody who steals the database: the
 * secret is encrypted with the application key, and tokens are kept only as a
 * SHA-256 digest, the same way push device tokens already are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform', 20)->default('mobile');

            // The public half. Travels in a header on every request and is
            // safe to write down; on its own it opens nothing.
            $table->string('client_id', 64)->unique();

            // The private half, encrypted rather than hashed: signed requests
            // need the plaintext to recompute the HMAC.
            $table->text('secret');

            // The last four characters, so the admin screen can tell two
            // credentials apart without being able to show either of them.
            $table->string('secret_hint', 8)->nullable();

            $table->boolean('enabled')->default(true)->index();

            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // What the customer sees in "devices signed in to this account".
            $table->string('device_name')->nullable();

            // sha256 of the token itself. Too long to index as text, and
            // nothing here ever needs to read a token back.
            $table->string('token_hash', 64)->unique();

            // The long-lived half, rotated every time it is used, so a stolen
            // refresh token stops working the moment the real device refreshes.
            $table->string('refresh_hash', 64)->nullable()->unique();

            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();

            // Kept rather than deleted, so a customer signing a device out is
            // visible in the audit trail and a replayed token stays dead.
            $table->timestamp('revoked_at')->nullable()->index();

            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('api_clients');
    }
};
