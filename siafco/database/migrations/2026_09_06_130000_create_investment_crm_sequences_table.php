<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_crm_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique('inv_crm_sequences_key_unique');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        DB::table('investment_crm_sequences')->insert([
            'key' => 'investment_advisor',
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_crm_sequences');
    }
};
