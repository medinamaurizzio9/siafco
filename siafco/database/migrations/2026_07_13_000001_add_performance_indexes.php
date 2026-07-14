<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->index('status');
            $table->index(['sector_id', 'status']);
            $table->index('created_at');
        });

        Schema::table('affiliation_payments', function (Blueprint $table) {
            $table->index('status');
            $table->index('confirmed_at');
            $table->index(['status', 'confirmed_at']);
        });

        Schema::table('digital_credentials', function (Blueprint $table) {
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('digital_credentials', function (Blueprint $table) {
            $table->dropIndex(['generated_at']);
        });

        Schema::table('affiliation_payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['confirmed_at']);
            $table->dropIndex(['status', 'confirmed_at']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['sector_id', 'status']);
            $table->dropIndex(['created_at']);
        });
    }
};
