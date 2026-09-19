<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer's address book. Orders keep their own JSON copy of the
        // address they were placed with, so editing one of these later never
        // rewrites the history of an order that has already shipped.
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->nullable();     // "Home", "Office"
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('line1', 190);
            $table->string('line2', 190)->nullable();
            $table->string('city', 120);
            $table->string('state', 120)->nullable();
            $table->string('postcode', 30)->nullable();
            $table->char('country', 2);
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default_billing']);
            $table->index(['user_id', 'is_default_shipping']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
