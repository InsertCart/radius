<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('customer')->after('email')->index();
            $table->string('phone', 30)->nullable()->after('email_verified_at');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('avatar')->nullable()->after('phone_verified_at');
            $table->string('status', 20)->default('active')->after('avatar')->index();

            // Two-factor authentication (TOTP authenticator apps).
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->softDeletes();
        });

        // Short-lived one-time codes for SMS/email verification and OTP login.
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->index();   // phone number or email
            $table->string('channel', 20)->default('sms');
            $table->string('purpose', 40)->default('login');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'phone', 'phone_verified_at', 'avatar', 'status',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'last_login_at', 'last_login_ip', 'deleted_at',
            ]);
        });
    }
};
