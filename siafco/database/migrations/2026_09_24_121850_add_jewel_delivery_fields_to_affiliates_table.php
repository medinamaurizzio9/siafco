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
        Schema::table('affiliates', function (Blueprint $table) {
            $table->timestamp('jewel_delivered_at')->nullable()->after('verification_token');
            $table->foreignId('jewel_delivered_by')->nullable()->after('jewel_delivered_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jewel_delivered_by');
            $table->dropColumn('jewel_delivered_at');
        });
    }
};
