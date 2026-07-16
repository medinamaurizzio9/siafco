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
            $table->string('code')->unique('sectors_code_unique');
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
            $table->foreignId('user_id')->nullable();
            $table->foreignId('sector_id');
            $table->foreignId('affiliation_plan_id');
            $table->string('full_name');
            $table->string('ci')->unique('affiliates_ci_unique');
            $table->string('phone')->nullable();
            $table->string('email')->unique('affiliates_email_unique');
            $table->string('address')->nullable();
            $table->string('regional')->nullable();
            $table->string('institution')->nullable();
            $table->string('position')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('registration_number')->unique('affiliates_reg_unique');
            $table->string('status')->default('pendiente_pago');
            $table->string('verification_token')->unique('affiliates_token_unique');
            $table->timestamps();
            $table->foreign('user_id', 'fk_aff_user')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sector_id', 'fk_aff_sector')->references('id')->on('sectors')->cascadeOnDelete();
            $table->foreign('affiliation_plan_id', 'fk_aff_plan')->references('id')->on('affiliation_plans')->cascadeOnDelete();
        });

        Schema::create('affiliation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id');
            $table->foreignId('confirmed_by')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('institutional_qr_path')->nullable();
            $table->string('transaction_number')->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('status')->default('pendiente');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->foreign('affiliate_id', 'fk_pay_aff')->references('id')->on('affiliates')->cascadeOnDelete();
            $table->foreign('confirmed_by', 'fk_pay_user')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('digital_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id');
            $table->string('qr_path');
            $table->string('pdf_path')->nullable();
            $table->string('png_path')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->foreign('affiliate_id', 'fk_cred_aff')->references('id')->on('affiliates')->cascadeOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->foreign('user_id', 'fk_audit_user')->references('id')->on('users')->nullOnDelete();
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
            $table->foreignId('affiliate_id');
            $table->foreignId('credit_product_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('term_months');
            $table->string('status')->default('borrador');
            $table->timestamps();
            $table->foreign('affiliate_id', 'fk_cr_app_aff')->references('id')->on('affiliates')->cascadeOnDelete();
            $table->foreign('credit_product_id', 'fk_cr_app_prod')->references('id')->on('credit_products')->nullOnDelete();
        });

        Schema::create('credit_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_application_id');
            $table->unsignedInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->string('status')->default('pendiente');
            $table->timestamps();
            $table->foreign('credit_application_id', 'fk_cr_inst_app')->references('id')->on('credit_applications')->cascadeOnDelete();
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
