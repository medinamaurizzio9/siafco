<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 120);
            $table->string('idempotency_key', 200);
            $table->string('request_hash', 64);
            $table->string('status', 30)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scope', 'idempotency_key'], 'mobile_idempotency_unique');
            $table->index(['scope', 'status'], 'mobile_idempotency_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_api_idempotency_keys');
    }
};
