<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('role')->unique('role_permission_overrides_role_unique');
            $table->json('permissions');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->addAuditIndex('created_at', 'audit_logs_created_at_index');
        $this->addAuditIndex('action', 'audit_logs_action_index');
        $this->addAuditIndex('ip_address', 'audit_logs_ip_address_index');
    }

    public function down(): void
    {
        $this->dropAuditIndex('audit_logs_ip_address_index');
        $this->dropAuditIndex('audit_logs_action_index');
        $this->dropAuditIndex('audit_logs_created_at_index');

        Schema::dropIfExists('role_permission_overrides');
    }

    private function addAuditIndex(string $column, string $name): void
    {
        if ($this->hasAuditIndex($name)) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) use ($column, $name) {
            $table->index($column, $name);
        });
    }

    private function dropAuditIndex(string $name): void
    {
        if (! $this->hasAuditIndex($name)) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) use ($name) {
            $table->dropIndex($name);
        });
    }

    private function hasAuditIndex(string $name): bool
    {
        return collect(Schema::getIndexes('audit_logs'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $name);
    }
};
