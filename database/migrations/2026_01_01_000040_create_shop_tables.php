<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 80)->nullable()->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('featured_image')->nullable();

            // Money is stored in minor units (paise/cents) as integers so that
            // totals never drift the way floats do.
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->unsignedBigInteger('cost_price')->nullable();

            $table->string('type', 20)->default('simple'); // simple|variable|digital
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock')->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->string('digital_file')->nullable();

            $table->decimal('weight', 10, 3)->nullable();
            $table->string('dimensions', 60)->nullable();
            $table->boolean('requires_shipping')->default(true);

            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $this->seoColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'is_featured']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 80)->nullable();
            $table->json('options');                 // {"Size":"M","Color":"Blue"}
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->integer('stock')->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('verified_purchase')->default(false);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('description')->nullable();
            $table->string('type', 20)->default('percent');  // percent|fixed|free_shipping
            $table->unsignedBigInteger('value')->default(0); // percent*100, or minor units
            $table->unsignedBigInteger('min_order_total')->default(0);
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 120)->nullable()->index();
            $table->string('coupon_code', 60)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            // Snapshotted so a mid-session price change cannot silently alter
            // what the customer thought they were paying.
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 60)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email');
            $table->string('phone', 30)->nullable();

            $table->string('status', 30)->default('pending')->index();
            // pending|processing|shipped|completed|cancelled|refunded
            $table->string('payment_status', 30)->default('unpaid')->index();
            // unpaid|paid|failed|refunded|partially_refunded|awaiting

            $table->string('payment_gateway', 40)->nullable();
            $table->string('transaction_id')->nullable()->index();

            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->string('coupon_code', 60)->nullable();

            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('tracking_number')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            // Name and SKU are copied in so the line survives the product being
            // renamed or deleted later.
            $table->string('name');
            $table->string('sku', 80)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->json('options')->nullable();
            $table->string('digital_file')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
        });

        // One row per attempt against a gateway, including failures. This is
        // the audit trail when a buyer has to reconcile a disputed payment.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('gateway', 40)->index();
            $table->string('type', 20)->default('payment'); // payment|refund
            $table->string('reference')->nullable()->index();  // our reference sent to the gateway
            $table->string('gateway_reference')->nullable()->index(); // theirs
            $table->unsignedBigInteger('amount')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 30)->default('pending')->index();
            $table->text('message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });

        // Per-gateway on/off switch, mode and encrypted credentials.
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->boolean('is_enabled')->default(false)->index();
            $table->string('mode', 10)->default('test');  // test|live
            $table->text('credentials')->nullable();      // encrypted JSON
            $table->string('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
    }
};
