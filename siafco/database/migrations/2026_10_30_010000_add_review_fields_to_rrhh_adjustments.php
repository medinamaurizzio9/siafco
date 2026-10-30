<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rrhh_adjustments', function (Blueprint $table) {
            $table->string('origin', 30)->default('manual')->after('status');
            $table->string('review_status', 20)->default('approved')->after('origin');
            $table->foreignId('source_sale_id')->nullable()->unique()->after('review_status')->constrained('rrhh_sales')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('rrhh_adjustments', function (Blueprint $table) {
            $table->dropForeign(['source_sale_id']);
            $table->dropUnique(['source_sale_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['origin', 'review_status', 'source_sale_id', 'reviewed_by', 'reviewed_at', 'review_notes']);
        });
    }
};
