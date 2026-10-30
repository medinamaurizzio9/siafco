<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rrhh_workers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('full_name', 180);
            $table->string('ci', 30)->unique();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('position', 120)->nullable();
            $table->date('joined_on');
            $table->string('schedule_description')->nullable();
            $table->string('compensation_mode', 30);
            $table->decimal('agreed_amount', 14, 2)->nullable();
            $table->boolean('commissions_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'is_active']);
            $table->index('full_name');
        });

        Schema::create('rrhh_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('reference_price', 14, 2);
            $table->char('currency', 3)->default('BOB');
            $table->string('commission_type', 20);
            $table->decimal('commission_value', 14, 4);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rrhh_sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('sold_on');
            $table->foreignId('worker_id')->constrained('rrhh_workers')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('commission_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['worker_id', 'sold_on']);
            $table->index(['status', 'sold_on']);
        });

        Schema::create('rrhh_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('rrhh_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('rrhh_products')->restrictOnDelete();
            $table->string('product_code_snapshot', 30);
            $table->string('product_name_snapshot', 150);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('commission_type_snapshot', 20);
            $table->decimal('commission_value_snapshot', 14, 4);
            $table->decimal('commission_amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('rrhh_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('rrhh_workers')->restrictOnDelete();
            $table->date('applies_on');
            $table->string('direction', 20);
            $table->string('concept', 180);
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('submission_key')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['worker_id', 'applies_on', 'status']);
        });

        Schema::create('rrhh_pay_periods', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('worker_id')->constrained('rrhh_workers')->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('recognized_days')->nullable();
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('commission_total', 14, 2)->default(0);
            $table->decimal('adjustment_total', 14, 2)->default(0);
            $table->decimal('calculated_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('calculated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('calculated_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['worker_id', 'date_from', 'date_to']);
            $table->index(['status', 'date_from']);
        });

        Schema::create('rrhh_pay_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_period_id')->constrained('rrhh_pay_periods')->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('sale_item_id')->nullable()->unique()->constrained('rrhh_sale_items')->restrictOnDelete();
            $table->foreignId('adjustment_id')->nullable()->unique()->constrained('rrhh_adjustments')->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rrhh_pay_lines');
        Schema::dropIfExists('rrhh_pay_periods');
        Schema::dropIfExists('rrhh_adjustments');
        Schema::dropIfExists('rrhh_sale_items');
        Schema::dropIfExists('rrhh_sales');
        Schema::dropIfExists('rrhh_products');
        Schema::dropIfExists('rrhh_workers');
    }
};
