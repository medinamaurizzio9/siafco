<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_settings', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name')->default('Cooperativa Tierra Bendita');
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 20)->default('#0b1f3a');
            $table->string('secondary_color', 20)->default('#d4af37');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('payment_qr_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_settings');
    }
};
