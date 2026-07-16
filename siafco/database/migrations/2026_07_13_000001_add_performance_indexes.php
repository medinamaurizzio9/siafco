<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->index('status', 'aff_status_idx');
            $table->index(['sector_id', 'status'], 'aff_sector_status_idx');
            $table->index('created_at', 'aff_created_idx');
        });

        Schema::table('affiliation_payments', function (Blueprint $table) {
            $table->index('status', 'pay_status_idx');
            $table->index('confirmed_at', 'pay_confirmed_idx');
            $table->index(['status', 'confirmed_at'], 'pay_status_confirmed_idx');
        });

        Schema::table('digital_credentials', function (Blueprint $table) {
            $table->index('generated_at', 'cred_generated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('digital_credentials', function (Blueprint $table) {
            $table->dropIndex('cred_generated_idx');
        });

        Schema::table('affiliation_payments', function (Blueprint $table) {
            $table->dropIndex('pay_status_idx');
            $table->dropIndex('pay_confirmed_idx');
            $table->dropIndex('pay_status_confirmed_idx');
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropIndex('aff_status_idx');
            $table->dropIndex('aff_sector_status_idx');
            $table->dropIndex('aff_created_idx');
        });
    }
};
