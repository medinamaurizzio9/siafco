<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_advisors', function (Blueprint $table) {
            $table->id();
            $table->string('advisor_number')->unique('inv_advisors_number_unique');
            $table->uuid('public_id')->unique('inv_advisors_public_id_unique');
            $table->string('public_token', 64)->unique('inv_advisors_token_unique');
            $table->string('full_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->unique('inv_advisors_user_unique');
            $table->boolean('is_active')->default(true)->index('inv_advisors_active_idx');
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'fk_inv_advisor_user')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'fk_inv_advisor_creator')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_advisors');
    }
};
