<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@clientflow.com'],
            [
                'name'     => 'Admin',
                'email'    => 'admin@clientflow.com',
                'password' => Hash::make('password123'),
            ]
        );
    }
}
