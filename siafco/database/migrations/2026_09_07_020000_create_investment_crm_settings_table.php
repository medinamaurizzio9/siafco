<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_crm_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('singleton_key')->default(true)->unique();

            foreach (['public_form_brand', 'public_form_subtitle', 'public_form_title', 'public_form_description', 'public_form_name_label', 'public_form_name_placeholder', 'public_form_phone_label', 'public_form_phone_placeholder', 'public_form_shares_label', 'public_form_shares_placeholder', 'public_form_shares_help', 'public_form_contact_question', 'public_form_whatsapp_label', 'public_form_call_label', 'public_form_consent_text', 'public_form_submit_text', 'success_title', 'success_message', 'success_contact_message', 'success_code_label', 'advisor_portal_title', 'advisor_portal_subtitle', 'advisor_dashboard_greeting', 'advisor_dashboard_description', 'login_title', 'login_subtitle', 'login_code_label', 'login_pin_label', 'login_button_text', 'footer_text'] as $field) {
                $table->text($field)->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_crm_settings');
    }
};
