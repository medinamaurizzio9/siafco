<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_advisors', fn (Blueprint $table) => $table->string('photo_path')->nullable()->after('email'));
        Schema::table('investment_prospects', function (Blueprint $table) {
            $table->string('service_rating', 20)->nullable()->index()->after('source');
            $table->timestamp('service_rating_at')->nullable()->after('service_rating');
            $table->string('service_rating_source', 30)->nullable()->after('service_rating_at');
        });
        Schema::table('investment_crm_settings', function (Blueprint $table) {
            foreach (['service_rating_question', 'service_rating_optional_text', 'service_rating_poor_label', 'service_rating_regular_label', 'service_rating_good_label', 'service_rating_very_good_label'] as $field) $table->text($field)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('investment_crm_settings', fn (Blueprint $table) => $table->dropColumn(['service_rating_question', 'service_rating_optional_text', 'service_rating_poor_label', 'service_rating_regular_label', 'service_rating_good_label', 'service_rating_very_good_label']));
        Schema::table('investment_prospects', function (Blueprint $table) { $table->dropIndex(['service_rating']); $table->dropColumn(['service_rating', 'service_rating_at', 'service_rating_source']); });
        Schema::table('investment_advisors', fn (Blueprint $table) => $table->dropColumn('photo_path'));
    }
};
