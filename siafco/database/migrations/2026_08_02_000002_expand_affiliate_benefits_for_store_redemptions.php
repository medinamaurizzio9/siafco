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
        Schema::table('affiliate_benefits', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
            $table->string('benefit_type', 30)->default('informational')->after('external_url');
            $table->boolean('redeemable')->default(false)->after('benefit_type');
            $table->timestamp('starts_at')->nullable()->after('redeemable');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->json('rules')->nullable()->after('ends_at');
            $table->unsignedInteger('redemption_limit_per_affiliate')->nullable()->after('rules');
        });

        $used = [];
        DB::table('affiliate_benefits')
            ->select('id', 'title')
            ->orderBy('id')
            ->get()
            ->each(function ($benefit) use (&$used): void {
                $base = Str::slug((string) $benefit->title) ?: 'beneficio-'.$benefit->id;
                $slug = $base;
                $counter = 2;

                while (isset($used[$slug])) {
                    $slug = $base.'-'.$counter++;
                }

                $used[$slug] = true;
                DB::table('affiliate_benefits')->where('id', $benefit->id)->update(['slug' => $slug]);
            });

        Schema::table('affiliate_benefits', function (Blueprint $table) {
            $table->unique('slug', 'affiliate_benefits_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_benefits', function (Blueprint $table) {
            $table->dropUnique('affiliate_benefits_slug_unique');
            $table->dropColumn([
                'slug',
                'benefit_type',
                'redeemable',
                'starts_at',
                'ends_at',
                'rules',
                'redemption_limit_per_affiliate',
            ]);
        });
    }
};
