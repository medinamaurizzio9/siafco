<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_deposits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BOB');
            $table->dateTime('deposited_at');
            $table->string('transaction_number', 120);
            $table->string('voucher_path')->nullable();
            $table->text('observations')->nullable();
            $table->string('status', 30)->default('under_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['registered_by', 'status']);
            $table->index('deposited_at');
            $table->index('transaction_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_deposits');
    }
};
