<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliation_payments', 'currency')) {
                $table->string('currency', 3)->default('BOB')->after('paid_amount');
            }
            if (! Schema::hasColumn('affiliation_payments', 'source')) {
                $table->string('source')->default('web')->after('status');
            }
            if (! Schema::hasColumn('affiliation_payments', 'registered_by')) {
                $table->foreignId('registered_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('affiliation_payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_date');
            }
            if (! Schema::hasColumn('affiliation_payments', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('transaction_number');
            }
            if (! Schema::hasColumn('affiliation_payments', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('affiliation_payments', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('affiliation_payments', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('affiliation_payments', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('voided_by');
            }
            if (! Schema::hasColumn('affiliation_payments', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_at');
            }
            if (! Schema::hasColumn('affiliation_payments', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->after('void_reason');
                $table->unique('receipt_number', 'affiliation_payments_receipt_number_unique');
            }
        });

        DB::table('affiliation_payments')->whereNull('paid_amount')->update(['paid_amount' => DB::raw('amount')]);
        DB::table('affiliation_payments')->whereNull('expected_amount')->update(['expected_amount' => DB::raw('amount')]);
        DB::table('affiliation_payments')->whereNull('currency')->update(['currency' => 'BOB']);
        DB::table('affiliation_payments')->whereNull('source')->update(['source' => 'web']);
    }

    public function down(): void
    {
        Schema::table('affiliation_payments', function (Blueprint $table) {
            if (Schema::hasColumn('affiliation_payments', 'receipt_number')) {
                $table->dropUnique('affiliation_payments_receipt_number_unique');
                $table->dropColumn('receipt_number');
            }
            foreach (['void_reason', 'voided_at'] as $column) {
                if (Schema::hasColumn('affiliation_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
            foreach (['voided_by', 'rejected_by', 'registered_by'] as $column) {
                if (Schema::hasColumn('affiliation_payments', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['rejected_at', 'reference_number', 'paid_at', 'source', 'currency'] as $column) {
                if (Schema::hasColumn('affiliation_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
