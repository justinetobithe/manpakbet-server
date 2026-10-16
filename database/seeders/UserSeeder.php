<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'email' => 'admin@manpakbet.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'phone' => '639171234567',
                'balance' => 0,
                'role' => AccessControl::ROLE_ADMIN,
            ],
            [
                'email' => 'staff@manpakbet.com',
                'first_name' => 'Cashier',
                'last_name' => 'Staff',
                'phone' => '639171234500',
                'balance' => 0,
                'role' => AccessControl::ROLE_STAFF,
            ],
            [
                'email' => 'user@manpakbet.com',
                'first_name' => 'Test',
                'last_name' => 'Player',
                'phone' => '639171234568',
                'balance' => 2500,
                'role' => AccessControl::ROLE_USER,
            ],
        ];

        foreach ($accounts as $account) {
            $role = $account['role'];
            unset($account['role']);

            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                array_merge($account, [
                    'phone_verified_at' => now(),
                    'is_active' => true,
                    'password' => Hash::make('password123'),
                ])
            );

            $user->syncRoles([$role]);
        }
    }
}
