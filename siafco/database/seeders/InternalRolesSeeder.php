<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class InternalRolesSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->whereNull('user_type')
            ->whereDoesntHave('affiliate')
            ->update(['user_type' => 'internal']);

        User::query()
            ->whereHas('affiliate')
            ->update(['user_type' => 'affiliate']);
    }
}
