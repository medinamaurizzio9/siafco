<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const INDEX = 'store_products_public_code_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('store_products', 'public_code')) {
            Schema::table('store_products', function (Blueprint $table): void {
                $table->uuid('public_code')->nullable()->after('id');
            });
        }

        DB::table('store_products')
            ->whereNull('public_code')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function ($product): void {
                do {
                    $uuid = (string) Str::uuid();
                } while (DB::table('store_products')->where('public_code', $uuid)->exists());

                DB::table('store_products')->where('id', $product->id)->update([
                    'public_code' => $uuid,
                ]);
            });

        if (! $this->uniqueIndexExists()) {
            Schema::table('store_products', function (Blueprint $table): void {
                $table->unique('public_code', self::INDEX);
            });
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE store_products MODIFY public_code CHAR(36) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('store_products', 'public_code')) {
            return;
        }

        if ($this->uniqueIndexExists()) {
            Schema::table('store_products', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }

        Schema::table('store_products', function (Blueprint $table): void {
            $table->dropColumn('public_code');
        });
    }

    private function uniqueIndexExists(): bool
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'store_products')
                ->where('index_name', self::INDEX)
                ->exists();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('store_products')"))
                ->contains(fn ($index) => ($index->name ?? null) === self::INDEX && (bool) ($index->unique ?? false));
        }

        return false;
    }
};
