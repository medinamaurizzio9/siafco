<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simple_investment_investor_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->unsignedInteger('session_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->foreignId('enabled_by')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
            $table->foreign('investor_id', 'simple_investor_access_investor_fk')->references('id')->on('simple_investment_investors')->cascadeOnDelete();
            $table->foreign('enabled_by', 'simple_investor_access_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('simple_investment_portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id');
            $table->foreignId('contract_id')->nullable();
            $table->foreignId('schedule_notice_id')->nullable();
            $table->string('type', 40);
            $table->string('title', 140);
            $table->text('body');
            $table->date('reference_date')->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->timestamp('generated_at');
            $table->timestamp('displayed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->enum('external_status', ['not_configured', 'pending', 'sent'])->default('not_configured');
            $table->timestamp('external_sent_at')->nullable();
            $table->timestamps();
            $table->index(['investor_id', 'read_at'], 'simple_investor_notification_read_idx');
            $table->foreign('investor_id', 'simple_portal_notification_investor_fk')->references('id')->on('simple_investment_investors')->cascadeOnDelete();
            $table->foreign('contract_id', 'simple_portal_notification_contract_fk')->references('id')->on('simple_investment_contracts')->cascadeOnDelete();
            $table->foreign('schedule_notice_id', 'simple_portal_notification_notice_fk')->references('id')->on('simple_investment_schedule_notices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simple_investment_portal_notifications');
        Schema::dropIfExists('simple_investment_investor_accesses');
    }
};
