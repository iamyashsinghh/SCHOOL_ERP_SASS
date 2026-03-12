<?php

namespace Database\Seeders\Central;

use App\Models\Central\CentralUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CentralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CentralUser::updateOrCreate(
            ['email' => 'admin@governance.com'],
            [
                'name' => 'Platform Owner',
                'password' => Hash::make('password'),
                'role' => 'platform_owner',
            ]
        );
    }
}
