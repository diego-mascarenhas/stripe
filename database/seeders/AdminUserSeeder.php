<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'administracion@revisionalpha.es'],
            [
                'name' => 'Administración',
                'password' => Hash::make('Simplicity!'),
                'email_verified_at' => now(),
            ],
        );
    }
}
