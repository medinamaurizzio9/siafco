<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->default('star');
            $table->string('route_name')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->boolean('visible_when_pending')->default(true);
            $table->unsignedInteger('order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_benefits');
    }
};
