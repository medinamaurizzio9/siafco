<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliates', 'origin')) {
                $table->string('origin', 40)->nullable()->after('status');
            }
            if (! Schema::hasColumn('affiliates', 'registered_by')) {
                $table->foreignId('registered_by')->nullable()->after('origin')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('affiliates', 'managed_by')) {
                $table->foreignId('managed_by')->nullable()->after('registered_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('affiliates', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('managed_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('affiliates', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });

        Schema::table('institutional_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('institutional_settings', 'web_affiliation_manager_id')) {
                $table->foreignId('web_affiliation_manager_id')->nullable()->after('payment_instructions')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('affiliates', function (Blueprint $table): void {
            $this->indexIfMissing($table, 'affiliates', ['origin'], 'affiliates_origin_idx');
            $this->indexIfMissing($table, 'affiliates', ['registered_by'], 'affiliates_registered_by_idx');
            $this->indexIfMissing($table, 'affiliates', ['managed_by'], 'affiliates_managed_by_idx');
            $this->indexIfMissing($table, 'affiliates', ['approved_by'], 'affiliates_approved_by_idx');
            $this->indexIfMissing($table, 'affiliates', ['created_at'], 'affiliates_created_at_idx');
            $this->indexIfMissing($table, 'affiliates', ['status'], 'affiliates_status_idx');
        });

        $managerId = $this->initialWebManagerId();
        if ($managerId) {
            DB::table('institutional_settings')->orderBy('id')->limit(1)->update([
                'web_affiliation_manager_id' => $managerId,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('institutional_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('institutional_settings', 'web_affiliation_manager_id')) {
                $table->dropConstrainedForeignId('web_affiliation_manager_id');
            }
        });

        Schema::table('affiliates', function (Blueprint $table): void {
            foreach ([
                'affiliates_origin_idx',
                'affiliates_registered_by_idx',
                'affiliates_managed_by_idx',
                'affiliates_approved_by_idx',
                'affiliates_created_at_idx',
                'affiliates_status_idx',
            ] as $index) {
                $this->dropIndexIfExists($table, 'affiliates', $index);
            }

            foreach (['approved_by', 'managed_by', 'registered_by'] as $column) {
                if (Schema::hasColumn('affiliates', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['approved_at', 'origin'] as $column) {
                if (Schema::hasColumn('affiliates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function initialWebManagerId(): ?int
    {
        $base = User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
            ->whereIn('role', ['superadministrador', 'administrador', 'gerente', 'secretaria', 'administrador_sector']);

        $mauricio = (clone $base)
            ->where(function ($query): void {
                $query->where('name', 'like', '%Mauricio%')
                    ->orWhere('name', 'like', '%Maurizzio%')
                    ->orWhere('email', 'like', '%mauricio%')
                    ->orWhere('email', 'like', '%maurizzio%')
                    ->orWhere('username', 'like', '%mauricio%')
                    ->orWhere('username', 'like', '%maurizzio%');
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        return $mauricio ?: (clone $base)->orderBy('created_at')->orderBy('id')->value('id');
    }

    private function indexIfMissing(Blueprint $table, string $tableName, array $columns, string $index): void
    {
        if (! $this->indexExists($tableName, $index)) {
            $table->index($columns, $index);
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $index): void
    {
        if ($this->indexExists($tableName, $index)) {
            $table->dropIndex($index);
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (method_exists(Schema::getFacadeRoot(), 'getIndexes')) {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $definition) => ($definition['name'] ?? null) === $index);
        }

        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
