<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * email_verified_at was missing from User::$fillable, so every account created
 * before this release - by the installer, the admin panel or make:admin - was
 * saved unverified even though the code asked for the opposite. Email
 * verification did not work at all back then, so nobody had a way to verify.
 *
 * Without this, turning on "Require email verification" would lock every
 * existing customer out of their account area.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Irreversible: there is no record of which rows were backfilled.
    }
};
