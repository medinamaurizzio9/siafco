<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_prospects', function (Blueprint $table) {
            $table->id();
            $table->string('prospect_number')->unique('inv_prospects_number_unique');
            $table->uuid('public_id')->unique('inv_prospects_public_id_unique');
            $table->string('full_name');
            $table->string('phone', 40);
            $table->string('phone_normalized', 40)->index('inv_prospects_phone_idx');
            $table->unsignedInteger('requested_shares');
            $table->string('preferred_contact_method', 20);
            $table->foreignId('original_advisor_id');
            $table->foreignId('current_advisor_id');
            $table->string('status', 30)->default('captured')->index('inv_prospects_status_idx');
            $table->string('source', 40)->default('advisor_qr')->index('inv_prospects_source_idx');
            $table->timestamp('captured_at')->index('inv_prospects_captured_idx');
            $table->timestamp('contact_consent_at')->nullable();
            $table->string('capture_ip', 45)->nullable();
            $table->text('capture_user_agent')->nullable();
            $table->foreignId('investor_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->foreign('original_advisor_id', 'fk_inv_prospect_original_advisor')
                ->references('id')->on('investment_advisors')->restrictOnDelete();
            $table->foreign('current_advisor_id', 'fk_inv_prospect_current_advisor')
                ->references('id')->on('investment_advisors')->restrictOnDelete();
            $table->foreign('investor_id', 'fk_inv_prospect_investor')
                ->references('id')->on('investors')->nullOnDelete();
        });

        DB::table('investment_crm_sequences')->insert([
            'key' => 'investment_prospect',
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_prospects');
        DB::table('investment_crm_sequences')->where('key', 'investment_prospect')->delete();
    }
};
