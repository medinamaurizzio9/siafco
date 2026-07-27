<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_plans', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('type')->default('independiente')->after('name');
            $table->string('currency', 3)->default('BOB')->after('credential_fee');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('payment_instructions')->nullable();
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->change();
            $table->string('verification_token')->nullable()->change();
        });

        Schema::create('public_affiliation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('affiliation_plan_id')->constrained()->restrictOnDelete();
            $table->uuid('public_token')->unique();
            $table->string('request_code')->unique();
            $table->decimal('amount_due', 10, 2);
            $table->string('status')->default('pending_payment')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('payment_submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('observations')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['sector_id', 'affiliation_plan_id', 'status'], 'public_affiliation_filter_idx');
        });

        Schema::table('affiliation_payments', function (Blueprint $table) {
            $table->foreignId('public_affiliation_request_id')->nullable()->after('affiliate_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('affiliation_plan_id')->nullable()->after('public_affiliation_request_id')
                ->constrained()->nullOnDelete();
            $table->decimal('expected_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('paid_amount', 10, 2)->nullable()->after('expected_amount');
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->default('transferencia');
            $table->string('bank_name')->nullable();
            $table->string('payer_name')->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->index('transaction_number', 'affiliation_payments_transaction_idx');
        });

        Schema::table('institutional_settings', function (Blueprint $table) {
            $table->string('payment_bank')->nullable();
            $table->string('payment_holder')->nullable();
            $table->string('payment_account')->nullable();
            $table->text('payment_instructions')->nullable();
        });

        Schema::table('digital_credentials', function (Blueprint $table) {
            $table->unique('affiliate_id', 'digital_credentials_affiliate_unique');
        });
    }

    public function down(): void
    {
        Schema::table('digital_credentials', fn (Blueprint $table) => $table->dropUnique('digital_credentials_affiliate_unique'));
        Schema::table('institutional_settings', function (Blueprint $table) {
            $table->dropColumn(['payment_bank', 'payment_holder', 'payment_account', 'payment_instructions']);
        });
        Schema::table('affiliation_payments', function (Blueprint $table) {
            $table->dropIndex('affiliation_payments_transaction_idx');
            $table->dropConstrainedForeignId('public_affiliation_request_id');
            $table->dropConstrainedForeignId('affiliation_plan_id');
            $table->dropColumn(['expected_amount', 'paid_amount', 'payment_date', 'payment_method', 'bank_name', 'payer_name', 'observations', 'submitted_at']);
        });
        Schema::dropIfExists('public_affiliation_requests');
        Schema::table('affiliation_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
            $table->dropColumn(['type', 'currency', 'valid_from', 'valid_until', 'payment_instructions']);
        });
    }
};
