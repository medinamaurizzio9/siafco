<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('affiliate_type')->nullable()->after('position');
            $table->text('administrative_notes')->nullable()->after('affiliate_type');
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('status_reason')->nullable()->after('status_changed_by');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
            $table->text('deletion_reason')->nullable()->after('deleted_by');
        });

        Schema::table('digital_credentials', function (Blueprint $table) {
            $table->string('status')->default('vigente')->after('affiliate_id')->index();
            $table->timestamp('suspended_at')->nullable()->after('generated_at');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')
                ->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable()->after('suspended_by');
            $table->timestamp('files_invalidated_at')->nullable()->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('digital_credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['status', 'suspended_at', 'suspension_reason', 'files_invalidated_at']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn([
                'affiliate_type',
                'administrative_notes',
                'status_changed_at',
                'status_reason',
                'deletion_reason',
            ]);
        });
    }
};
