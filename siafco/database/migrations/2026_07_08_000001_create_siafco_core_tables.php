<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('regional')->nullable();
            $table->string('institution')->nullable();
            $table->unsignedInteger('current_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('affiliation_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('affiliation_fee', 10, 2)->default(0);
            $table->decimal('credential_fee', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliation_plan_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('ci')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('address')->nullable();
            $table->string('regional')->nullable();
            $table->string('institution')->nullable();
            $table->string('position')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('registration_number')->unique();
            $table->string('status')->default('pendiente_pago');
            $table->string('verification_token')->unique();
            $table->timestamps();
        });

        Schema::create('affiliation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('institutional_qr_path')->nullable();
            $table->string('transaction_number')->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('status')->default('pendiente');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('digital_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('qr_path');
            $table->string('pdf_path')->nullable();
            $table->string('png_path')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('annual_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('term_months');
            $table->string('status')->default('borrador');
            $table->timestamps();
        });

        Schema::create('credit_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->string('status')->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_installments');
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('credit_products');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('digital_credentials');
        Schema::dropIfExists('affiliation_payments');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('affiliation_plans');
        Schema::dropIfExists('sectors');
    }
};
