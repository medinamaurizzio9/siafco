<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type', 20)->default('internal')->after('role')->index();
            $table->string('username', 60)->nullable()->after('name')->unique();
            $table->string('ci', 30)->nullable()->after('username')->unique();
            $table->string('phone', 30)->nullable()->after('ci');
            $table->string('position', 100)->nullable()->after('phone');
            $table->string('area', 100)->nullable()->after('position');
            $table->string('photo_path')->nullable()->after('area');
            $table->boolean('is_active')->default(true)->after('must_change_password')->index();
            $table->timestamp('last_login_at')->nullable()->after('is_active')->index();
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });

        DB::table('users')
            ->whereIn('id', DB::table('affiliates')->whereNotNull('user_id')->select('user_id'))
            ->update(['user_type' => 'affiliate']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['ci']);
            $table->dropIndex(['user_type']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_login_at']);
            $table->dropColumn([
                'user_type', 'username', 'ci', 'phone', 'position', 'area',
                'photo_path', 'is_active', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
