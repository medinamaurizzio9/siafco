<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliation_payments', 'payment_timezone')) {
                $table->string('payment_timezone', 80)->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliation_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('affiliation_payments', 'payment_timezone')) {
                $table->dropColumn('payment_timezone');
            }
        });
    }
};
