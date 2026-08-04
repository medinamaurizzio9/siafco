<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->text('whatsapp_number_encrypted')->nullable();
            $table->char('whatsapp_number_hash', 64)->nullable()->unique();
            $table->string('whatsapp_number_hint')->nullable();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('pickup_enabled')->default(true);
            $table->boolean('shipping_enabled')->default(false);
            $table->text('pickup_instructions')->nullable();
            $table->text('shipping_instructions')->nullable();
            $table->char('default_currency', 3)->default('BOB');
            $table->unsignedInteger('max_receipt_size_kb')->default(6144);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->decimal('regular_price', 12, 2);
            $table->decimal('affiliate_price', 12, 2);
            $table->decimal('promo_price', 12, 2)->nullable();
            $table->timestamp('promo_starts_at')->nullable();
            $table->timestamp('promo_ends_at')->nullable();
            $table->string('availability_status', 30)->default('disponible')->index();
            $table->json('delivery_modes');
            $table->unsignedInteger('max_quantity_per_order')->default(10);
            $table->boolean('featured')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('store_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_product_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_code')->unique();
            $table->string('type', 60);
            $table->string('name');
            $table->string('sku_suffix')->nullable();
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('store_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->unsignedInteger('order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('store_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_code')->unique();
            $table->string('scope', 30)->index();
            $table->string('department')->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('zone')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('BOB');
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('priority')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('store_coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_code')->unique();
            $table->char('code_hash', 64)->unique();
            $table->text('code_encrypted');
            $table->string('code_hint');
            $table->string('type', 30);
            $table->decimal('value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('minimum_amount', 12, 2)->default(0);
            $table->unsignedInteger('global_limit')->nullable();
            $table->unsignedInteger('per_affiliate_limit')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('store_coupon_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('store_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['store_product_id', 'store_category_id'], 'store_coupon_targets_target_idx');
        });

        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status', 30)->default('pendiente')->index();
            $table->string('delivery_method', 30)->index();
            $table->string('department')->nullable();
            $table->string('city')->nullable();
            $table->string('zone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->char('currency', 3)->default('BOB');
            $table->json('coupon_snapshot')->nullable();
            $table->json('shipping_snapshot')->nullable();
            $table->json('payment_snapshot')->nullable();
            $table->text('whatsapp_number_snapshot')->nullable();
            $table->timestamp('whatsapp_opened_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'status'], 'store_orders_affiliate_status_idx');
        });

        Schema::create('store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku_snapshot');
            $table->string('name_snapshot');
            $table->string('variant_snapshot')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('store_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['store_order_id', 'status'], 'store_order_receipts_order_status_idx');
        });

        Schema::create('store_coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('used_at');
            $table->timestamps();

            $table->index(['store_coupon_id', 'affiliate_id'], 'store_coupon_usages_coupon_affiliate_idx');
            $table->index(['store_coupon_id', 'used_at'], 'store_coupon_usages_coupon_used_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_coupon_usages');
        Schema::dropIfExists('store_order_receipts');
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('store_coupon_targets');
        Schema::dropIfExists('store_coupons');
        Schema::dropIfExists('store_shipping_rates');
        Schema::dropIfExists('store_product_images');
        Schema::dropIfExists('store_product_variants');
        Schema::dropIfExists('store_products');
        Schema::dropIfExists('store_categories');
        Schema::dropIfExists('store_settings');
    }
};
