<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('summary', 500);
            $t->longText('body');
            $t->string('status')->default('draft');
            $t->string('priority');
            $t->boolean('requires_confirmation')->default(false);
            $t->json('audience');
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('publisher_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('publish_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'publish_at']);
        });
        Schema::create('communication_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('name');
            $t->boolean('cover')->default(false);
        });
        Schema::create('communication_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->timestamp('assigned_at');
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->unique(['communication_id', 'affiliate_id']);
            $t->index(['affiliate_id', 'opened_at']);
        });
        Schema::create('communication_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event');
            $t->timestamp('created_at');
        });
        Schema::create('affiliate_push_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->uuid('device_id');
            $t->text('token');
            $t->char('token_hash', 64)->unique();
            $t->boolean('enabled');
            $t->unique(['affiliate_id', 'device_id']);
        });
        Schema::create('communication_push_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_id')->constrained('affiliate_push_devices')->cascadeOnDelete();
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at');
            $t->string('result')->nullable();
            $t->unique(['communication_id', 'device_id']);
            $t->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        foreach (['communication_push_deliveries', 'affiliate_push_devices', 'communication_audit_logs', 'communication_recipients', 'communication_attachments', 'communications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
