<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_advisor_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_advisor_id')->unique();
            $table->string('login_code', 6)->unique();
            $table->string('pin_hash');
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('must_change_pin')->default(true);
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('pin_changed_at')->nullable();
            $table->timestamps();
            $table->foreign('investment_advisor_id', 'fk_inv_advisor_access_advisor')->references('id')->on('investment_advisors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_advisor_accesses');
    }
};
