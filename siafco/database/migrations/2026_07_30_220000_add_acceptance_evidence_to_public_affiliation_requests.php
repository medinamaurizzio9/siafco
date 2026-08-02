<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_affiliation_requests', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('user_agent');
            $table->timestamp('privacy_accepted_at')->nullable()->after('terms_accepted_at');
            $table->string('terms_version', 40)->nullable()->after('privacy_accepted_at');
            $table->string('privacy_version', 40)->nullable()->after('terms_version');
            $table->string('acceptance_ip', 45)->nullable()->after('privacy_version');
            $table->text('acceptance_user_agent')->nullable()->after('acceptance_ip');
        });
    }

    public function down(): void
    {
        Schema::table('public_affiliation_requests', function (Blueprint $table) {
            $table->dropColumn([
                'terms_accepted_at',
                'privacy_accepted_at',
                'terms_version',
                'privacy_version',
                'acceptance_ip',
                'acceptance_user_agent',
            ]);
        });
    }
};
