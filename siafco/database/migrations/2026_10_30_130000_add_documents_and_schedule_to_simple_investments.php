<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simple_investment_contracts', function (Blueprint $table) {
            $table->unsignedSmallInteger('first_schedule_days_snapshot')->nullable()->after('signed_on');
            $table->unsignedSmallInteger('schedule_interval_days_snapshot')->nullable()->after('first_schedule_days_snapshot');
            $table->unsignedSmallInteger('schedule_projection_count_snapshot')->nullable()->after('schedule_interval_days_snapshot');
            $table->string('schedule_timezone_snapshot', 50)->nullable()->after('schedule_projection_count_snapshot');
        });

        Schema::create('simple_investment_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('simple_investment_contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->enum('status', ['uploaded', 'verified', 'observed'])->default('uploaded');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'version']);
            $table->index(['contract_id', 'status']);
        });

        Schema::create('simple_investment_schedule_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('simple_investment_contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('schedule_index');
            $table->date('scheduled_on');
            $table->enum('notice_kind', ['advance_7', 'advance_3', 'due', 'past']);
            $table->string('idempotency_key', 160)->unique();
            $table->timestamp('prepared_at');
            $table->string('channel', 30)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivery_confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'schedule_index', 'notice_kind'], 'simple_investment_notice_unique');
            $table->index(['scheduled_on', 'notice_kind'], 'simple_investment_notice_date_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simple_investment_schedule_notices');
        Schema::dropIfExists('simple_investment_document_versions');

        Schema::table('simple_investment_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'first_schedule_days_snapshot',
                'schedule_interval_days_snapshot',
                'schedule_projection_count_snapshot',
                'schedule_timezone_snapshot',
            ]);
        });
    }
};
