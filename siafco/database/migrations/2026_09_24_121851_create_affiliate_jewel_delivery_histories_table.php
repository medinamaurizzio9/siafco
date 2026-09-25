<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('affiliate_jewel_delivery_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('action', 40);
            $table->timestamp('delivery_date_before')->nullable();
            $table->timestamp('delivery_date_after')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at'], 'jewel_history_action_created_idx');
            $table->index(['affiliate_id', 'created_at'], 'jewel_history_affiliate_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_jewel_delivery_histories');
    }
};
