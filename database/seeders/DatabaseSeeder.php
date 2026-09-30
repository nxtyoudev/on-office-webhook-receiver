<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'name' => 'Admin User',
            'email' => 'accounts@nxtyou.de',
            'email_verified_at' => now(),
            'password' => Hash::make('6>(U£l6£Uq2Y'),
            'remember_token' => Str::random(10),
        ]);
    }
}
