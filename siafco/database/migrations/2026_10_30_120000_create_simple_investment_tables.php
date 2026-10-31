<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simple_investment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('product_name', 80)->default('Acción');
            $table->decimal('unit_price', 16, 2)->default(14000);
            $table->char('currency', 3)->default('BOB');
            $table->unsignedSmallInteger('minimum_units')->default(1);
            $table->unsignedSmallInteger('maximum_units')->default(20);
            $table->unsignedSmallInteger('first_schedule_days')->default(40);
            $table->unsignedSmallInteger('schedule_interval_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('simple_investment_sequences', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });

        Schema::create('simple_investment_investors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('full_name', 180);
            $table->string('ci', 30);
            $table->string('ci_complement', 10)->nullable();
            $table->string('expedition_department', 30)->nullable();
            $table->string('identity_key', 100)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('registered_on');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'full_name']);
        });

        Schema::create('simple_investment_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 30)->unique();
            $table->foreignId('investor_id')->constrained('simple_investment_investors')->restrictOnDelete();
            $table->string('product_name_snapshot', 80);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price_snapshot', 16, 2);
            $table->decimal('total_amount', 16, 2);
            $table->char('currency', 3)->default('BOB');
            $table->date('registered_on');
            $table->date('signed_on')->nullable();
            $table->enum('status', ['draft', 'pending_signature', 'signed', 'void'])->default('draft');
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'registered_on']);
        });

        Schema::create('simple_investment_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('contract_id')->constrained('simple_investment_contracts')->restrictOnDelete();
            $table->foreignId('investor_id')->constrained('simple_investment_investors')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['contract_id', 'position']);
        });

        DB::table('simple_investment_settings')->insert([
            'product_name' => 'Acción', 'unit_price' => 14000, 'currency' => 'BOB',
            'minimum_units' => 1, 'maximum_units' => 20,
            'first_schedule_days' => 40, 'schedule_interval_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('simple_investment_sequences')->insert([
            ['key' => 'investor', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contract', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'unit', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('simple_investment_units');
        Schema::dropIfExists('simple_investment_contracts');
        Schema::dropIfExists('simple_investment_investors');
        Schema::dropIfExists('simple_investment_sequences');
        Schema::dropIfExists('simple_investment_settings');
    }
};
