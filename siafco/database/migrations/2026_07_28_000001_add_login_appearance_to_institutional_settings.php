<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutional_settings', function (Blueprint $table) {
            $table->string('login_background_path')->nullable();
            $table->string('login_logo_path')->nullable();
            $table->string('login_title')->default('SISTEMA DE AFILIACIÓN');
            $table->string('login_institution_name')->default('COOPERATIVA TIERRA BENDITA');
            $table->text('login_affiliate_message')->nullable();
            $table->unsignedTinyInteger('login_overlay_opacity')->default(65);
        });
    }

    public function down(): void
    {
        Schema::table('institutional_settings', function (Blueprint $table) {
            $table->dropColumn([
                'login_background_path',
                'login_logo_path',
                'login_title',
                'login_institution_name',
                'login_affiliate_message',
                'login_overlay_opacity',
            ]);
        });
    }
};
