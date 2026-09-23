<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@vatican.local'],
            [
                'name' => 'Vatican Administrator',
                'password' => 'VaticanAdmin@2026!',
                'email_verified_at' => now(),
            ],
        );
    }
}
