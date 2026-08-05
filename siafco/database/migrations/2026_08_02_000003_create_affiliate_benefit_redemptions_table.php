<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_benefit_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('affiliate_benefit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('requested_at');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['affiliate_benefit_id', 'affiliate_id'], 'benefit_redemptions_benefit_affiliate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_benefit_redemptions');
    }
};
