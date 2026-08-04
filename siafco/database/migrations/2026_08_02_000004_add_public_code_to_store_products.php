<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->uuid('public_code')->nullable()->after('id');
        });

        DB::table('store_products')->select('id')->orderBy('id')->get()->each(function ($product): void {
            DB::table('store_products')->where('id', $product->id)->update([
                'public_code' => (string) Str::uuid(),
            ]);
        });

        Schema::table('store_products', function (Blueprint $table) {
            $table->unique('public_code', 'store_products_public_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->dropUnique('store_products_public_code_unique');
            $table->dropColumn('public_code');
        });
    }
};
