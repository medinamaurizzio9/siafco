<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_coupon_usages', function (Blueprint $table): void {
            $table->timestamp('released_at')->nullable()->after('used_at');
            $table->string('release_reason')->nullable()->after('released_at');
            $table->foreignId('released_by_user_id')->nullable()->after('release_reason')->constrained('users')->nullOnDelete();
        });

        Schema::create('store_order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('admin_note')->nullable();
            $table->timestamp('changed_at')->index();
            $table->timestamps();

            $table->index(['store_order_id', 'changed_at'], 'store_order_status_histories_order_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_status_histories');

        Schema::table('store_coupon_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('released_by_user_id');
            $table->dropColumn(['released_at', 'release_reason']);
        });
    }
};
